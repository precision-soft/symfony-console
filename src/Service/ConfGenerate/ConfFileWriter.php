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

    /** @return array<int, string> */
    public function save(ConfFilesDto $confFilesDto, string $destinationDir): array
    {
        $tempDir = \sys_get_temp_dir() . '/' . \uniqid('conf_', true);

        $this->filesystem->mkdir($tempDir, 0755);

        $backupDir = null;

        $backupRestored = false;

        try {
            $configurationFiles = [];

            foreach ($confFilesDto->getFiles() as $path => $content) {
                if (false === \str_starts_with($path, $destinationDir)) {
                    throw new ConfGenerateException(\sprintf('path `%s` is outside destination directory `%s`', $path, $destinationDir));
                }

                $relativePath = \ltrim(\substr($path, \strlen($destinationDir)), '/');

                if (false !== \strpos($relativePath, '..')) {
                    throw new ConfGenerateException(\sprintf('path traversal detected in `%s`', $path));
                }

                $tempPath = $tempDir . '/' . $relativePath;

                $this->filesystem->dumpFile($tempPath, $content);

                $configurationFiles[] = $path;
            }

            if (true === $this->filesystem->exists($destinationDir)) {
                $backupDir = $destinationDir . '.bak_' . \uniqid('', true);
                $this->filesystem->rename($destinationDir, $backupDir);
            }

            try {
                $this->filesystem->rename($tempDir, $destinationDir);
            } catch (Throwable $throwable) {
                if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                    try {
                        $this->filesystem->rename($backupDir, $destinationDir);
                        $backupRestored = true;
                    } catch (Throwable) {
                    }
                }

                throw new ConfGenerateException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
            }

            if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                try {
                    $this->filesystem->remove($backupDir);
                } catch (Throwable) {
                }
            }

            return $configurationFiles;
        } catch (Throwable $throwable) {
            if (true === $this->filesystem->exists($tempDir)) {
                $this->filesystem->remove($tempDir);
            }

            if (false === $backupRestored && null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                $this->filesystem->remove($backupDir);
            }

            throw $throwable instanceof ConfGenerateException
                ? $throwable
                : new ConfGenerateException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }
    }

    public function initLogsDir(string $logsDir): void
    {
        $this->filesystem->mkdir($logsDir, 0755);
    }
}
