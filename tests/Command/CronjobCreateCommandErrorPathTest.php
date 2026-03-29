<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class CronjobCreateCommandErrorPathTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(
            CronjobCreateCommand::class,
            null,
            true,
        );
    }

    public function testExecuteWithNullConfig(): void
    {
        $confGenerateService = Mockery::mock(ConfGenerateService::class);

        $cronjobCreateCommand = new CronjobCreateCommand($confGenerateService, null);

        $commandTester = new CommandTester($cronjobCreateCommand);
        $commandTester->execute([]);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('no configuration is set', $commandTester->getDisplay());
    }

    public function testExecuteWithExceptionReturnsFailure(): void
    {
        $confGenerateService = Mockery::mock(ConfGenerateService::class);

        $confGenerateService->shouldReceive('generate')
            ->once()
            ->andThrow(new ConfGenerateException('test error message'));

        $config = [
            'config' => [
                'template_class' => 'SomeTemplate',
                'conf_files_dir' => 'generated_conf/cron',
                'logs_dir' => 'generated_conf/logs/cron',
                'settings' => [
                    'destination_file' => 'crontab',
                    'heartbeat' => true,
                    'log' => true,
                    'user' => null,
                ],
            ],
            'commands' => [
                'test' => [
                    'command' => ['test'],
                    'user' => null,
                    'log_file_name' => 'test.log',
                    'schedule' => [
                        'minute' => '*',
                        'hour' => '*',
                        'day_of_month' => '*',
                        'month' => '*',
                        'day_of_week' => '*',
                    ],
                    'settings' => [
                        'log' => true,
                    ],
                ],
            ],
        ];

        $cronjobCreateCommand = new CronjobCreateCommand($confGenerateService, $config);

        $commandTester = new CommandTester($cronjobCreateCommand);
        $commandTester->execute([]);

        static::assertSame(CronjobCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('test error message', $commandTester->getDisplay());
    }
}
