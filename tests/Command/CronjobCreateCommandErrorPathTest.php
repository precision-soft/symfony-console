<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
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
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => 'SomeTemplate',
                Configuration::CONF_FILES_DIR => 'generated_conf/cron',
                Configuration::LOGS_DIR => 'generated_conf/logs/cron',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'crontab',
                    Configuration::HEARTBEAT => true,
                    Configuration::LOG => true,
                    Configuration::USER => null,
                ],
            ],
            Configuration::COMMANDS => [
                'test' => [
                    Configuration::COMMAND => ['test'],
                    Configuration::USER => null,
                    Configuration::LOG_FILE_NAME => 'test.log',
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
            ],
        ];

        $cronjobCreateCommand = new CronjobCreateCommand($confGenerateService, $config);

        $commandTester = new CommandTester($cronjobCreateCommand);
        $commandTester->execute([]);

        static::assertSame(CronjobCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('test error message', $commandTester->getDisplay());
    }
}
