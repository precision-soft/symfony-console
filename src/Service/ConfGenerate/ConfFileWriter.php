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

        /** @info guard a TOCTOU race where an attacker could pre-create a symlink at our chosen path; `Filesystem::mkdir` is a no-op when the path already exists (as a directory or a symlink pointing at one), so we explicitly verify the path we just created is a real directory */
        if (true === \is_link($temporaryDirectory) || false === \is_dir($temporaryDirectory)) {
            throw new ConfGenerateException(\sprintf('temporary directory `%s` is not a real directory', $temporaryDirectory));
        }

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

    /**
     * @return array<int, string>
     * @throws ConfGenerateException
     */
    protected function writeTemporaryFiles(ConfFilesDto $confFilesDto, string $destinationDir, string $temporaryDirectory): array
    {
        $configurationFiles = [];

        /** @info enforce a trailing separator so `/tmp/conf` does not match `/tmp/confAAAA/...` via prefix alone */
        $destinationDirPrefix = \rtrim($destinationDir, '/') . '/';

        $canonicalTemporaryDirectory = \realpath($temporaryDirectory);

        if (false === $canonicalTemporaryDirectory) {
            throw new ConfGenerateException(\sprintf('temporary directory `%s` could not be canonicalized', $temporaryDirectory));
        }

        foreach ($confFilesDto->getFiles() as $path => $content) {
            if (false === \str_starts_with($path, $destinationDirPrefix)) {
                throw new ConfGenerateException(\sprintf('path `%s` is outside destination directory `%s`', $path, $destinationDir));
            }

            $relativePath = \substr($path, \strlen($destinationDirPrefix));

            if (true === \str_contains($relativePath, '..')) {
                throw new ConfGenerateException(\sprintf('path traversal detected in `%s`', $path));
            }

            $tempPath = $temporaryDirectory . '/' . $relativePath;

            $this->filesystem->dumpFile($tempPath, $content);

            /** @info after writing, canonicalize the resulting file path and verify it stays within the (already canonicalized) temporary directory — guards against symlink-based escapes that passed the textual checks above */
            $canonicalTempPath = \realpath($tempPath);

            if (
                false === $canonicalTempPath
                || false === \str_starts_with($canonicalTempPath, $canonicalTemporaryDirectory . '/')
            ) {
                throw new ConfGenerateException(\sprintf('path `%s` escaped the temporary directory after canonicalization', $path));
            }

            $configurationFiles[] = $path;
        }

        return $configurationFiles;
    }

    /** @throws ConfGenerateException */
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
