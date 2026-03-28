<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
final class ConfigurationTest extends TestCase
{
    public function testGetConfigTreeBuilderReturnsTreeBuilder(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        static::assertSame('precision_soft_symfony_console', $treeBuilder->buildTree()->getName());
    }

    public function testCronjobDefaultConfiguration(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ]);

        static::assertSame(CrontabTemplate::class, $processedConfiguration[Configuration::CRONJOB][Configuration::CONFIG][Configuration::TEMPLATE_CLASS]);
        static::assertStringContainsString('cron', $processedConfiguration[Configuration::CRONJOB][Configuration::CONFIG][Configuration::CONF_FILES_DIR]);
    }

    public function testWorkerDefaultConfiguration(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ]);

        static::assertSame(SupervisorTemplate::class, $processedConfiguration[Configuration::WORKER][Configuration::CONFIG][Configuration::TEMPLATE_CLASS]);
        static::assertStringContainsString('worker', $processedConfiguration[Configuration::WORKER][Configuration::CONFIG][Configuration::CONF_FILES_DIR]);
    }

    public function testCronjobSettingsDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ]);

        $settings = $processedConfiguration[Configuration::CRONJOB][Configuration::CONFIG][Configuration::SETTINGS];
        static::assertTrue($settings[Configuration::LOG]);
        static::assertSame('crontab', $settings[Configuration::DESTINATION_FILE]);
        static::assertTrue($settings[Configuration::HEARTBEAT]);
        static::assertNull($settings[Configuration::USER]);
    }

    public function testWorkerSettingsDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ]);

        $settings = $processedConfiguration[Configuration::WORKER][Configuration::CONFIG][Configuration::SETTINGS];
        static::assertSame(1, $settings[Configuration::NUMBER_OF_PROCESSES]);
        static::assertTrue($settings[Configuration::AUTO_START]);
        static::assertTrue($settings[Configuration::AUTO_RESTART]);
        static::assertNull($settings[Configuration::PREFIX]);
        static::assertNull($settings[Configuration::USER]);
    }

    public function testCronjobScheduleDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                            Configuration::SCHEDULE => [],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ]);

        $schedule = $processedConfiguration[Configuration::CRONJOB][Configuration::COMMANDS]['test'][Configuration::SCHEDULE];
        static::assertSame('*', $schedule[Configuration::MINUTE]);
        static::assertSame('*', $schedule[Configuration::HOUR]);
        static::assertSame('*', $schedule[Configuration::DAY_OF_MONTH]);
        static::assertSame('*', $schedule[Configuration::MONTH]);
        static::assertSame('*', $schedule[Configuration::DAY_OF_WEEK]);
    }

    public function testCronjobCommandStringNormalization(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => 'single-command',
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
            ],
        ]);

        static::assertIsArray($processedConfiguration[Configuration::CRONJOB][Configuration::COMMANDS]['test'][Configuration::COMMAND]);
        static::assertSame(['single-command'], $processedConfiguration[Configuration::CRONJOB][Configuration::COMMANDS]['test'][Configuration::COMMAND]);
    }

    public function testWorkerCommandStringNormalization(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => 'single-command',
                        ],
                    ],
                ],
            ],
        ]);

        static::assertIsArray($processedConfiguration[Configuration::WORKER][Configuration::COMMANDS]['test'][Configuration::COMMAND]);
        static::assertSame(['single-command'], $processedConfiguration[Configuration::WORKER][Configuration::COMMANDS]['test'][Configuration::COMMAND]);
    }

    public function testConstantsExist(): void
    {
        static::assertSame('command', Configuration::COMMAND);
        static::assertSame('schedule', Configuration::SCHEDULE);
        static::assertSame('log', Configuration::LOG);
        static::assertSame('log_file_name', Configuration::LOG_FILE_NAME);
        static::assertSame('log_file', Configuration::LOG_FILE);
        static::assertSame('template_class', Configuration::TEMPLATE_CLASS);
        static::assertSame('conf_files_dir', Configuration::CONF_FILES_DIR);
        static::assertSame('logs_dir', Configuration::LOGS_DIR);
        static::assertSame('heartbeat', Configuration::HEARTBEAT);
        static::assertSame('destination_file', Configuration::DESTINATION_FILE);
        static::assertSame('config', Configuration::CONFIG);
        static::assertSame('commands', Configuration::COMMANDS);
        static::assertSame('minute', Configuration::MINUTE);
        static::assertSame('hour', Configuration::HOUR);
        static::assertSame('day_of_month', Configuration::DAY_OF_MONTH);
        static::assertSame('month', Configuration::MONTH);
        static::assertSame('day_of_week', Configuration::DAY_OF_WEEK);
        static::assertSame('number_of_processes', Configuration::NUMBER_OF_PROCESSES);
        static::assertSame('auto_start', Configuration::AUTO_START);
        static::assertSame('auto_restart', Configuration::AUTO_RESTART);
        static::assertSame('prefix', Configuration::PREFIX);
        static::assertSame('user', Configuration::USER);
        static::assertSame('cronjob', Configuration::CRONJOB);
        static::assertSame('worker', Configuration::WORKER);
        static::assertSame('settings', Configuration::SETTINGS);
    }
}
