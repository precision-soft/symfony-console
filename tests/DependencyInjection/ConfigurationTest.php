<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
final class ConfigurationTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(Configuration::class);
    }

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
        static::assertNull($settings[Configuration::DESTINATION_FILE]);
        static::assertNull($settings[Configuration::DESTINATION_SUB_DIR]);
        static::assertNull($settings[Configuration::DESTINATION_SUFFIX]);
    }

    public function testWorkerDestinationDefaultsToNullPerCommand(): void
    {
        $processedConfiguration = $this->processWorkerConfiguration([
            'test' => [
                Configuration::COMMAND => ['test'],
            ],
        ]);

        $command = $processedConfiguration[Configuration::WORKER][Configuration::COMMANDS]['test'];
        static::assertNull($command[Configuration::DESTINATION_SUB_DIR]);
        static::assertNull($command[Configuration::DESTINATION_SUFFIX]);
    }

    public function testWorkerDestinationIsReadPerCommandAndAtConfigLevel(): void
    {
        $processedConfiguration = $this->processWorkerConfiguration(
            [
                'test' => [
                    Configuration::COMMAND => ['test'],
                    Configuration::DESTINATION_SUB_DIR => 'machine-b',
                    Configuration::DESTINATION_SUFFIX => 'green',
                ],
            ],
            [
                Configuration::DESTINATION_SUB_DIR => 'machine-a',
                Configuration::DESTINATION_SUFFIX => 'blue',
            ],
        );

        $settings = $processedConfiguration[Configuration::WORKER][Configuration::CONFIG][Configuration::SETTINGS];
        static::assertSame('machine-a', $settings[Configuration::DESTINATION_SUB_DIR]);
        static::assertSame('blue', $settings[Configuration::DESTINATION_SUFFIX]);

        $command = $processedConfiguration[Configuration::WORKER][Configuration::COMMANDS]['test'];
        static::assertSame('machine-b', $command[Configuration::DESTINATION_SUB_DIR]);
        static::assertSame('green', $command[Configuration::DESTINATION_SUFFIX]);
    }

    public function testWorkerDestinationSubDirRejectsPathTraversal(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_sub_dir#');

        $this->processWorkerConfiguration([
            'test' => [
                Configuration::COMMAND => ['test'],
                Configuration::DESTINATION_SUB_DIR => '../escape',
            ],
        ]);
    }

    public function testWorkerDestinationSubDirRejectsPathTraversalAtConfigLevel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_sub_dir#');

        $this->processWorkerConfiguration(
            [
                'test' => [
                    Configuration::COMMAND => ['test'],
                ],
            ],
            [
                Configuration::DESTINATION_SUB_DIR => 'a/../../escape',
            ],
        );
    }

    public function testWorkerDestinationSuffixRejectsDirectorySeparator(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_suffix#');

        $this->processWorkerConfiguration([
            'test' => [
                Configuration::COMMAND => ['test'],
                Configuration::DESTINATION_SUFFIX => 'a/b',
            ],
        ]);
    }

    public function testWorkerDestinationSuffixRejectsPathTraversal(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_suffix#');

        $this->processWorkerConfiguration([
            'test' => [
                Configuration::COMMAND => ['test'],
                Configuration::DESTINATION_SUFFIX => '..',
            ],
        ]);
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

    public function testLogsDirsDefaultsToEmptyList(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, []);

        static::assertSame([], $processedConfiguration[Configuration::LOGS_DIRS]);
    }

    public function testLogsDirsRejectsEmptyEntries(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::LOGS_DIRS => [''],
            ],
        ]);
    }

    public function testLogsDirsFromLaterConfigurationReplacesEarlierOne(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            [Configuration::LOGS_DIRS => ['/tmp/first']],
            [Configuration::LOGS_DIRS => ['/tmp/second']],
        ]);

        static::assertSame(['/tmp/second'], $processedConfiguration[Configuration::LOGS_DIRS]);
    }

    public function testLogsDirsRejectsNonStringEntries(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#logs_dirs#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::LOGS_DIRS => [123],
            ],
        ]);
    }

    public function testUnrecognisedSettingsKeysSurviveAtEverySettingsNode(): void
    {
        $processedConfiguration = (new Processor())->processConfiguration(new Configuration(), [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => ['mailto' => 'ops@example.com'],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                            Configuration::SETTINGS => ['nice' => 10],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => ['stopwaitsecs' => 30],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                            Configuration::SETTINGS => ['killasgroup' => true],
                        ],
                    ],
                ],
            ],
        ]);

        $cronjobConfiguration = $processedConfiguration[Configuration::CRONJOB];
        $workerConfiguration = $processedConfiguration[Configuration::WORKER];

        static::assertSame(
            'ops@example.com',
            $cronjobConfiguration[Configuration::CONFIG][Configuration::SETTINGS]['mailto'] ?? null,
        );
        static::assertSame(
            10,
            $cronjobConfiguration[Configuration::COMMANDS]['test'][Configuration::SETTINGS]['nice'] ?? null,
        );
        static::assertSame(
            30,
            $workerConfiguration[Configuration::CONFIG][Configuration::SETTINGS]['stopwaitsecs'] ?? null,
        );
        static::assertTrue(
            $workerConfiguration[Configuration::COMMANDS]['test'][Configuration::SETTINGS]['killasgroup'] ?? null,
        );
    }

    public function testDestinationFileAcceptsAnExplicitValueAtEveryDeclarationSite(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => 'my-crontab',
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                            Configuration::DESTINATION_FILE => 'test.cron',
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => 'workers.conf',
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);

        $cronjobConfiguration = $processedConfiguration[Configuration::CRONJOB];
        $workerConfiguration = $processedConfiguration[Configuration::WORKER];

        static::assertSame(
            'my-crontab',
            $cronjobConfiguration[Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILE],
        );
        static::assertSame(
            'test.cron',
            $cronjobConfiguration[Configuration::COMMANDS]['test'][Configuration::DESTINATION_FILE],
        );
        static::assertSame(
            'workers.conf',
            $workerConfiguration[Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILE],
        );
    }

    public function testDestinationFilesDefaultsToAnEmptyList(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);

        static::assertSame(
            [],
            $processedConfiguration[Configuration::CRONJOB][Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILES],
        );
    }

    public function testDestinationFilesAcceptsAnExplicitList(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILES => ['crontab.m2', 'crontab.m3'],
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);

        static::assertSame(
            ['crontab.m2', 'crontab.m3'],
            $processedConfiguration[Configuration::CRONJOB][Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILES],
        );
    }

    public function testDestinationFilesIsReindexedWhenDeclaredAsAMap(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILES => ['alpha' => 'crontab.m2', 'beta' => 'crontab.m3'],
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);

        static::assertSame(
            ['crontab.m2', 'crontab.m3'],
            $processedConfiguration[Configuration::CRONJOB][Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILES],
        );
    }

    public function testDestinationFilesRejectsTraversal(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_files#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILES => ['../../etc/cron.d/escaped'],
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);
    }

    public function testDestinationFilesRejectsANonStringEntry(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_files#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILES => [123],
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);
    }

    /** @return array<string, array<int, string>> */
    public static function emptyPathProvider(): array
    {
        return [
            'a dot' => ['.'],
            'a dot and a slash' => ['./'],
            'a slash' => ['/'],
        ];
    }

    #[DataProvider('emptyPathProvider')]
    public function testDestinationFilesRejectsAnEntryThatResolvesToAnEmptyPath(string $destinationFile): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_files#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILES => [$destinationFile],
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);
    }

    #[DataProvider('emptyPathProvider')]
    public function testCronjobConfigDestinationFileRejectsAValueThatResolvesToAnEmptyPath(string $destinationFile): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_file#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => $destinationFile,
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);
    }

    public function testCronjobConfigDestinationFileRejectsTraversal(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_file#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => '../../etc/cron.d/escaped',
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);
    }

    public function testCronjobCommandDestinationFileRejectsTraversal(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_file#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                            Configuration::DESTINATION_FILE => '..\\escaped',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testWorkerConfigDestinationFileRejectsTraversal(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('#destination_file#');

        $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::WORKER => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => '../workers.yaml',
                        ],
                    ],
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);
    }

    public function testDestinationFileKeepsItsDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::COMMANDS => [
                        'test' => [Configuration::COMMAND => ['test']],
                    ],
                ],
            ],
        ]);

        $cronjobConfiguration = $processedConfiguration[Configuration::CRONJOB];
        $workerConfiguration = $processedConfiguration[Configuration::WORKER];

        static::assertSame('crontab', $cronjobConfiguration[Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILE]);
        static::assertNull($cronjobConfiguration[Configuration::COMMANDS]['test'][Configuration::DESTINATION_FILE]);
        static::assertNull($workerConfiguration[Configuration::CONFIG][Configuration::SETTINGS][Configuration::DESTINATION_FILE]);
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
        static::assertSame('logs_dirs', Configuration::LOGS_DIRS);
        static::assertSame('heartbeat', Configuration::HEARTBEAT);
        static::assertSame('destination_file', Configuration::DESTINATION_FILE);
        static::assertSame('destination_sub_dir', Configuration::DESTINATION_SUB_DIR);
        static::assertSame('destination_suffix', Configuration::DESTINATION_SUFFIX);
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

    /**
     * @param array<string, mixed> $commands
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    protected function processWorkerConfiguration(array $commands, array $settings = []): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [
            'precision_soft_symfony_console' => [
                Configuration::CRONJOB => [
                    Configuration::COMMANDS => [
                        'test' => [
                            Configuration::COMMAND => ['test'],
                        ],
                    ],
                ],
                Configuration::WORKER => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => $settings,
                    ],
                    Configuration::COMMANDS => $commands,
                ],
            ],
        ]);
    }
}
