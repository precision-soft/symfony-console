<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->load(
        'PrecisionSoft\\Symfony\\Console\\Service\\ConfGenerate\\',
        __DIR__ . '/../../Service/ConfGenerate/*',
    )
        ->autowire()
        ->autoconfigure();

    $services->set(ConfGenerateService::class)
        ->arg('$templates', new TaggedIteratorArgument(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE))
        ->autowire()
        ->autoconfigure();

    $services->load(
        'PrecisionSoft\\Symfony\\Console\\Template\\',
        __DIR__ . '/../../Template/*',
    )
        ->tag(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE)
        ->autowire()
        ->autoconfigure();

    $services->set(CronjobCreateCommand::class)
        ->arg('$confGenerateService', new Reference(ConfGenerateService::class))
        ->arg('$cronjobConfiguration', '%precision_soft_symfony_console.cronjob%')
        ->tag('console.command')
        ->autowire()
        ->autoconfigure();

    $services->set(WorkerCreateCommand::class)
        ->arg('$confGenerateService', new Reference(ConfGenerateService::class))
        ->arg('$workerConfiguration', '%precision_soft_symfony_console.worker%')
        ->tag('console.command')
        ->autowire()
        ->autoconfigure();
};
