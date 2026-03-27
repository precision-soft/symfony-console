<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template\Trait;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;
use ReflectionMethod;

/**
 * @internal
 */
final class WorkerNumberOfProcessesTraitTest extends TestCase
{
    public function testGetNumberOfProcessesFromCommand(): void
    {
        $object = $this->createTraitObject();

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [
                Configuration::NUMBER_OF_PROCESSES => 1,
            ],
        ]);

        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 5,
                ],
            ],
        );

        $result = $this->callMethod($object, 'getNumberOfProcesses', [$configDto, $commandDto]);

        static::assertSame(5, $result);
    }

    public function testGetNumberOfProcessesFallsBackToConfig(): void
    {
        $object = $this->createTraitObject();

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [
                Configuration::NUMBER_OF_PROCESSES => 3,
            ],
        ]);

        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
                Configuration::SETTINGS => [],
            ],
        );

        $result = $this->callMethod($object, 'getNumberOfProcesses', [$configDto, $commandDto]);

        static::assertSame(3, $result);
    }

    public function testGetNumberOfProcessesThrowsExceptionWhenNull(): void
    {
        $object = $this->createTraitObject();

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [],
        ]);

        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
                Configuration::SETTINGS => [],
            ],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid `number of processes`');

        $this->callMethod($object, 'getNumberOfProcesses', [$configDto, $commandDto]);
    }

    public function testGetNumberOfProcessesThrowsExceptionWhenZero(): void
    {
        $object = $this->createTraitObject();

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [
                Configuration::NUMBER_OF_PROCESSES => 0,
            ],
        ]);

        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 0,
                ],
            ],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid `number of processes`');

        $this->callMethod($object, 'getNumberOfProcesses', [$configDto, $commandDto]);
    }

    public function testGetNumberOfProcessesThrowsExceptionWhenNegative(): void
    {
        $object = $this->createTraitObject();

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [
                Configuration::NUMBER_OF_PROCESSES => -1,
            ],
        ]);

        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => -1,
                ],
            ],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid `number of processes`');

        $this->callMethod($object, 'getNumberOfProcesses', [$configDto, $commandDto]);
    }

    private function createTraitObject(): object
    {
        return new class {
            use WorkerNumberOfProcessesTrait;
        };
    }

    private function callMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
