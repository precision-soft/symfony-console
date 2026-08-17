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
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use TypeError;

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

    public function testGenerateWrapsTemplateExceptionsInConfGenerateException(): void
    {
        $temporaryDirectory = \sys_get_temp_dir() . '/test_conf_generate_wrap_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/test_logs_wrap_' . \uniqid('', true);

        $invalidValueException = new InvalidValueException('the file path is in use `worker.blue.conf`');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andThrow($invalidValueException);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter(new Filesystem()));

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        try {
            $confGenerateService->generate($configInterfaceMock, []);

            static::fail(\sprintf('expected a %s', ConfGenerateException::class));
        } catch (ConfGenerateException $confGenerateException) {
            static::assertSame('the file path is in use `worker.blue.conf`', $confGenerateException->getMessage());
            static::assertSame($invalidValueException, $confGenerateException->getPrevious());

            static::assertSame(
                [
                    'templateClass' => $templateInterfaceMock::class,
                    'confFilesDir' => $temporaryDirectory,
                ],
                $confGenerateException->getContext(),
            );
        } finally {
            $filesystem = new Filesystem();
            $filesystem->remove([$temporaryDirectory, $logsDirectory]);
        }
    }

    public function testGenerateDoesNotWrapProgrammingErrors(): void
    {
        $temporaryDirectory = \sys_get_temp_dir() . '/test_conf_generate_error_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/test_logs_error_' . \uniqid('', true);

        $typeError = new TypeError('argument #1 must be of type string, int given');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andThrow($typeError);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter(new Filesystem()));

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        try {
            $confGenerateService->generate($configInterfaceMock, []);

            static::fail(\sprintf('expected a %s', TypeError::class));
        } catch (TypeError $caughtTypeError) {
            static::assertSame($typeError, $caughtTypeError);
        } finally {
            $filesystem = new Filesystem();
            $filesystem->remove([$temporaryDirectory, $logsDirectory]);
        }
    }

    public function testGenerateDoesNotRewrapConfGenerateException(): void
    {
        $temporaryDirectory = \sys_get_temp_dir() . '/test_conf_generate_norewrap_' . \uniqid('', true);
        $logsDirectory = \sys_get_temp_dir() . '/test_logs_norewrap_' . \uniqid('', true);

        $confGenerateException = new ConfGenerateException('already wrapped');

        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')->once()->andThrow($confGenerateException);

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter(new Filesystem()));

        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateInterfaceMock::class);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($temporaryDirectory);

        try {
            $confGenerateService->generate($configInterfaceMock, []);

            static::fail(\sprintf('expected a %s', ConfGenerateException::class));
        } catch (ConfGenerateException $caughtException) {
            static::assertSame($confGenerateException, $caughtException);
            static::assertNull($caughtException->getPrevious());
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
