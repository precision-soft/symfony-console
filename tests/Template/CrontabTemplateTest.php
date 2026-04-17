<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template;

use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class CrontabTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CrontabTemplate::class, [], true);
    }

    public function testGenerate(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => true,
                ],
            ],
        );
        $commands = [
            new CommandDto(
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
                    Configuration::SETTINGS => [
                        Configuration::LOG => true,
                    ],
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('* * * * * test', $content);
        static::assertStringContainsString('GENERATED FILE', $content);
        static::assertStringContainsString(">> 'test/test.log' 2>&1", $content);
        static::assertStringContainsString('/bin/touch', $content);
    }

    public function testHeartbeatDisabled(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['bin/console', 'app:test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '0',
                        Configuration::HOUR => '6',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('0 6 * * * bin/console app:test', $content);
        static::assertStringNotContainsString('/bin/touch', $content);
        static::assertStringNotContainsString('heartbeat', $content);
    }

    public function testLogDisabledOmitsLogRedirect(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['bin/console', 'app:test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringNotContainsString('>>', $content);
        static::assertStringNotContainsString('2>&1', $content);
    }

    public function testUserFromConfigSettings(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => false,
                    Configuration::USER => 'www-data',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['bin/console', 'app:test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*/5',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('www-data bin/console app:test', $content);
    }

    public function testMultipleCommandsAcrossFiles(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'default.cron',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'first',
                [
                    Configuration::COMMAND => ['bin/console', 'app:first'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '0',
                        Configuration::HOUR => '0',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                    Configuration::DESTINATION_FILE => 'custom.cron',
                ],
            ),
            new CommandDto(
                'second',
                [
                    Configuration::COMMAND => ['bin/console', 'app:second'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '30',
                        Configuration::HOUR => '12',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(2, $files);
        static::assertArrayHasKey('test/custom.cron', $files);
        static::assertArrayHasKey('test/default.cron', $files);
    }

    public function testEmptyCommandsGeneratesNoFiles(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $confFilesDto = $crontabTemplate->generate($configDto, []);

        static::assertCount(0, $confFilesDto->getFiles());
    }

    public function testHeartbeatOnlyConfigGeneratesFile(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                ],
            ],
        );

        $commands = [
            Configuration::HEARTBEAT => new CommandDto(
                Configuration::HEARTBEAT,
                [
                    Configuration::COMMAND => ['/bin/touch', '/tmp/heartbeat.test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('/bin/touch', $content);
        static::assertStringContainsString('/tmp/heartbeat.test', $content);
    }

    public function testHeartbeatOnlyWithDefaultHeartbeatGeneratesFile(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                ],
            ],
        );

        $confFilesDto = $crontabTemplate->generate($configDto, []);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('/bin/touch', $content);
        static::assertStringContainsString('heartbeat', $content);
    }

    public function testCustomLogFileName(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => '/var/log',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['bin/console', 'app:test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => true,
                    ],
                    Configuration::LOG_FILE_NAME => 'custom.log',
                ],
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString(">> '/var/log/custom.log' 2>&1", $content);
    }
}
