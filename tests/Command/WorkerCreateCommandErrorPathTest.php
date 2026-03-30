<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class WorkerCreateCommandErrorPathTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(
            WorkerCreateCommand::class,
            null,
            true,
        );
    }

    public function testExecuteWithNullConfig(): void
    {
        $confGenerateService = Mockery::mock(ConfGenerateService::class);

        $workerCreateCommand = new WorkerCreateCommand($confGenerateService, null);

        $commandTester = new CommandTester($workerCreateCommand);
        $commandTester->execute([]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('no configuration is set', $commandTester->getDisplay());
    }

    public function testExecuteWithExceptionReturnsFailure(): void
    {
        $confGenerateService = Mockery::mock(ConfGenerateService::class);

        $confGenerateService->shouldReceive('generate')
            ->once()
            ->andThrow(new ConfGenerateException('test error message'));

        $config = [
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'SomeTemplate',
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

        $workerCreateCommand = new WorkerCreateCommand($confGenerateService, $config);

        $commandTester = new CommandTester($workerCreateCommand);
        $commandTester->execute([]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('test error message', $commandTester->getDisplay());
    }
}
