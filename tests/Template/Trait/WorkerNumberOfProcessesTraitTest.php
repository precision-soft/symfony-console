<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template\Trait;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Test\Utility\WorkerNumberOfProcessesTraitObject;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;

/**
 * @internal
 */
final class WorkerNumberOfProcessesTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testGetNumberOfProcessesFromCommand(): void
    {
        $workerNumberOfProcessesTraitObject = new WorkerNumberOfProcessesTraitObject();

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

        $numberOfProcesses = $workerNumberOfProcessesTraitObject->resolveNumberOfProcesses($configDto, $commandDto);

        static::assertSame(5, $numberOfProcesses);
    }

    public function testGetNumberOfProcessesFallsBackToConfig(): void
    {
        $workerNumberOfProcessesTraitObject = new WorkerNumberOfProcessesTraitObject();

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

        $numberOfProcesses = $workerNumberOfProcessesTraitObject->resolveNumberOfProcesses($configDto, $commandDto);

        static::assertSame(3, $numberOfProcesses);
    }

    public function testGetNumberOfProcessesThrowsExceptionWhenNull(): void
    {
        $workerNumberOfProcessesTraitObject = new WorkerNumberOfProcessesTraitObject();

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

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid `number of processes`');

        $workerNumberOfProcessesTraitObject->resolveNumberOfProcesses($configDto, $commandDto);
    }

    public function testGetNumberOfProcessesThrowsExceptionWhenZero(): void
    {
        $workerNumberOfProcessesTraitObject = new WorkerNumberOfProcessesTraitObject();

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

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid `number of processes`');

        $workerNumberOfProcessesTraitObject->resolveNumberOfProcesses($configDto, $commandDto);
    }

    public function testGetNumberOfProcessesThrowsExceptionWhenNegative(): void
    {
        $workerNumberOfProcessesTraitObject = new WorkerNumberOfProcessesTraitObject();

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

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid `number of processes`');

        $workerNumberOfProcessesTraitObject->resolveNumberOfProcesses($configDto, $commandDto);
    }
}
