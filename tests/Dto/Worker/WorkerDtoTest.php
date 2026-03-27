<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Worker;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\WorkerDto;

/**
 * @internal
 */
final class WorkerDtoTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $workerDto = new WorkerDto([
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'conf',
                Configuration::LOGS_DIR => 'logs',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
            Configuration::COMMANDS => [
                'test-worker' => [
                    Configuration::COMMAND => ['bin/console', 'test'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 2,
                    ],
                ],
            ],
        ]);

        static::assertInstanceOf(ConfigDto::class, $workerDto->getConfig());
        static::assertCount(1, $workerDto->getCommands());
        static::assertArrayHasKey('test-worker', $workerDto->getCommands());
        static::assertInstanceOf(CommandDto::class, $workerDto->getCommands()['test-worker']);
    }

    public function testMultipleCommands(): void
    {
        $workerDto = new WorkerDto([
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'conf',
                Configuration::LOGS_DIR => 'logs',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                ],
            ],
            Configuration::COMMANDS => [
                'worker-one' => [
                    Configuration::COMMAND => ['one'],
                    Configuration::SETTINGS => [],
                ],
                'worker-two' => [
                    Configuration::COMMAND => ['two'],
                    Configuration::SETTINGS => [],
                ],
            ],
        ]);

        static::assertCount(2, $workerDto->getCommands());
    }

    public function testEmptyCommands(): void
    {
        $workerDto = new WorkerDto([
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'conf',
                Configuration::LOGS_DIR => 'logs',
                Configuration::SETTINGS => [],
            ],
            Configuration::COMMANDS => [],
        ]);

        static::assertCount(0, $workerDto->getCommands());
    }
}
