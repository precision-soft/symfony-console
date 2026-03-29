<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Worker;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandSettingsDto;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class CommandSettingsDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CommandSettingsDto::class);
    }

    public function testDefaultsAreNull(): void
    {
        $commandSettingsDto = new CommandSettingsDto([]);

        static::assertNull($commandSettingsDto->getNumberOfProcesses());
        static::assertNull($commandSettingsDto->getAutoStart());
        static::assertNull($commandSettingsDto->getAutoRestart());
        static::assertNull($commandSettingsDto->getPrefix());
        static::assertNull($commandSettingsDto->getUser());
        static::assertNull($commandSettingsDto->getLogFile());
    }

    public function testAllSupervisorSettingsAreSet(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            Configuration::NUMBER_OF_PROCESSES => 5,
            Configuration::AUTO_START => true,
            Configuration::AUTO_RESTART => false,
            Configuration::PREFIX => 'my-prefix',
            Configuration::USER => 'www-data',
            Configuration::LOG_FILE => '/var/log/worker.log',
        ]);

        static::assertSame(5, $commandSettingsDto->getNumberOfProcesses());
        static::assertTrue($commandSettingsDto->getAutoStart());
        static::assertFalse($commandSettingsDto->getAutoRestart());
        static::assertSame('my-prefix', $commandSettingsDto->getPrefix());
        static::assertSame('www-data', $commandSettingsDto->getUser());
        static::assertSame('/var/log/worker.log', $commandSettingsDto->getLogFile());
    }

    public function testExtraSettingsAreAccessibleViaSetting(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            'custom_setting' => 'custom-value',
        ]);

        static::assertSame('custom-value', $commandSettingsDto->getSetting('customSetting'));
    }
}
