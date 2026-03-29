<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Trait;

use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandSettingsDto;
use PrecisionSoft\Symfony\Console\Exception\SettingNotFound;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class SettingsTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CommandSettingsDto::class);
    }

    public function testGetSettingReturnsValueAsString(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            'custom_key' => 42,
        ]);

        static::assertSame('42', $commandSettingsDto->getSetting('customKey'));
    }

    public function testGetSettingReturnsNullForNullValue(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            'custom_key' => null,
        ]);

        static::assertNull($commandSettingsDto->getSetting('customKey'));
    }

    public function testGetSettingThrowsExceptionForNonExistentSetting(): void
    {
        $commandSettingsDto = new CommandSettingsDto([]);

        $this->expectException(SettingNotFound::class);
        $this->expectExceptionMessage('the setting `nonExistent` is not set for');

        $commandSettingsDto->getSetting('nonExistent');
    }

    public function testToCamelCaseConversion(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            'some_long_setting_name' => 'value',
        ]);

        static::assertSame('value', $commandSettingsDto->getSetting('someLongSettingName'));
    }

    public function testLoadPropertiesMapsToExistingProperties(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            'log' => true,
        ]);

        static::assertTrue($commandSettingsDto->getLog());
    }

    public function testLoadPropertiesStoresUnknownInSettings(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            'unknown_property' => 'stored',
        ]);

        static::assertSame('stored', $commandSettingsDto->getSetting('unknownProperty'));
    }
}
