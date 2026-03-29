<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CronjobDto;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class CronjobDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CronjobDto::class);
    }

    public function testConstructorAndGetters(): void
    {
        $cronjobDto = new CronjobDto([
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'conf',
                Configuration::LOGS_DIR => 'logs',
                Configuration::SETTINGS => [
                    Configuration::LOG => true,
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                ],
            ],
            Configuration::COMMANDS => [
                'test-job' => [
                    Configuration::COMMAND => ['bin/console', 'test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => true,
                    ],
                ],
            ],
        ]);

        static::assertInstanceOf(ConfigDto::class, $cronjobDto->getConfig());
        static::assertCount(1, $cronjobDto->getCommands());
        static::assertArrayHasKey('test-job', $cronjobDto->getCommands());
        static::assertInstanceOf(CommandDto::class, $cronjobDto->getCommands()['test-job']);
    }

    public function testMultipleCommands(): void
    {
        $cronjobDto = new CronjobDto([
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'conf',
                Configuration::LOGS_DIR => 'logs',
                Configuration::SETTINGS => [
                    Configuration::LOG => true,
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                ],
            ],
            Configuration::COMMANDS => [
                'job-one' => [
                    Configuration::COMMAND => ['one'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [],
                ],
                'job-two' => [
                    Configuration::COMMAND => ['two'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '0',
                        Configuration::HOUR => '0',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [],
                ],
            ],
        ]);

        static::assertCount(2, $cronjobDto->getCommands());
    }

    public function testEmptyCommands(): void
    {
        $cronjobDto = new CronjobDto([
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'conf',
                Configuration::LOGS_DIR => 'logs',
                Configuration::SETTINGS => [
                    Configuration::LOG => true,
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                ],
            ],
            Configuration::COMMANDS => [],
        ]);

        static::assertCount(0, $cronjobDto->getCommands());
    }
}
