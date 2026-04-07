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
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
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
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
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

        $confFilesDto = $kubernetesCronjobTemplate->generate($configDto, $commands);

        static::assertCount(1, $confFilesDto->getFiles());

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('test-job', $content);
        static::assertStringContainsString('*/5 * * * *', $content);
        static::assertStringContainsString("'bin/console' 'app:test'", $content);
    }

    public function testGenerateWithEmptyCommands(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
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

        $confFilesDto = $kubernetesCronjobTemplate->generate($configDto, []);

        static::assertCount(0, $confFilesDto->getFiles());
    }

    public function testGenerateWithMultipleCommands(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
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

        $confFilesDto = $kubernetesCronjobTemplate->generate($configDto, $commands);

        static::assertCount(1, $confFilesDto->getFiles());

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('job-one', $content);
        static::assertStringContainsString('job-two', $content);
    }

    public function testGenerateContentContainsSchedule(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
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

        $confFilesDto = $kubernetesCronjobTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('15 3 * * 1', $content);
    }

    public function testGenerateContentContainsCommandParts(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
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

        $confFilesDto = $kubernetesCronjobTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString("'bin/console' 'app:test'", $content);
    }

    public function testNullDestinationFileThrowsException(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
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

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `destination file` is mandatory for kubernetes cronjob template');

        $kubernetesCronjobTemplate->generate($configDto, $commands);
    }

    public function testEmptyDestinationFileThrowsException(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => '',
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

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `destination file` is mandatory for kubernetes cronjob template');

        $kubernetesCronjobTemplate->generate($configDto, $commands);
    }

    public function testGenerateSanitizesName(): void
    {
        /** @var KubernetesCronjobTemplate|MockInterface $kubernetesCronjobTemplate */
        $kubernetesCronjobTemplate = $this->get(KubernetesCronjobTemplate::class);

        $configDto = new ConfigDto(
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

        $confFilesDto = $kubernetesCronjobTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('app-test-command', $content);
    }
}
