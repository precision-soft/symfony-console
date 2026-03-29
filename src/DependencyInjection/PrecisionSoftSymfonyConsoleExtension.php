<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PrecisionSoftSymfonyConsoleExtension extends Extension
{
    public const CONSOLE_TEMPLATE = 'precision-soft.symfony.console.template';

    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));
        $phpFileLoader->load('services.php');

        $configuration = new Configuration();
        $processedConfiguration = $this->processConfiguration($configuration, $configs);

        $containerBuilder->setParameter('precision_soft_symfony_console.cronjob', $processedConfiguration[Configuration::CRONJOB] ?? null);
        $containerBuilder->setParameter('precision_soft_symfony_console.worker', $processedConfiguration[Configuration::WORKER] ?? null);
    }
}
