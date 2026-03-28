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
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
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
            [[]],
            true,
        );
    }

    public function testConstructorStoresTemplatesByClassName(): void
    {
        $firstTemplateMock = Mockery::namedMock('Template1Mock', TemplateInterface::class);
        $secondTemplateMock = Mockery::namedMock('Template2Mock', TemplateInterface::class);

        $confGenerateService = new ConfGenerateService([$firstTemplateMock, $secondTemplateMock]);

        $reflectionClass = new ReflectionClass($confGenerateService);
        $reflectionProperty = $reflectionClass->getProperty('templates');
        $reflectionProperty->setAccessible(true);

        $templates = $reflectionProperty->getValue($confGenerateService);

        static::assertCount(2, $templates);
        static::assertArrayHasKey('Template1Mock', $templates);
        static::assertArrayHasKey('Template2Mock', $templates);
    }

    public function testGetTemplateThrowsExceptionWhenTemplateNotFound(): void
    {
        $confGenerateService = new ConfGenerateService([]);

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn('NonExistentTemplate');
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn(\sys_get_temp_dir() . '/test_logs');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('the template `NonExistentTemplate` does not exist');

        $confGenerateService->generate($configInterfaceMock, []);
    }

    public function testGenerateCallsTemplateAndSavesFiles(): void
    {
        $temporaryDirectory = \sys_get_temp_dir() . '/test_conf_generate_' . \uniqid('', true);

        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile($temporaryDirectory . '/test.conf', 'test content');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')
            ->once()
            ->andReturn($confFilesDto);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock]);

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn(\sys_get_temp_dir() . '/test_logs_' . \uniqid('', true));
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        $result = $confGenerateService->generate($configInterfaceMock, []);

        static::assertCount(1, $result);
        static::assertSame($temporaryDirectory . '/test.conf', $result[0]);

        $filesystem = new Filesystem();
        if (true === $filesystem->exists($temporaryDirectory)) {
            $filesystem->remove($temporaryDirectory);
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

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock]);

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        $confGenerateService->generate($configInterfaceMock, []);

        static::assertDirectoryExists($logsDirectory);

        $filesystem = new Filesystem();
        $filesystem->remove([$temporaryDirectory, $logsDirectory]);
    }
}
