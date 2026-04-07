<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service\ConfGenerate;

use Mockery;
use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ConfGenerateServiceTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(
            ConfGenerateService::class,
            [[], new ConfFileWriter(new Filesystem())],
            true,
        );
    }

    public function testConstructorStoresTemplatesByClassName(): void
    {
        $crontabTemplateMock = Mockery::namedMock('CrontabTemplateMock', TemplateInterface::class);
        $supervisorTemplateMock = Mockery::namedMock('SupervisorTemplateMock', TemplateInterface::class);

        $confGenerateService = new ConfGenerateService([$crontabTemplateMock, $supervisorTemplateMock], new ConfFileWriter(new Filesystem()));

        $reflectionClass = new ReflectionClass($confGenerateService);
        $reflectionProperty = $reflectionClass->getProperty('templates');

        $templates = $reflectionProperty->getValue($confGenerateService);

        static::assertCount(2, $templates);
        static::assertArrayHasKey('CrontabTemplateMock', $templates);
        static::assertArrayHasKey('SupervisorTemplateMock', $templates);
    }

    public function testGetTemplateThrowsExceptionWhenTemplateNotFound(): void
    {
        $confGenerateService = new ConfGenerateService([], new ConfFileWriter(new Filesystem()));

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn('NonExistentTemplate');
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn(\sys_get_temp_dir() . '/test_logs');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('the template `NonExistentTemplate` does not exist');

        $confGenerateService->generate($configInterfaceMock, []);
    }

    public function testGenerateCallsTemplateAndSavesFiles(): void
    {
        $temporaryDirectory = \sys_get_temp_dir() . '/test_conf_generate_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/test_logs_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($temporaryDirectory . '/test.conf', 'test content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')
            ->once()
            ->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter(new Filesystem()));

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        try {
            $generatedFiles = $confGenerateService->generate($configInterfaceMock, []);

            static::assertCount(1, $generatedFiles);
            static::assertSame($temporaryDirectory . '/test.conf', $generatedFiles[0]);
        } finally {
            $filesystem = new Filesystem();
            $filesystem->remove([$temporaryDirectory, $logsDirectory]);
        }
    }

    public function testGenerateCreatesLogsDirectory(): void
    {
        $temporaryDirectory = \sys_get_temp_dir() . '/test_conf_generate_logs_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/test_logs_dir_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($temporaryDirectory . '/test.conf', 'content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter(new Filesystem()));

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        try {
            $confGenerateService->generate($configInterfaceMock, []);

            static::assertDirectoryExists($logsDirectory);
        } finally {
            $filesystem = new Filesystem();
            $filesystem->remove([$temporaryDirectory, $logsDirectory]);
        }
    }
}
