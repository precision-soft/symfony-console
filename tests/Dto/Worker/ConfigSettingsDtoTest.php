<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Worker;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigSettingsDto;
use PrecisionSoft\Symfony\Console\Exception\SettingNotFoundException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class ConfigSettingsDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(ConfigSettingsDto::class);
    }

    public function testGetSettingForUnknownKeyThrowsException(): void
    {
        $configSettingsDto = new ConfigSettingsDto([]);

        $this->expectException(SettingNotFoundException::class);

        $configSettingsDto->getSetting('non_existent_key');
    }

    public function testGetSettingReturnsValueForExtraKey(): void
    {
        $configSettingsDto = new ConfigSettingsDto(['extra_option' => 'my-value']);

        static::assertSame('my-value', $configSettingsDto->getSetting('extraOption'));
    }

    public function testDefaultsAreNull(): void
    {
        $configSettingsDto = new ConfigSettingsDto([]);

        static::assertNull($configSettingsDto->getNumberOfProcesses());
        static::assertNull($configSettingsDto->getAutoStart());
        static::assertNull($configSettingsDto->getAutoRestart());
        static::assertNull($configSettingsDto->getPrefix());
        static::assertNull($configSettingsDto->getUser());
        static::assertNull($configSettingsDto->getLogFile());
        static::assertNull($configSettingsDto->getDestinationFile());
    }

    public function testAllSettingsAreSet(): void
    {
        $configSettingsDto = new ConfigSettingsDto([
            Configuration::NUMBER_OF_PROCESSES => 3,
            Configuration::AUTO_START => false,
            Configuration::AUTO_RESTART => true,
            Configuration::PREFIX => 'app',
            Configuration::USER => 'root',
            Configuration::LOG_FILE => '/var/log/app.log',
            Configuration::DESTINATION_FILE => 'workers.conf',
        ]);

        static::assertSame(3, $configSettingsDto->getNumberOfProcesses());
        static::assertFalse($configSettingsDto->getAutoStart());
        static::assertTrue($configSettingsDto->getAutoRestart());
        static::assertSame('app', $configSettingsDto->getPrefix());
        static::assertSame('root', $configSettingsDto->getUser());
        static::assertSame('/var/log/app.log', $configSettingsDto->getLogFile());
        static::assertSame('workers.conf', $configSettingsDto->getDestinationFile());
    }
}
