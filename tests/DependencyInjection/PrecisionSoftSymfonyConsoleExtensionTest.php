<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\DependencyInjection;

use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\LogsDirCreateCommand;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
final class PrecisionSoftSymfonyConsoleExtensionTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(PrecisionSoftSymfonyConsoleExtension::class);
    }

    public function testLoadRegistersServices(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $precisionSoftSymfonyConsoleExtension->load([], $containerBuilder);

        static::assertTrue($containerBuilder->hasDefinition(CronjobCreateCommand::class));
        static::assertTrue($containerBuilder->hasDefinition(WorkerCreateCommand::class));
        static::assertTrue($containerBuilder->hasDefinition(LogsDirCreateCommand::class));
        static::assertTrue($containerBuilder->hasDefinition(ConfFileWriter::class));
        static::assertTrue($containerBuilder->hasDefinition(ConfGenerateService::class));
        static::assertTrue($containerBuilder->hasDefinition(CrontabTemplate::class));
        static::assertTrue($containerBuilder->hasDefinition(SupervisorTemplate::class));
        static::assertTrue($containerBuilder->hasDefinition(KubernetesCronjobTemplate::class));
        static::assertTrue($containerBuilder->hasDefinition(KubernetesWorkerTemplate::class));
    }

    public function testLoadSetsParameters(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $precisionSoftSymfonyConsoleExtension->load([
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ], $containerBuilder);

        static::assertTrue($containerBuilder->hasParameter('precision_soft_symfony_console.cronjob'));
        static::assertTrue($containerBuilder->hasParameter('precision_soft_symfony_console.worker'));

        $cronjobParameter = $containerBuilder->getParameter('precision_soft_symfony_console.cronjob');
        $workerParameter = $containerBuilder->getParameter('precision_soft_symfony_console.worker');

        static::assertIsArray($cronjobParameter);
        static::assertIsArray($workerParameter);
        static::assertArrayHasKey(Configuration::COMMANDS, $cronjobParameter);
        static::assertArrayHasKey(Configuration::COMMANDS, $workerParameter);
    }

    public function testLoadWithEmptyConfigSetsDefaultParameters(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $precisionSoftSymfonyConsoleExtension->load([], $containerBuilder);

        static::assertTrue($containerBuilder->hasParameter('precision_soft_symfony_console.cronjob'));
        static::assertTrue($containerBuilder->hasParameter('precision_soft_symfony_console.worker'));

        $cronjobParameter = $containerBuilder->getParameter('precision_soft_symfony_console.cronjob');
        $workerParameter = $containerBuilder->getParameter('precision_soft_symfony_console.worker');

        static::assertIsArray($cronjobParameter);
        static::assertIsArray($workerParameter);
        static::assertArrayHasKey(Configuration::CONFIG, $cronjobParameter);
        static::assertArrayHasKey(Configuration::CONFIG, $workerParameter);
    }

    public function testLoadWithEmptyConfigSetsDefaultLogsDirs(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $precisionSoftSymfonyConsoleExtension->load([], $containerBuilder);

        static::assertTrue($containerBuilder->hasParameter('precision_soft_symfony_console.logs_dirs'));

        static::assertSame(
            ['%kernel.logs_dir%/cron', '%kernel.logs_dir%/worker'],
            $containerBuilder->getParameter('precision_soft_symfony_console.logs_dirs'),
        );
    }

    public function testLoadDerivesLogsDirsFromCronjobWorkerAndExtras(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $precisionSoftSymfonyConsoleExtension->load([
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::LOGS_DIR => '/tmp/crontab',
                    ],
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::LOGS_DIRS => ['/tmp/command'],
            ],
        ], $containerBuilder);

        static::assertSame(
            ['/tmp/crontab', '%kernel.logs_dir%/worker', '/tmp/command'],
            $containerBuilder->getParameter('precision_soft_symfony_console.logs_dirs'),
        );
    }

    public function testLoadDeduplicatesLogsDirs(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $precisionSoftSymfonyConsoleExtension->load([
            'precision_soft_symfony_console' => [
                Configuration::LOGS_DIRS => ['%kernel.logs_dir%/cron', '/tmp/command'],
            ],
        ], $containerBuilder);

        static::assertSame(
            ['%kernel.logs_dir%/cron', '%kernel.logs_dir%/worker', '/tmp/command'],
            $containerBuilder->getParameter('precision_soft_symfony_console.logs_dirs'),
        );
    }

    public function testLoadRejectsNonStringLogsDirsWithANamedConfigurationError(): void
    {
        $containerBuilder = new ContainerBuilder();
        $precisionSoftSymfonyConsoleExtension = new PrecisionSoftSymfonyConsoleExtension();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#logs_dirs#');

        $precisionSoftSymfonyConsoleExtension->load([
            'precision_soft_symfony_console' => [
                Configuration::LOGS_DIRS => [123],
            ],
        ], $containerBuilder);
    }

    public function testConstConsoleTemplate(): void
    {
        static::assertSame('precision-soft.symfony.console.template', PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE);
    }
}
