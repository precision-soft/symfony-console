<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandSettingsDto;
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

    public function testLogDefaultsToNull(): void
    {
        $commandSettingsDto = new CommandSettingsDto([]);

        static::assertNull($commandSettingsDto->getLog());
    }

    public function testLogIsSetFromSettings(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            Configuration::LOG => true,
        ]);

        static::assertTrue($commandSettingsDto->getLog());
    }

    public function testLogCanBeFalse(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            Configuration::LOG => false,
        ]);

        static::assertFalse($commandSettingsDto->getLog());
    }

    public function testExtraSettingsAreStoredAsSettings(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            Configuration::LOG => true,
            'custom_setting' => 'value',
        ]);

        static::assertSame('value', $commandSettingsDto->getSetting('customSetting'));
    }

    public function testToArrayReturnsSnakeCaseKeysForModelledAndExtraSettingsAlike(): void
    {
        $commandSettingsDto = new CommandSettingsDto([
            Configuration::LOG => true,
            'custom_setting' => 'value',
        ]);

        static::assertSame(
            [
                Configuration::LOG => true,
                'custom_setting' => 'value',
            ],
            $commandSettingsDto->toArray(),
        );
    }

    public function testToArrayRoundTripsThroughTheConstructor(): void
    {
        $settings = [
            Configuration::LOG => false,
            'custom_setting' => 'value',
            'another_one' => 7,
        ];

        $commandSettingsDto = new CommandSettingsDto((new CommandSettingsDto($settings))->toArray());

        static::assertFalse($commandSettingsDto->getLog());
        static::assertSame('value', $commandSettingsDto->getSetting('customSetting'));
        static::assertSame('7', $commandSettingsDto->getSetting('anotherOne'));
    }
}
