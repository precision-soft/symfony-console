<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service\ConfGenerate;

use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class ConfFileWriter
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * @return array<int, string>
     * @throws ConfGenerateException
     */
    public function save(ConfFilesDto $confFilesDto, string $destinationDir): array
    {
        if (0 === \count($confFilesDto->getFiles())) {
            return [];
        }

        $temporaryDirectory = \sys_get_temp_dir() . '/conf_' . \bin2hex(\random_bytes(8));

        $this->filesystem->mkdir($temporaryDirectory, 0755);

        $backupDirectory = null;

        $backupRestored = false;

        try {
            $configurationFiles = $this->writeTemporaryFiles($confFilesDto, $destinationDir, $temporaryDirectory);

            if (true === $this->filesystem->exists($destinationDir)) {
                $backupDirectory = $destinationDir . '.bak_' . \bin2hex(\random_bytes(8));
                $this->filesystem->rename($destinationDir, $backupDirectory);
            }

            $this->activateDirectory($temporaryDirectory, $destinationDir, $backupDirectory, $backupRestored);

            if (null !== $backupDirectory) {
                $this->silentRemove($backupDirectory);
            }

            return $configurationFiles;
        } catch (Throwable $throwable) {
            $this->silentRemove($temporaryDirectory);

            if (false === $backupRestored && null !== $backupDirectory && true === $this->filesystem->exists($backupDirectory)) {
                throw new ConfGenerateException(
                    \sprintf('%s — backup preserved at `%s`', $throwable->getMessage(), $backupDirectory),
                    (int)$throwable->getCode(),
                    true === $throwable instanceof ConfGenerateException ? $throwable->getPrevious() : $throwable,
                );
            }

            throw true === $throwable instanceof ConfGenerateException
                ? $throwable
                : new ConfGenerateException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }
    }

    public function initLogsDir(string $logsDir): void
    {
        $this->filesystem->mkdir($logsDir, 0755);
    }

    /** @return array<int, string> */
    protected function writeTemporaryFiles(ConfFilesDto $confFilesDto, string $destinationDir, string $temporaryDirectory): array
    {
        $configurationFiles = [];

        foreach ($confFilesDto->getFiles() as $path => $content) {
            if (false === \str_starts_with($path, $destinationDir)) {
                throw new ConfGenerateException(\sprintf('path `%s` is outside destination directory `%s`', $path, $destinationDir));
            }

            $relativePath = \ltrim(\substr($path, \strlen($destinationDir)), '/');

            if (true === \str_contains($relativePath, '..')) {
                throw new ConfGenerateException(\sprintf('path traversal detected in `%s`', $path));
            }

            $tempPath = $temporaryDirectory . '/' . $relativePath;

            $this->filesystem->dumpFile($tempPath, $content);

            $configurationFiles[] = $path;
        }

        return $configurationFiles;
    }

    protected function activateDirectory(string $temporaryDirectory, string $destinationDir, ?string $backupDirectory, bool &$backupRestored): void
    {
        try {
            $this->filesystem->rename($temporaryDirectory, $destinationDir);
        } catch (Throwable $throwable) {
            if (null !== $backupDirectory && true === $this->filesystem->exists($backupDirectory)) {
                $backupRestored = $this->tryRestoreBackup($backupDirectory, $destinationDir);
            }

            throw new ConfGenerateException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }
    }

    protected function tryRestoreBackup(string $backupDirectory, string $destinationDir): bool
    {
        try {
            $this->filesystem->rename($backupDirectory, $destinationDir);

            return true;
        } catch (Throwable) {
            /** @info backup restore failed, original error is rethrown by caller */
            return false;
        }
    }

    protected function silentRemove(string $path): void
    {
        if (true === $this->filesystem->exists($path)) {
            try {
                $this->filesystem->remove($path);
            } catch (Throwable) {
                /** @info cleanup is non-critical */
            }
        }
    }
}
