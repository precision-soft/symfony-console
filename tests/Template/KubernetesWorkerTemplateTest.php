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
use PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class KubernetesWorkerTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(KubernetesWorkerTemplate::class, [], true);
    }

    public function testGenerateWithSingleCommand(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 2,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'test-worker',
                [
                    Configuration::COMMAND => ['bin/console', 'app:worker'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 3,
                    ],
                ],
            ),
        ];

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, $commands);

        static::assertCount(1, $confFilesDto->getFiles());
    }

    public function testGenerateWithEmptyCommands(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, []);

        static::assertCount(0, $confFilesDto->getFiles());
    }

    public function testGenerateContentContainsWorkerName(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'my-worker',
                [
                    Configuration::COMMAND => ['bin/console', 'app:my-worker'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 2,
                    ],
                ],
            ),
        ];

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('my-worker', $content);
    }

    public function testGenerateContentContainsCommand(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker-test',
                [
                    Configuration::COMMAND => ['bin/console', 'messenger:consume'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('bin/console messenger:consume', $content);
    }

    public function testGenerateContentContainsParallelism(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker-test',
                [
                    Configuration::COMMAND => ['bin/console', 'app:worker'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 5,
                    ],
                ],
            ),
        ];

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('parallelism: 5', $content);
    }

    public function testGenerateWithMultipleCommands(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker-one',
                [
                    Configuration::COMMAND => ['bin/console', 'app:one'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
            new CommandDto(
                'worker-two',
                [
                    Configuration::COMMAND => ['bin/console', 'app:two'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 2,
                    ],
                ],
            ),
        ];

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, $commands);

        static::assertCount(1, $confFilesDto->getFiles());

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('worker-one', $content);
        static::assertStringContainsString('worker-two', $content);
    }

    public function testGenerateFallsBackToConfigNumberOfProcesses(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 4,
                    Configuration::DESTINATION_FILE => 'workers.yaml',
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker-test',
                [
                    Configuration::COMMAND => ['bin/console', 'app:worker'],
                    Configuration::SETTINGS => [],
                ],
            ),
        ];

        $confFilesDto = $kubernetesWorkerTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        $content = \reset($files);
        static::assertStringContainsString('parallelism: 4', $content);
    }

    public function testMissingDestinationFileThrowsException(): void
    {
        /** @var KubernetesWorkerTemplate|MockInterface $kubernetesWorkerTemplate */
        $kubernetesWorkerTemplate = $this->get(KubernetesWorkerTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                ],
            ],
        );

        $commands = [
            new CommandDto(
                'worker',
                [
                    Configuration::COMMAND => ['bin/console', 'app:worker'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `destination file` is mandatory for kubernetes worker template');

        $kubernetesWorkerTemplate->generate($configDto, $commands);
    }
}
