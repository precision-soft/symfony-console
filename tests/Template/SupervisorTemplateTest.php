<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Console\Test\Utility\ConfFiles;
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

    /** @return iterable<string, array{array<string, mixed>, array<int, string>}> */
    public static function provideValuesCarryingControlCharacters(): iterable
    {
        $command = ['bin/console', 'app:report'];

        yield 'command part' => [[], ['bin/console', "app:report\nuser = root"]];
        yield 'user' => [[Configuration::USER => "test\nuser = root"], $command];
        yield 'prefix' => [[Configuration::PREFIX => "test\n[program:evil]"], $command];
        yield 'log file' => [[Configuration::LOG_FILE => "/var/log/a\nb.log"], $command];
    }

    public function testGenerate(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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

        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('[program:test-test]', $content);
        static::assertStringContainsString('command = test', $content);
        static::assertStringContainsString('numprocs = 1', $content);
        static::assertStringContainsString('autostart = true', $content);
        static::assertStringContainsString('autorestart = true', $content);
        static::assertStringContainsString('user = test', $content);
    }

    public function testSettingsFallBackToConfig(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('[program:config-prefix-worker]', $content);
        static::assertStringContainsString('user = config-user', $content);
        static::assertStringContainsString('autostart = false', $content);
        static::assertStringContainsString('autorestart = false', $content);
        static::assertStringContainsString('numprocs = 3', $content);
    }

    public function testMissingPrefixThrowsException(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('stdout_logfile = /custom/path/worker.log', $content);
    }

    public function testMultipleCommandsGenerateMultipleFiles(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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

    public function testDestinationSubDirSplitsCommandsIntoSubDirectories(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                    Configuration::DESTINATION_SUB_DIR => 'm1',
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'worker-two',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::DESTINATION_SUB_DIR => 'm2',
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'worker-three',
                [
                    Configuration::COMMAND => ['bin/console', 'app:three'],
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(3, $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/m1/worker-one.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/m2/worker-two.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-three.conf', $files);
    }

    public function testDestinationSubDirFallsBackToConfigSettings(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                    Configuration::DESTINATION_SUB_DIR => 'm1',
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
                    Configuration::DESTINATION_SUB_DIR => 'm2',
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(2, $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/m1/worker-one.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/m2/worker-two.conf', $files);
    }

    public function testEmptyCommandDestinationOptsOutOfTheConfigLevelValues(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                    Configuration::DESTINATION_SUB_DIR => 'm1',
                    Configuration::DESTINATION_SUFFIX => 'blue',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker-one',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
                    Configuration::DESTINATION_SUB_DIR => '',
                    Configuration::DESTINATION_SUFFIX => '',
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-one.conf', $files);
    }

    public function testCollidingDestinationPathsAreRejected(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                'worker',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
                    Configuration::DESTINATION_SUFFIX => 'blue',
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'worker.blue',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the file path is in use `/etc/supervisor/conf.d/worker.blue.conf`');

        $supervisorTemplate->generate($configDto, $commands);
    }

    public function testDestinationSubDirSeparatorsAreNormalized(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => '/etc/supervisor/conf.d/',
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
                    Configuration::DESTINATION_SUB_DIR => '/m1/',
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'worker-two',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::DESTINATION_SUB_DIR => '/',
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertArrayHasKey('/etc/supervisor/conf.d/m1/worker-one.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-two.conf', $files);
    }

    public function testDestinationSubDirRedundantSegmentsAreCollapsed(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                'current-dir',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
                    Configuration::DESTINATION_SUB_DIR => '.',
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'repeated-separators',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::DESTINATION_SUB_DIR => 'a//b',
                    Configuration::SETTINGS => [],
                ],
            ),
            new CommandDto(
                'leading-current-dir',
                [
                    Configuration::COMMAND => ['bin/console', 'app:three'],
                    Configuration::DESTINATION_SUB_DIR => './x/',
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertArrayHasKey('/etc/supervisor/conf.d/current-dir.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/a/b/repeated-separators.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/x/leading-current-dir.conf', $files);
    }

    public function testDestinationSuffixIsAppendedBeforeTheExtension(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                    Configuration::DESTINATION_SUFFIX => 'm2',
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
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-one.m2.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-two.conf', $files);
    }

    public function testDestinationSuffixFallsBackToConfigSettings(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                    Configuration::DESTINATION_SUFFIX => 'm1',
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
                    Configuration::DESTINATION_SUFFIX => 'm3',
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(2, $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-one.m1.conf', $files);
        static::assertArrayHasKey('/etc/supervisor/conf.d/worker-two.m3.conf', $files);
    }

    public function testDestinationSubDirAndSuffixCombine(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
                    Configuration::DESTINATION_SUB_DIR => 'eu-west',
                    Configuration::DESTINATION_SUFFIX => '.m2.',
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $supervisorTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertArrayHasKey('/etc/supervisor/conf.d/eu-west/worker-one.m2.conf', $files);
    }

    public function testCommandPassesThroughVerbatim(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
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
        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('command = bin/console messenger:consume --limit=100', $content);
    }

    /* supervisor `%`-interpolates `command` and the log paths, so a literal `%` has to be doubled */
    public function testGenerateEscapesPercentSignsForSupervisorInterpolation(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => '/var/log/100%done',
            Configuration::SETTINGS => [Configuration::AUTO_START => true, Configuration::AUTO_RESTART => true],
        ]);
        $commandDto = new CommandDto('report', [
            Configuration::COMMAND => ['bin/console', 'app:report', '--date=%Y'],
            Configuration::SETTINGS => [Configuration::PREFIX => 'test', Configuration::USER => 'test', Configuration::NUMBER_OF_PROCESSES => 1],
        ]);

        $content = ConfFiles::getFirstContent($supervisorTemplate->generate($configDto, ['report' => $commandDto])->getFiles());

        static::assertStringContainsString('command = bin/console app:report --date=%%Y', $content);
        static::assertStringContainsString('stdout_logfile = /var/log/100%%done/report.log', $content);
        static::assertStringContainsString('process_name = %(program_name)s_%(process_num)s', $content);
    }

    /* the placeholders are substituted in one pass: a value spelling another placeholder is data, not a second template */
    public function testGenerateKeepsPlaceholderTextInValuesLiteral(): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [Configuration::AUTO_START => true, Configuration::AUTO_RESTART => true],
        ]);
        $commandDto = new CommandDto('report', [
            Configuration::COMMAND => ['bin/console', 'app:report', '--label=%user%'],
            Configuration::SETTINGS => [Configuration::PREFIX => 'test', Configuration::USER => 'worker', Configuration::NUMBER_OF_PROCESSES => 1],
        ]);

        $content = ConfFiles::getFirstContent($supervisorTemplate->generate($configDto, ['report' => $commandDto])->getFiles());

        static::assertStringContainsString('command = bin/console app:report --label=%%user%%', $content);
        static::assertStringNotContainsString('--label=worker', $content);
        static::assertStringContainsString('user = worker', $content);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, string> $command
     */
    #[DataProvider('provideValuesCarryingControlCharacters')]
    public function testGenerateRejectsControlCharactersInProgramValues(array $settings, array $command): void
    {
        /** @var SupervisorTemplate&MockInterface $supervisorTemplate */
        $supervisorTemplate = $this->get(SupervisorTemplate::class);

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => 'test',
            Configuration::SETTINGS => [Configuration::AUTO_START => true, Configuration::AUTO_RESTART => true],
        ]);
        $commandDto = new CommandDto('worker', [
            Configuration::COMMAND => $command,
            Configuration::SETTINGS => [Configuration::PREFIX => 'test', Configuration::USER => 'test', Configuration::NUMBER_OF_PROCESSES => 1, ...$settings],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must not contain control characters');

        $supervisorTemplate->generate($configDto, ['worker' => $commandDto]);
    }
}
