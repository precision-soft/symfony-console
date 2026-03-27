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
use PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class KubernetesCronjobTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(KubernetesCronjobTemplate::class, [], true);
    }

    public function testGenerateWithSingleCommand(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $mock */
        $mock = $this->get(KubernetesCronjobTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test-job',
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

        $confFilesDto = $mock->generate($config, $commands);

        static::assertCount(1, $confFilesDto->getFiles());
    }

    public function testGenerateWithEmptyCommands(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $mock */
        $mock = $this->get(KubernetesCronjobTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $confFilesDto = $mock->generate($config, []);

        static::assertCount(0, $confFilesDto->getFiles());
    }

    public function testGenerateWithMultipleCommands(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $mock */
        $mock = $this->get(KubernetesCronjobTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'job-one',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
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
                ],
            ),
            new CommandDto(
                'job-two',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
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

        $confFilesDto = $mock->generate($config, $commands);

        static::assertCount(1, $confFilesDto->getFiles());

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('job-one', $content);
        static::assertStringContainsString('job-two', $content);
    }

    public function testGenerateContentContainsSchedule(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $mock */
        $mock = $this->get(KubernetesCronjobTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test-job',
                [
                    Configuration::COMMAND => ['bin/console', 'app:test'],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '15',
                        Configuration::HOUR => '3',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '1',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            ),
        ];

        $confFilesDto = $mock->generate($config, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('15 3 * * 1', $content);
    }

    public function testGenerateContentContainsCommandParts(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $mock */
        $mock = $this->get(KubernetesCronjobTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test-job',
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

        $confFilesDto = $mock->generate($config, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('bin/console app:test', $content);
    }

    public function testGenerateSanitizesName(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $mock */
        $mock = $this->get(KubernetesCronjobTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                    Configuration::HEARTBEAT => false,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'app:test:command',
                [
                    Configuration::COMMAND => ['bin/console', 'app:test:command'],
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

        $confFilesDto = $mock->generate($config, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        // Colons should be replaced with dashes by sanitize
        static::assertStringContainsString('app-test-command', $content);
    }
}
