<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Cronjob;

use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandSettingsDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ScheduleDto;

/**
 * @internal
 */
final class CommandDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CommandDto::class);
    }

    public function testConstructorAndGetters(): void
    {
        $commandDto = new CommandDto(
            'test-command',
            [
                Configuration::COMMAND => ['bin/console', 'app:test'],
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '0',
                    Configuration::HOUR => '12',
                    Configuration::DAY_OF_MONTH => '*',
                    Configuration::MONTH => '*',
                    Configuration::DAY_OF_WEEK => '1-5',
                ],
                Configuration::SETTINGS => [
                    Configuration::LOG => true,
                ],
                Configuration::LOG_FILE_NAME => 'test.log',
                Configuration::USER => 'www-data',
                Configuration::DESTINATION_FILE => 'custom-crontab',
            ],
        );

        static::assertSame('test-command', $commandDto->getName());
        static::assertSame(['bin/console', 'app:test'], $commandDto->getCommand());
        static::assertSame('test.log', $commandDto->getLogFileName());
        static::assertSame('www-data', $commandDto->getUser());
        static::assertSame('custom-crontab', $commandDto->getDestinationFile());
        static::assertInstanceOf(ScheduleDto::class, $commandDto->getSchedule());
        static::assertInstanceOf(CommandSettingsDto::class, $commandDto->getSettings());
    }

    public function testNullableFieldsDefaultToNull(): void
    {
        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '*',
                    Configuration::HOUR => '*',
                    Configuration::DAY_OF_MONTH => '*',
                    Configuration::MONTH => '*',
                    Configuration::DAY_OF_WEEK => '*',
                ],
                Configuration::SETTINGS => [],
            ],
        );

        static::assertNull($commandDto->getLogFileName());
        static::assertNull($commandDto->getUser());
        static::assertNull($commandDto->getDestinationFile());
    }
}
