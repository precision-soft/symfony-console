<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Worker;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigSettingsDto;

/**
 * @internal
 */
final class ConfigDtoTest extends TestCase
{
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
