<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Cronjob;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigSettingsDto;

/**
 * @internal
 */
final class ConfigDtoTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'MyTemplate',
            Configuration::CONF_FILES_DIR => '/conf/dir',
            Configuration::LOGS_DIR => '/logs/dir',
            Configuration::SETTINGS => [
                Configuration::LOG => true,
                Configuration::DESTINATION_FILE => 'crontab',
                Configuration::HEARTBEAT => true,
            ],
        ]);

        static::assertSame('MyTemplate', $configDto->getTemplateClass());
        static::assertSame('/conf/dir', $configDto->getConfFilesDir());
        static::assertSame('/logs/dir', $configDto->getLogsDir());
        static::assertInstanceOf(ConfigSettingsDto::class, $configDto->getSettings());
    }
}
