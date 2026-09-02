<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example;

use PrecisionSoft\Symfony\Console\Example\Catalogue\ExchangeRateProvider;
use PrecisionSoft\Symfony\Console\Example\Catalogue\ProductRepository;
use PrecisionSoft\Symfony\Console\Example\Command\ExchangeRateRefreshCommand;
use PrecisionSoft\Symfony\Console\Example\Command\PriceListImportCommand;
use PrecisionSoft\Symfony\Console\PrecisionSoftSymfonyConsoleBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

class CatalogueKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new PrecisionSoftSymfonyConsoleBundle()];
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__) . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__) . '/var/log';
    }

    protected function configureContainer(ContainerConfigurator $containerConfigurator): void
    {
        $containerConfigurator->extension('framework', ['secret' => 'product-catalogue', 'test' => true]);

        $containerConfigurator->import(\dirname(__DIR__) . '/config/precision_soft_symfony_console.yaml');
        $containerConfigurator->import(\dirname(__DIR__) . '/config/' . $this->environment . '/precision_soft_symfony_console.yaml');

        $services = $containerConfigurator->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        $services->set(ProductRepository::class);
        $services->set(ExchangeRateProvider::class);
        $services->set(PriceListImportCommand::class);
        $services->set(ExchangeRateRefreshCommand::class);
    }
}
