<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Worker;

use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandSettingsDto;

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
            'test-worker',
            [
                Configuration::COMMAND => ['bin/console', 'messenger:consume'],
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 3,
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => false,
                ],
            ],
        );

        static::assertSame('test-worker', $commandDto->getName());
        static::assertSame(['bin/console', 'messenger:consume'], $commandDto->getCommand());
        static::assertInstanceOf(CommandSettingsDto::class, $commandDto->getSettings());
    }

    public function testSettingsDefaultsToEmptyArray(): void
    {
        $commandDto = new CommandDto(
            'test',
            [
                Configuration::COMMAND => ['test'],
            ],
        );

        static::assertInstanceOf(CommandSettingsDto::class, $commandDto->getSettings());
    }
}
