<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service;

use Mockery;
use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ConfGenerateServiceErrorRecoveryTest extends AbstractTestCase
{
    private Filesystem $filesystem;

    public static function getMockDto(): MockDto
    {
        return new MockDto(
            ConfGenerateService::class,
            [[], new Filesystem()],
            true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
    }

    public function testGenerateWithNonExistentTemplateThrowsException(): void
    {
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_' . \uniqid('', true);

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn('NonExistentTemplateClass');
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('the template `NonExistentTemplateClass` does not exist');

        try {
            $confGenerateService->generate($configInterfaceMock, []);
        } finally {
            if (true === $this->filesystem->exists($logsDirectory)) {
                $this->filesystem->remove($logsDirectory);
            }
        }
    }

    public function testGenerateReplacesExistingDestinationDir(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_dest_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_' . \uniqid('', true);

        $this->filesystem->mkdir($destinationDirectory, 0755);
        $this->filesystem->dumpFile($destinationDirectory . '/old_file.conf', 'old content');

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/new_file.conf', 'new content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $generatedFiles = $confGenerateService->generate($configInterfaceMock, []);

        static::assertCount(1, $generatedFiles);
        static::assertSame($destinationDirectory . '/new_file.conf', $generatedFiles[0]);
        static::assertFileExists($destinationDirectory . '/new_file.conf');
        static::assertSame('new content', \file_get_contents($destinationDirectory . '/new_file.conf'));
        static::assertFileDoesNotExist($destinationDirectory . '/old_file.conf');

        $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
    }

    public function testGenerateWithEmptyCommandsProducesNoFiles(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_empty_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_empty_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $generatedFiles = $confGenerateService->generate($configInterfaceMock, []);

        static::assertSame([], $generatedFiles);

        $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
    }

    public function testGenerateCreatesLogsDirWhenNotExists(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_logsdir_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_newlogs_' . \uniqid('', true);

        static::assertDirectoryDoesNotExist($logsDirectory);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/file.conf', 'content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $confGenerateService->generate($configInterfaceMock, []);

        static::assertDirectoryExists($logsDirectory);

        $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
    }

    public function testPathOutsideDestinationDirThrowsException(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_outside_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_outside_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile('/tmp/other_directory/malicious.conf', 'content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('is outside destination directory');

        try {
            $confGenerateService->generate($configInterfaceMock, []);
        } finally {
            $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
        }
    }

    public function testPathTraversalDetectedThrowsException(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_traversal_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_traversal_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/../etc/passwd', 'content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('path traversal detected');

        try {
            $confGenerateService->generate($configInterfaceMock, []);
        } finally {
            $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
        }
    }

    public function testRenameFailureRestoresBackup(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_rename_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_rename_' . \uniqid('', true);

        $this->filesystem->mkdir($destinationDirectory, 0755);
        $this->filesystem->dumpFile($destinationDirectory . '/original.conf', 'original content');

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/new.conf', 'new content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

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
        $filesystemMock->shouldReceive('rename')->once()->andReturnUsing(function (string $origin, string $target): void {
            (new Filesystem())->rename($origin, $target);
        });
        $filesystemMock->shouldReceive('rename')->andThrow(new IOException('rename failed'));
        $filesystemMock->shouldReceive('remove')->andReturnUsing(function ($paths): void {
            (new Filesystem())->remove($paths);
        });

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], $filesystemMock);

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('rename failed');

        try {
            $confGenerateService->generate($configInterfaceMock, []);
        } finally {
            $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
        }
    }

    public function testGenerateWithMultipleFilesCreatesAllFiles(): void
    {
        $destinationDirectory = \sys_get_temp_dir() . '/error_recovery_multi_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/error_recovery_logs_multi_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($destinationDirectory . '/first.conf', 'first content');
        $confFilesDto->addFile($destinationDirectory . '/second.conf', 'second content');
        $confFilesDto->addFile($destinationDirectory . '/third.conf', 'third content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new Filesystem());

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($destinationDirectory);

        $generatedFiles = $confGenerateService->generate($configInterfaceMock, []);

        static::assertCount(3, $generatedFiles);
        static::assertSame($destinationDirectory . '/first.conf', $generatedFiles[0]);
        static::assertSame($destinationDirectory . '/second.conf', $generatedFiles[1]);
        static::assertSame($destinationDirectory . '/third.conf', $generatedFiles[2]);

        static::assertSame('first content', \file_get_contents($destinationDirectory . '/first.conf'));
        static::assertSame('second content', \file_get_contents($destinationDirectory . '/second.conf'));
        static::assertSame('third content', \file_get_contents($destinationDirectory . '/third.conf'));

        $this->filesystem->remove([$destinationDirectory, $logsDirectory]);
    }
}
