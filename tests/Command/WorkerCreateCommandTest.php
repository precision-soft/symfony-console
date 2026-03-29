<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class WorkerCreateCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(
            WorkerCreateCommand::class,
            null,
            true,
        );
    }

    public function testExecuteGeneratesConfFiles(): void
    {
        $config = [
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => SupervisorTemplate::class,
                Configuration::CONF_FILES_DIR => 'generated_conf/worker',
                Configuration::LOGS_DIR => 'generated_conf/logs/worker',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 1,
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                    Configuration::PREFIX => 'prefix',
                    Configuration::USER => 'user',
                ],
            ],
            Configuration::COMMANDS => [
                'test' => [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::NUMBER_OF_PROCESSES => 1,
                        Configuration::AUTO_START => true,
                        Configuration::AUTO_RESTART => true,
                    ],
                ],
            ],
        ];

        $confGenerateServiceMock = Mockery::mock(ConfGenerateService::class);
        $confGenerateServiceMock->shouldReceive('generate')
            ->once()
            ->andReturn(['test']);

        $workerCreateCommand = new WorkerCreateCommand($confGenerateServiceMock, $config);
        $commandTester = new CommandTester($workerCreateCommand);

        $commandTester->execute([]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('test', $display);
    }

    public function testExecuteWithNullConfigOutputsWarning(): void
    {
        $confGenerateServiceMock = Mockery::mock(ConfGenerateService::class);

        $workerCreateCommand = new WorkerCreateCommand($confGenerateServiceMock, null);
        $commandTester = new CommandTester($workerCreateCommand);

        $commandTester->execute([]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('no configuration is set', $display);
    }
}
