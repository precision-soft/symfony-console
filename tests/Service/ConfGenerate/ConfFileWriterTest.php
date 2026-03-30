<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service\ConfGenerate;

use Mockery;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ConfFileWriterTest extends AbstractTestCase
{
    private Filesystem $filesystem;
    private ConfFileWriter $confFileWriter;

    public static function getMockDto(): MockDto
    {
        return new MockDto(
            ConfFileWriter::class,
            [new Filesystem()],
            true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->confFileWriter = new ConfFileWriter($this->filesystem);
    }

    public function testSaveCreatesFilesInDestinationDir(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_save_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/test.conf', 'test content');

        try {
            $configurationFiles = $this->confFileWriter->save($confFilesDto, $destinationDirectory);

            static::assertCount(1, $configurationFiles);
            static::assertSame($destinationDirectory . '/test.conf', $configurationFiles[0]);
            static::assertFileExists($destinationDirectory . '/test.conf');
            static::assertSame('test content', \file_get_contents($destinationDirectory . '/test.conf'));
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }

    public function testSaveReplacesExistingDestinationDir(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_replace_' . \uniqid('', true);

        $this->filesystem->mkdir($destinationDirectory, 0755);
        $this->filesystem->dumpFile($destinationDirectory . '/old.conf', 'old content');

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/new.conf', 'new content');

        try {
            $configurationFiles = $this->confFileWriter->save($confFilesDto, $destinationDirectory);

            static::assertCount(1, $configurationFiles);
            static::assertFileExists($destinationDirectory . '/new.conf');
            static::assertSame('new content', \file_get_contents($destinationDirectory . '/new.conf'));
            static::assertFileDoesNotExist($destinationDirectory . '/old.conf');
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }

    public function testSaveWithEmptyConfFilesDtoReturnsEmptyArray(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_empty_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();

        try {
            $configurationFiles = $this->confFileWriter->save($confFilesDto, $destinationDirectory);

            static::assertSame([], $configurationFiles);
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }

    public function testSaveThrowsOnPathOutsideDestination(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_outside_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile('/tmp/other_dir/malicious.conf', 'content');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('is outside destination directory');

        try {
            $this->confFileWriter->save($confFilesDto, $destinationDirectory);
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }

    public function testSaveThrowsOnPathTraversal(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_traversal_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/../etc/passwd', 'content');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('path traversal detected');

        try {
            $this->confFileWriter->save($confFilesDto, $destinationDirectory);
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }

    public function testSaveWithMultipleFiles(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_multi_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/first.conf', 'first');
        $confFilesDto->addFile($destinationDirectory . '/second.conf', 'second');

        try {
            $configurationFiles = $this->confFileWriter->save($confFilesDto, $destinationDirectory);

            static::assertCount(2, $configurationFiles);
            static::assertSame('first', \file_get_contents($destinationDirectory . '/first.conf'));
            static::assertSame('second', \file_get_contents($destinationDirectory . '/second.conf'));
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }

    public function testInitLogsDirCreatesDirectory(): void
    {
        $logsDirectory = \sys_get_temp_dir() . '/cfw_logs_' . \uniqid('', true);

        static::assertDirectoryDoesNotExist($logsDirectory);

        try {
            $this->confFileWriter->initLogsDir($logsDirectory);

            static::assertDirectoryExists($logsDirectory);
        } finally {
            $this->filesystem->remove($logsDirectory);
        }
    }

    public function testInitLogsDirDoesNotFailIfAlreadyExists(): void
    {
        $logsDirectory = \sys_get_temp_dir() . '/cfw_logs_exist_' . \uniqid('', true);
        $this->filesystem->mkdir($logsDirectory, 0755);

        try {
            $this->confFileWriter->initLogsDir($logsDirectory);

            static::assertDirectoryExists($logsDirectory);
        } finally {
            $this->filesystem->remove($logsDirectory);
        }
    }

    public function testSavePreservesBackupWhenRestoreFails(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_preserve_backup_' . \uniqid('', true);

        $this->filesystem->mkdir($destinationDirectory, 0755);
        $this->filesystem->dumpFile($destinationDirectory . '/original.conf', 'original content');

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/new.conf', 'new content');

        $renameCallCount = 0;
        $filesystemMock = Mockery::mock(Filesystem::class);
        $filesystemMock->shouldReceive('mkdir')->andReturnUsing(function (string $dir, int $mode): void {
            (new Filesystem())->mkdir($dir, $mode);
        });
        $filesystemMock->shouldReceive('dumpFile')->andReturnUsing(function (string $path, string $content): void {
            (new Filesystem())->dumpFile($path, $content);
        });
        $filesystemMock->shouldReceive('exists')->andReturnUsing(function (string $path): bool {
            return (new Filesystem())->exists($path);
        });
        $filesystemMock->shouldReceive('rename')->andReturnUsing(function (string $origin, string $target) use (&$renameCallCount): void {
            $renameCallCount++;

            /** @info first rename: destinationDir → backupDir (succeeds) */
            if (1 === $renameCallCount) {
                (new Filesystem())->rename($origin, $target);

                return;
            }

            /** @info second rename: tempDir → destinationDir (fails) */
            if (2 === $renameCallCount) {
                throw new IOException('deploy rename failed');
            }

            /** @info third rename: backupDir → destinationDir (restore also fails) */
            throw new IOException('restore rename failed');
        });
        $filesystemMock->shouldReceive('remove')->andReturnUsing(function ($paths): void {
            (new Filesystem())->remove($paths);
        });

        $confFileWriter = new ConfFileWriter($filesystemMock);

        $confGenerateException = null;

        try {
            $confFileWriter->save($confFilesDto, $destinationDirectory);
        } catch (ConfGenerateException $confGenerateException) {
            static::assertStringContainsString('backup preserved at', $confGenerateException->getMessage());
        }

        static::assertInstanceOf(ConfGenerateException::class, $confGenerateException);

        /** @info verify backup was NOT deleted */
        $backupDirectories = \glob($destinationDirectory . '.bak_*');
        static::assertCount(1, $backupDirectories);
        static::assertFileExists($backupDirectories[0] . '/original.conf');
        static::assertSame('original content', \file_get_contents($backupDirectories[0] . '/original.conf'));

        $this->filesystem->remove($backupDirectories[0]);
    }

    public function testSaveRemovesBackupAfterSuccessfulDeploy(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/cfw_backup_cleanup_' . \uniqid('', true);

        $this->filesystem->mkdir($destinationDirectory, 0755);
        $this->filesystem->dumpFile($destinationDirectory . '/old.conf', 'old');

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/new.conf', 'new');

        try {
            $this->confFileWriter->save($confFilesDto, $destinationDirectory);

            $backupDirs = \glob($destinationDirectory . '.bak_*');
            static::assertSame([], $backupDirs);
        } finally {
            $this->filesystem->remove($destinationDirectory);
        }
    }
}
