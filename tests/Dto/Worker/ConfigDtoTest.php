<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Worker;

use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigSettingsDto;

/**
 * @internal
 */
final class ConfigDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(ConfigDto::class);
    }

    public function testConstructorAndGetters(): void
    {
        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'SupervisorTemplate',
            Configuration::CONF_FILES_DIR => '/generated/worker',
            Configuration::LOGS_DIR => '/logs/worker',
            Configuration::SETTINGS => [
                Configuration::NUMBER_OF_PROCESSES => 2,
                Configuration::AUTO_START => true,
                Configuration::AUTO_RESTART => true,
            ],
        ]);

        static::assertSame('SupervisorTemplate', $configDto->getTemplateClass());
        static::assertSame('/generated/worker', $configDto->getConfFilesDir());
        static::assertSame('/logs/worker', $configDto->getLogsDir());
        static::assertInstanceOf(ConfigSettingsDto::class, $configDto->getSettings());
    }
}
