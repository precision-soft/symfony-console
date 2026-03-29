<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CronjobCreateCommand::class)
        ->arg('$confGenerateService', new Reference(ConfGenerateService::class))
        ->arg('$config', '%precision_soft_symfony_console.cronjob%')
        ->tag('console.command');

    $services->set(WorkerCreateCommand::class)
        ->arg('$confGenerateService', new Reference(ConfGenerateService::class))
        ->arg('$config', '%precision_soft_symfony_console.worker%')
        ->tag('console.command');

    $services->set(ConfGenerateService::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$templates', new TaggedIteratorArgument(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE));

    $services->set(CrontabTemplate::class)
        ->tag(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE);

    $services->set(SupervisorTemplate::class)
        ->tag(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE);

    $services->set(KubernetesCronjobTemplate::class)
        ->tag(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE);

    $services->set(KubernetesWorkerTemplate::class)
        ->tag(PrecisionSoftSymfonyConsoleExtension::CONSOLE_TEMPLATE);
};
