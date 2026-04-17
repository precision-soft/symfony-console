<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template;

use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class SupervisorTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(SupervisorTemplate::class, [], true);
    }

    public function testGenerate(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );
        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('[program:test-test]', $content);
        static::assertStringContainsString('command = test', $content);
        static::assertStringContainsString('numprocs = 1', $content);
        static::assertStringContainsString('autostart = true', $content);
        static::assertStringContainsString('autorestart = true', $content);
        static::assertStringContainsString('user = test', $content);
    }

    public function testSettingsFallBackToConfig(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => false,
                    Configuration::AUTO_RESTART => false,
                    Configuration::PREFIX => 'config-prefix',
                    Configuration::USER => 'config-user',
                    Configuration::NUMBER_OF_PROCESSES => 3,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['bin/console', 'app:worker'],
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('[program:config-prefix-worker]', $content);
        static::assertStringContainsString('user = config-user', $content);
        static::assertStringContainsString('autostart = false', $content);
        static::assertStringContainsString('autorestart = false', $content);
        static::assertStringContainsString('numprocs = 3', $content);
    }

    public function testMissingPrefixThrowsException(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `prefix` is mandatory');

        $supervisorTemplate->generate($configDto, $commands);
    }

    public function testMissingUserThrowsException(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `user` is mandatory');

        $supervisorTemplate->generate($configDto, $commands);
    }

    public function testMissingAutoStartThrowsException(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `auto start` is mandatory');

        $supervisorTemplate->generate($configDto, $commands);
    }

    public function testMissingAutoRestartThrowsException(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `auto restart` is mandatory');

        $supervisorTemplate->generate($configDto, $commands);
    }

    public function testCustomLogFileFromCommandSettings(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => '/var/log',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                        Configuration::LOG_FILE => '/custom/path/worker.log',
                    ],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('stdout_logfile = /custom/path/worker.log', $content);
    }

    public function testMultipleCommandsGenerateMultipleFiles(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => '/etc/supervisor/conf.d',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                    Configuration::PREFIX => 'app',
                    Configuration::USER => 'www-data',
                    Configuration::NUMBER_OF_PROCESSES => 1,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker-one',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'worker-two',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(2, $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-one.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-two.conf', $files);
    }

    public function testCommandIsEscaped(): void
    {
        /** @var SupervisorTemplate|MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['bin/console', 'messenger:consume', '--limit=100'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('command = bin/console messenger:consume --limit=100', $content);
    }
}
