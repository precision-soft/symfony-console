<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service\ConfGenerate;

use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileChangesDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileStatus;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class ConfFileWriter
{
    public function __construct(
        protected readonly Filesystem $filesystem,
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

        $this->assertRealDestinationDir($destinationDir);

        /* staged next to the destination so the activation rename is a same-filesystem atomic swap */
        $temporaryDirectory = \rtrim(\dirname($destinationDir), '/') . '/.conf_' . \bin2hex(\random_bytes(8));

        $this->filesystem->mkdir($temporaryDirectory, 0755);

        /* `Filesystem::mkdir` is a no-op on an existing path, so a pre-created symlink would pass unnoticed */
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
                    [
                        'destinationDir' => $destinationDir,
                        'backupDirectory' => $backupDirectory,
                        'backupRestored' => false,
                    ],
                );
            }

            throw true === $throwable instanceof ConfGenerateException
                ? $throwable
                : ConfGenerateException::from($throwable, ['destinationDir' => $destinationDir]);
        }
    }

    /**
     * The status of every generated file against what the destination directory currently holds, indexed by path.
     *
     * @throws ConfGenerateException
     */
    public function diff(ConfFilesDto $confFilesDto, string $destinationDir): ConfFileChangesDto
    {
        $confFileChangesDto = new ConfFileChangesDto();

        /* symmetric with `save()`: a writer that would touch nothing must not report a change either */
        if (0 === \count($confFilesDto->getFiles())) {
            return $confFileChangesDto;
        }

        $this->assertRealDestinationDir($destinationDir);

        /* the normalized prefix is also the iteration root below, so an unnormalized `//` cannot spell the same
           file two ways and report it as both current and removed */
        $destinationDirPrefix = \rtrim($destinationDir, '/') . '/';
        $expectedRelativePaths = [];

        foreach ($confFilesDto->getFiles() as $path => $content) {
            if (false === \str_starts_with($path, $destinationDirPrefix)) {
                throw new ConfGenerateException(\sprintf('path `%s` is outside destination directory `%s`', $path, $destinationDir));
            }

            $relativePath = \substr($path, \strlen($destinationDirPrefix));

            if ('' === $relativePath || true === \str_contains($relativePath, '..')) {
                throw new ConfGenerateException(\sprintf('invalid generated path `%s`', $path));
            }

            $expectedRelativePaths[$relativePath] = true;

            if (false === \is_file($path) || true === \is_link($path)) {
                $confFileChangesDto->addChange(new ConfFileChangeDto($path, ConfFileStatus::Added, $content, null));

                continue;
            }

            $currentContent = $this->readFile($path);

            if (false === $currentContent) {
                throw new ConfGenerateException(\sprintf('file `%s` could not be read', $path));
            }

            $confFileChangesDto->addChange(
                new ConfFileChangeDto(
                    $path,
                    $currentContent === $content ? ConfFileStatus::Unchanged : ConfFileStatus::Changed,
                    $content,
                    $currentContent,
                ),
            );
        }

        $this->addRemovedChanges($confFileChangesDto, $destinationDirPrefix, $expectedRelativePaths);

        return $confFileChangesDto->sort();
    }

    /** @throws ConfGenerateException */
    public function initLogsDir(string $logsDir): void
    {
        try {
            $this->filesystem->mkdir($logsDir, 0755);
        } catch (Throwable $throwable) {
            throw new ConfGenerateException(
                \sprintf('logs directory `%s` could not be created — %s', $logsDir, $throwable->getMessage()),
                (int)$throwable->getCode(),
                $throwable,
                ['logsDir' => $logsDir],
            );
        }
    }

    protected function readFile(string $path): string|false
    {
        return \file_get_contents($path);
    }

    /**
     * Activation renames the destination path itself, so a symlink there would be replaced by a real directory
     * rather than followed. Both `save()` and `diff()` refuse it, so a preview cannot pass what a write would break.
     *
     * @throws ConfGenerateException
     */
    protected function assertRealDestinationDir(string $destinationDir): void
    {
        if (true === \is_link($destinationDir)) {
            throw new ConfGenerateException(
                \sprintf('destination directory `%s` is a symlink, and the atomic activation would replace it', $destinationDir),
            );
        }
    }

    /**
     * @param array<string, bool> $expectedRelativePaths
     * @throws ConfGenerateException
     */
    protected function addRemovedChanges(
        ConfFileChangesDto $confFileChangesDto,
        string $destinationDirPrefix,
        array $expectedRelativePaths,
    ): void {
        if (false === \is_dir($destinationDirPrefix)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($destinationDirPrefix, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (false === $fileInfo instanceof SplFileInfo || (false === $fileInfo->isFile() && false === $fileInfo->isLink())) {
                continue;
            }

            $path = $fileInfo->getPathname();
            $relativePath = \substr($path, \strlen($destinationDirPrefix));

            if (false === isset($expectedRelativePaths[$relativePath])) {
                $confFileChangesDto->addChange(new ConfFileChangeDto($path, ConfFileStatus::Removed, null, null));
            }
        }
    }

    /**
     * @return array<int, string>
     * @throws ConfGenerateException
     */
    protected function writeTemporaryFiles(ConfFilesDto $confFilesDto, string $destinationDir, string $temporaryDirectory): array
    {
        $configurationFiles = [];

        /* the trailing separator is what stops `/tmp/conf` matching `/tmp/confAAAA/...` by prefix */
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

            /* the textual checks above pass for a symlink, so the written path is re-checked canonicalized */
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
            /* the caller rethrows the original failure */
            return false;
        }
    }

    protected function silentRemove(string $path): void
    {
        if (true === $this->filesystem->exists($path)) {
            try {
                $this->filesystem->remove($path);
            } catch (Throwable) {
                /* cleanup is non-critical */
            }
        }
    }
}
