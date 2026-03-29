<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\DependencyInjection;

use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
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

    public function testConstConsoleTemplate(): void
    {
        static::assertSame('precision-soft.symfony.console.template', PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE);
    }
}
