<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Console\Test\Utility\ConfFiles;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;

/**
 * @internal
 */
final class CrontabTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CrontabTemplate::class, [], true);
    }


    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function provideValuesCarryingControlCharacters(): iterable
    {
        yield 'command part' => [[], [Configuration::COMMAND => ['bin/console', "app:report\n* * * * * root /bin/evil"]]];
        yield 'command user' => [[], [Configuration::USER => "root\n* * * * * root /bin/evil"]];
        yield 'log file name' => [[], [Configuration::LOG_FILE_NAME => "report\n.log"]];
        yield 'config user' => [[Configuration::SETTINGS => [Configuration::USER => "root\r* * * * * root /bin/evil"]], []];
        yield 'logs dir' => [[Configuration::LOGS_DIR => "/var/log\n* * * * * root /bin/evil"], []];
    }

    public function testGenerate(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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

        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('* * * * * test', $content);
        static::assertStringContainsString('GENERATED FILE', $content);
        static::assertStringContainsString(">> 'test/test.log' 2>&1", $content);
        static::assertStringContainsString('/bin/touch', $content);
    }

    public function testHeartbeatDisabled(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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

        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('0 6 * * * bin/console app:test', $content);
        static::assertStringNotContainsString('/bin/touch', $content);
        static::assertStringNotContainsString('heartbeat', $content);
    }

    public function testLogDisabledOmitsLogRedirect(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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
        $content = ConfFiles::getFirstContent($files);
        static::assertStringNotContainsString('>>', $content);
        static::assertStringNotContainsString('2>&1', $content);
    }

    public function testUserFromConfigSettings(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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
        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('www-data bin/console app:test', $content);
    }

    public function testMultipleCommandsAcrossFiles(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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

        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('/bin/touch', $content);
        static::assertStringContainsString('/tmp/heartbeat.test', $content);
    }

    public function testHeartbeatOnlyWithDefaultHeartbeatGeneratesFile(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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

        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString('/bin/touch', $content);
        static::assertStringContainsString('heartbeat', $content);
    }

    public function testCustomLogFileName(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
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
        $content = ConfFiles::getFirstContent($files);
        static::assertStringContainsString(">> '/var/log/custom.log' 2>&1", $content);
    }

    public function testHeartbeatOverridePlaceholderIsInterpolatedPerDestinationFile(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(),
            $this->buildTwoFileCommands(
                \sprintf('bin/console app:heartbeat %s', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER),
            ),
        );

        $files = $confFilesDto->getFiles();
        static::assertCount(2, $files);

        static::assertStringContainsString('bin/console app:heartbeat crontab' . \PHP_EOL, $files['test/crontab']);
        static::assertStringNotContainsString('crontab.m2', $files['test/crontab']);

        static::assertStringContainsString('bin/console app:heartbeat crontab.m2', $files['test/crontab.m2']);
        static::assertStringNotContainsString(CrontabTemplate::DESTINATION_FILE_PLACEHOLDER, $files['test/crontab.m2']);
    }

    public function testHeartbeatOverrideWithoutPlaceholderIsIdenticalInEveryDestinationFile(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(),
            $this->buildTwoFileCommands('bin/console app:heartbeat'),
        );

        $files = $confFilesDto->getFiles();
        static::assertCount(2, $files);

        foreach ($files as $content) {
            static::assertStringContainsString('bin/console app:heartbeat' . \PHP_EOL, $content);
        }
    }

    public function testHeartbeatOverridePlaceholderIsInterpolatedInTheLogFileName(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(),
            $this->buildTwoFileCommands(
                'bin/console app:heartbeat',
                \sprintf('heartbeat.%s.log', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER),
            ),
        );

        $files = $confFilesDto->getFiles();

        static::assertStringContainsString(">> '/var/log/heartbeat.crontab.log' 2>&1", $files['test/crontab']);
        static::assertStringContainsString(">> '/var/log/heartbeat.crontab.m2.log' 2>&1", $files['test/crontab.m2']);
    }

    public function testDeclaredDestinationFileIsGeneratedWithoutAnyCommandTargetingIt(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['crontab.m3']),
            $this->buildTwoFileCommands(
                \sprintf('bin/console app:heartbeat %s', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER),
            ),
        );

        $files = $confFilesDto->getFiles();
        static::assertCount(3, $files);
        static::assertArrayHasKey('test/crontab.m3', $files);

        static::assertStringContainsString('bin/console app:heartbeat crontab.m3', $files['test/crontab.m3']);
        static::assertStringNotContainsString('app:one', $files['test/crontab.m3']);
        static::assertStringNotContainsString('app:two', $files['test/crontab.m3']);
    }

    public function testHeartbeatPlaceholderIsSubstitutedBeforeTheLogPathIsEscaped(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => '/var/log',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => "cron'tab",
                    Configuration::HEARTBEAT => true,
                ],
            ],
        );

        $confFilesDto = $crontabTemplate->generate(
            $configDto,
            [
                Configuration::HEARTBEAT => new CommandDto(
                    Configuration::HEARTBEAT,
                    [
                        Configuration::COMMAND => ['bin/console', 'app:heartbeat'],
                        Configuration::LOG_FILE_NAME => \sprintf('heartbeat.%s.log', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER),
                        Configuration::SCHEDULE => [
                            Configuration::MINUTE => '*',
                            Configuration::HOUR => '*',
                            Configuration::DAY_OF_MONTH => '*',
                            Configuration::MONTH => '*',
                            Configuration::DAY_OF_WEEK => '*',
                        ],
                        Configuration::SETTINGS => [Configuration::LOG => true],
                    ],
                ),
            ],
        );

        $content = ConfFiles::getFirstContent($confFilesDto->getFiles());

        static::assertStringContainsString(">> '/var/log/heartbeat.cron'\\''tab.log' 2>&1", $content);
        static::assertStringNotContainsString(CrontabTemplate::DESTINATION_FILE_PLACEHOLDER, $content);
    }

    public function testDeclaredDestinationFileIsGeneratedWithTheHeartbeatDisabled(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['crontab.m3'], false),
            $this->buildTwoFileCommands('bin/console app:heartbeat'),
        );

        $files = $confFilesDto->getFiles();
        static::assertCount(3, $files);
        static::assertArrayHasKey('test/crontab.m3', $files);

        static::assertStringNotContainsString('bin/console', $files['test/crontab.m3']);
    }

    public function testNumericDestinationFileReachesTheDefaultHeartbeat(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['2026']),
            [
                'one' => new CommandDto(
                    'one',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:one'],
                        Configuration::SCHEDULE => [
                            Configuration::MINUTE => '*',
                            Configuration::HOUR => '*',
                            Configuration::DAY_OF_MONTH => '*',
                            Configuration::MONTH => '*',
                            Configuration::DAY_OF_WEEK => '*',
                        ],
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $files = $confFilesDto->getFiles();

        static::assertArrayHasKey('test/2026', $files);
        static::assertStringContainsString('/bin/touch /var/log/heartbeat.2026', $files['test/2026']);
    }

    public function testDeclaringAFileThatCommandsAlreadyTargetKeepsTheirRows(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $schedule = [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['crontab'], false),
            [
                'one' => new CommandDto(
                    'one',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:one'],
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
                'two' => new CommandDto(
                    'two',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:two'],
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $files = $confFilesDto->getFiles();

        static::assertCount(1, $files);

        static::assertStringContainsString('bin/console app:one', $files['test/crontab']);
        static::assertStringContainsString('bin/console app:two', $files['test/crontab']);
    }

    public function testHeartbeatWithoutThePlaceholderIsReturnedUntouched(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $schedule = [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];

        $reflectionMethod = new ReflectionMethod($crontabTemplate, 'resolveHeartbeat');

        $plainCommandDto = new CommandDto(
            Configuration::HEARTBEAT,
            [
                Configuration::COMMAND => ['bin/console', 'app:heartbeat'],
                Configuration::SCHEDULE => $schedule,
                Configuration::SETTINGS => [Configuration::LOG => false],
            ],
        );

        static::assertSame($plainCommandDto, $reflectionMethod->invoke($crontabTemplate, $plainCommandDto, 'crontab'));

        $placeholderCommandDto = new CommandDto(
            Configuration::HEARTBEAT,
            [
                Configuration::COMMAND => ['bin/console', 'app:heartbeat', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER],
                Configuration::SCHEDULE => $schedule,
                Configuration::SETTINGS => [Configuration::LOG => false],
            ],
        );

        $resolvedCommandDto = $reflectionMethod->invoke($crontabTemplate, $placeholderCommandDto, 'crontab');

        static::assertNotSame($placeholderCommandDto, $resolvedCommandDto);
        static::assertInstanceOf(CommandDto::class, $resolvedCommandDto);
        static::assertSame(['bin/console', 'app:heartbeat', 'crontab'], $resolvedCommandDto->getCommand());
        static::assertSame(Configuration::HEARTBEAT, $resolvedCommandDto->getName());
        static::assertFalse($resolvedCommandDto->getSettings()->getLog());
    }

    public function testHeartbeatOverrideKeepsItsOwnUserWhenThePlaceholderIsResolved(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $schedule = [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => '/var/log',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                    Configuration::USER => 'root',
                ],
            ],
        );

        $confFilesDto = $crontabTemplate->generate(
            $configDto,
            [
                Configuration::HEARTBEAT => new CommandDto(
                    Configuration::HEARTBEAT,
                    [
                        Configuration::COMMAND => ['bin/console', 'app:heartbeat', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER],
                        Configuration::USER => 'www-data',
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $content = ConfFiles::getFirstContent($confFilesDto->getFiles());

        static::assertStringContainsString('* * * * * www-data bin/console app:heartbeat crontab', $content);
    }

    public function testDeclaredDestinationFilesDoNotSuppressTheDefaultHeartbeatFile(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['crontab.m3']),
            [
                Configuration::HEARTBEAT => new CommandDto(
                    Configuration::HEARTBEAT,
                    [
                        Configuration::COMMAND => ['bin/console', 'app:heartbeat', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER],
                        Configuration::SCHEDULE => [
                            Configuration::MINUTE => '*',
                            Configuration::HOUR => '*',
                            Configuration::DAY_OF_MONTH => '*',
                            Configuration::MONTH => '*',
                            Configuration::DAY_OF_WEEK => '*',
                        ],
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $files = $confFilesDto->getFiles();

        static::assertCount(2, $files);

        static::assertStringContainsString('bin/console app:heartbeat crontab' . \PHP_EOL, $files['test/crontab']);
        static::assertStringContainsString('bin/console app:heartbeat crontab.m3', $files['test/crontab.m3']);
    }

    public function testHeartbeatDeclaredBeforeTheOtherCommandsDoesNotDropThem(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $schedule = [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(),
            [
                Configuration::HEARTBEAT => new CommandDto(
                    Configuration::HEARTBEAT,
                    [
                        Configuration::COMMAND => ['bin/console', 'app:heartbeat'],
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
                'one' => new CommandDto(
                    'one',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:one'],
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
                'two' => new CommandDto(
                    'two',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:two'],
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $content = ConfFiles::getFirstContent($confFilesDto->getFiles());

        static::assertStringContainsString('bin/console app:one', $content);
        static::assertStringContainsString('bin/console app:two', $content);
        static::assertStringContainsString('bin/console app:heartbeat', $content);
    }

    public function testDeclaredFileSpelledWithADotSegmentMergesIntoTheFileItResolvesTo(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['./crontab'], false),
            [
                'one' => new CommandDto(
                    'one',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:one'],
                        Configuration::SCHEDULE => [
                            Configuration::MINUTE => '*',
                            Configuration::HOUR => '*',
                            Configuration::DAY_OF_MONTH => '*',
                            Configuration::MONTH => '*',
                            Configuration::DAY_OF_WEEK => '*',
                        ],
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $files = $confFilesDto->getFiles();

        static::assertCount(1, $files);
        static::assertArrayHasKey('test/crontab', $files);
        static::assertStringContainsString('bin/console app:one', $files['test/crontab']);
    }

    public function testCommandsWhoseDestinationFilesResolveToTheSameFileShareIt(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $schedule = [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto([], false),
            [
                'one' => new CommandDto(
                    'one',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:one'],
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
                'two' => new CommandDto(
                    'two',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:two'],
                        Configuration::DESTINATION_FILE => './crontab',
                        Configuration::SCHEDULE => $schedule,
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $files = $confFilesDto->getFiles();

        static::assertCount(1, $files);
        static::assertArrayHasKey('test/crontab', $files);
        static::assertStringContainsString('bin/console app:one', $files['test/crontab']);
        static::assertStringContainsString('bin/console app:two', $files['test/crontab']);
    }

    public function testNestedAndRedundantPathSegmentsAreNormalized(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['sub/./crontab', './/other', 'trailing/'], false),
            [
                'one' => new CommandDto(
                    'one',
                    [
                        Configuration::COMMAND => ['bin/console', 'app:one'],
                        Configuration::SCHEDULE => [
                            Configuration::MINUTE => '*',
                            Configuration::HOUR => '*',
                            Configuration::DAY_OF_MONTH => '*',
                            Configuration::MONTH => '*',
                            Configuration::DAY_OF_WEEK => '*',
                        ],
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        static::assertSame(
            ['test/crontab', 'test/sub/crontab', 'test/other', 'test/trailing'],
            \array_keys($confFilesDto->getFiles()),
        );
    }

    public function testNestedDestinationFilesSharingABaseNameGetDistinctHeartbeatLabels(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['machine-a/crontab', 'machine-b/crontab']),
            [
                Configuration::HEARTBEAT => new CommandDto(
                    Configuration::HEARTBEAT,
                    [
                        Configuration::COMMAND => ['bin/console', 'app:heartbeat', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER],
                        Configuration::SCHEDULE => $this->buildSchedule(),
                        Configuration::SETTINGS => [Configuration::LOG => false],
                    ],
                ),
            ],
        );

        $files = $confFilesDto->getFiles();

        static::assertSame(
            ['test/crontab', 'test/machine-a/crontab', 'test/machine-b/crontab'],
            \array_keys($files),
        );

        static::assertStringContainsString('bin/console app:heartbeat crontab' . \PHP_EOL, $files['test/crontab']);
        static::assertStringContainsString('bin/console app:heartbeat machine-a.crontab', $files['test/machine-a/crontab']);
        static::assertStringContainsString('bin/console app:heartbeat machine-b.crontab', $files['test/machine-b/crontab']);
    }

    public function testNestedDestinationFilesSharingABaseNameTouchDistinctDefaultHeartbeatFiles(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto(['machine-a/crontab', 'machine-b/crontab']),
            [],
        );

        $files = $confFilesDto->getFiles();

        static::assertStringContainsString('/bin/touch /var/log/heartbeat.crontab' . \PHP_EOL, $files['test/crontab']);
        static::assertStringContainsString('/bin/touch /var/log/heartbeat.machine-a.crontab', $files['test/machine-a/crontab']);
        static::assertStringContainsString('/bin/touch /var/log/heartbeat.machine-b.crontab', $files['test/machine-b/crontab']);
    }

    public function testResolvedHeartbeatKeepsSettingsTheBundleDoesNotModel(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $reflectionMethod = new ReflectionMethod($crontabTemplate, 'resolveHeartbeat');

        $commandDto = new CommandDto(
            Configuration::HEARTBEAT,
            [
                Configuration::COMMAND => ['bin/console', 'app:heartbeat', CrontabTemplate::DESTINATION_FILE_PLACEHOLDER],
                Configuration::SCHEDULE => $this->buildSchedule(),
                Configuration::SETTINGS => [
                    Configuration::LOG => false,
                    'custom_setting' => 'value',
                ],
            ],
        );

        $resolvedCommandDto = $reflectionMethod->invoke($crontabTemplate, $commandDto, 'crontab');

        static::assertInstanceOf(CommandDto::class, $resolvedCommandDto);
        static::assertSame('value', $resolvedCommandDto->getSettings()->getSetting('customSetting'));
        static::assertFalse($resolvedCommandDto->getSettings()->getLog());
    }

    public function testACommandNamedHeartbeatIsNotEmittedWhenTheHeartbeatIsDisabled(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $confFilesDto = $crontabTemplate->generate(
            $this->buildConfigDto([], false),
            $this->buildTwoFileCommands('bin/console app:heartbeat'),
        );

        foreach ($confFilesDto->getFiles() as $content) {
            static::assertStringNotContainsString('app:heartbeat', $content);
        }
    }

    public function testADestinationFileThatResolvesToAnEmptyPathIsRejected(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `destination file` `.` resolves to an empty path');

        $crontabTemplate->generate(
            new ConfigDto(
                [
                    Configuration::TEMPLATE_CLASS => 'test',
                    Configuration::CONF_FILES_DIR => 'test',
                    Configuration::LOGS_DIR => '/var/log',
                    Configuration::SETTINGS => [
                        Configuration::DESTINATION_FILE => '.',
                        Configuration::HEARTBEAT => true,
                    ],
                ],
            ),
            [],
        );
    }

    public function testADeclaredDestinationFileThatResolvesToAnEmptyPathIsRejected(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `destination file` `./` resolves to an empty path');

        $crontabTemplate->generate($this->buildConfigDto(['./'], false), $this->buildTwoFileCommands('bin/console app:heartbeat'));
    }

    /* cron cuts the command at an unescaped `%` and feeds what follows to its stdin; the literal form is `\%` */
    public function testGenerateEscapesPercentSignsInCommandsAndLogPaths(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => '/var/log/100%done',
            Configuration::SETTINGS => [Configuration::DESTINATION_FILE => 'crontab', Configuration::HEARTBEAT => false],
        ]);
        $commandDto = new CommandDto('report', [
            Configuration::COMMAND => ['bin/console', 'app:report', '--date=%Y-%m-%d'],
            Configuration::LOG_FILE_NAME => 'report-%d.log',
            Configuration::SCHEDULE => $this->buildSchedule(),
            Configuration::SETTINGS => [Configuration::LOG => true],
        ]);

        $content = ConfFiles::getFirstContent($crontabTemplate->generate($configDto, ['report' => $commandDto])->getFiles());

        static::assertStringContainsString(
            '* * * * * bin/console app:report --date=\%Y-\%m-\%d >> \'/var/log/100\%done/report-\%d.log\' 2>&1',
            $content,
        );
    }

    /* `escapeshellarg()` follows `LC_CTYPE`: under the `C` locale it drops every byte above 0x7f, so a UTF-8 path was rewritten silently */
    public function testTheLogPathSurvivesANonAsciiLogsDirUnderTheCLocale(): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => '/srv/données',
            Configuration::SETTINGS => [Configuration::DESTINATION_FILE => 'crontab', Configuration::HEARTBEAT => false],
        ]);
        $commandDto = new CommandDto('report', [
            Configuration::COMMAND => ['bin/console', 'app:report'],
            Configuration::SCHEDULE => $this->buildSchedule(),
            Configuration::SETTINGS => [Configuration::LOG => true],
        ]);

        $previousLocale = \setlocale(\LC_CTYPE, '0');

        static::assertIsString($previousLocale);
        static::assertIsString(\setlocale(\LC_CTYPE, 'C'));

        try {
            $content = ConfFiles::getFirstContent($crontabTemplate->generate($configDto, ['report' => $commandDto])->getFiles());
        } finally {
            \setlocale(\LC_CTYPE, $previousLocale);
        }

        static::assertStringContainsString(">> '/srv/données/report.log' 2>&1", $content);
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $commandParameters
     */
    #[DataProvider('provideValuesCarryingControlCharacters')]
    public function testGenerateRejectsControlCharactersInCrontabValues(array $configuration, array $commandParameters): void
    {
        /** @var CrontabTemplate&MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(\array_replace_recursive([
            Configuration::TEMPLATE_CLASS => 'test',
            Configuration::CONF_FILES_DIR => 'test',
            Configuration::LOGS_DIR => '/var/log',
            Configuration::SETTINGS => [Configuration::DESTINATION_FILE => 'crontab', Configuration::HEARTBEAT => false],
        ], $configuration));
        $commandDto = new CommandDto('report', \array_replace_recursive([
            Configuration::COMMAND => ['bin/console', 'app:report'],
            Configuration::SCHEDULE => $this->buildSchedule(),
            Configuration::SETTINGS => [Configuration::LOG => true],
        ], $commandParameters));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must not contain control characters');

        $crontabTemplate->generate($configDto, ['report' => $commandDto]);
    }

    /** @return array<string, string> */
    private function buildSchedule(): array
    {
        return [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];
    }

    /** @param array<int, string> $destinationFiles */
    private function buildConfigDto(array $destinationFiles = [], bool $heartbeat = true): ConfigDto
    {
        return new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => '/var/log',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::DESTINATION_FILES => $destinationFiles,
                    Configuration::HEARTBEAT => $heartbeat,
                ],
            ],
        );
    }

    /** @return array<int|string, CommandDto> */
    private function buildTwoFileCommands(string $heartbeatCommand, ?string $heartbeatLogFileName = null): array
    {
        $schedule = [
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ];

        $heartbeatParameters = [
            Configuration::COMMAND => \explode(' ', $heartbeatCommand),
            Configuration::SCHEDULE => $schedule,
            Configuration::SETTINGS => [
                Configuration::LOG => null !== $heartbeatLogFileName,
            ],
        ];

        if (null !== $heartbeatLogFileName) {
            $heartbeatParameters[Configuration::LOG_FILE_NAME] = $heartbeatLogFileName;
        }

        return [
            'one' => new CommandDto(
                'one',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
                    Configuration::SCHEDULE => $schedule,
                    Configuration::SETTINGS => [Configuration::LOG => false],
                ],
            ),
            'two' => new CommandDto(
                'two',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::SCHEDULE => $schedule,
                    Configuration::SETTINGS => [Configuration::LOG => false],
                    Configuration::DESTINATION_FILE => 'crontab.m2',
                ],
            ),
            Configuration::HEARTBEAT => new CommandDto(
                Configuration::HEARTBEAT,
                $heartbeatParameters,
            ),
        ];
    }
}
