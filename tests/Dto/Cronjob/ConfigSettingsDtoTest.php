<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Cronjob;

use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigSettingsDto;

/**
 * @internal
 */
final class ConfigSettingsDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(ConfigSettingsDto::class);
    }

    public function testAllGetters(): void
    {
        $configSettingsDto = new ConfigSettingsDto([
            Configuration::LOG => true,
            Configuration::DESTINATION_FILE => 'my-crontab',
            Configuration::HEARTBEAT => false,
            Configuration::USER => 'www-data',
        ]);

        static::assertTrue($configSettingsDto->getLog());
        static::assertSame('my-crontab', $configSettingsDto->getDestinationFile());
        static::assertFalse($configSettingsDto->getHeartbeat());
        static::assertSame('www-data', $configSettingsDto->getUser());
    }

    public function testUserDefaultsToNull(): void
    {
        $configSettingsDto = new ConfigSettingsDto([
            Configuration::LOG => true,
            Configuration::DESTINATION_FILE => 'crontab',
            Configuration::HEARTBEAT => true,
        ]);

        static::assertNull($configSettingsDto->getUser());
    }
}
