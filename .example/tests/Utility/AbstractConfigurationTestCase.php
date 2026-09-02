<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Utility;

use PrecisionSoft\Symfony\Console\Example\Catalogue\ExchangeRateProvider;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/* boots the catalogue kernel in the environment that selects the templates, and runs the bundle's commands through it */
abstract class AbstractConfigurationTestCase extends AbstractKernelTestCase
{
    protected const ENVIRONMENT = 'test';

    protected Filesystem $filesystem;

    public static function getMockDto(): MockDto
    {
        return new MockDto(ExchangeRateProvider::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->filesystem->remove([$this->getGeneratedConfigurationDir(), $this->getProjectDir() . '/var/cache/' . static::ENVIRONMENT]);

        /* without debug the framework does not dump `config/reference.php` next to the tracked configuration; the cache
           removed above makes every test compile the container from the configuration as it stands */
        static::bootKernel(['environment' => static::ENVIRONMENT, 'debug' => false]);
    }

    /** @param array<string, mixed> $input */
    protected function runCommand(string $name, array $input = []): CommandTester
    {
        $kernel = static::$kernel;

        static::assertNotNull($kernel);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $commandTester = new CommandTester($application->find($name));
        $commandTester->execute($input);

        return $commandTester;
    }

    protected function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    protected function getLogsDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    protected function getGeneratedConfigurationDir(): string
    {
        return $this->getProjectDir() . '/var/generated_conf/' . static::ENVIRONMENT;
    }

    protected function getConsolePath(): string
    {
        return $this->getProjectDir() . '/bin/console';
    }

    protected function readFile(string $path): string
    {
        static::assertFileExists($path);

        $content = \file_get_contents($path);

        static::assertIsString($content);

        return $content;
    }
}
