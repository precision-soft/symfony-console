<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class CronjobCreateCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(
            CronjobCreateCommand::class,
            null,
            true,
        );
    }

    public function testExecuteGeneratesConfFiles(): void
    {
        $config = [
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => CrontabTemplate::class,
                Configuration::CONF_FILES_DIR => 'generated_conf/cron',
                Configuration::LOGS_DIR => 'generated_conf/logs/cron',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'destination_file',
                    Configuration::HEARTBEAT => true,
                    Configuration::USER => 'test',
                ],
            ],
            Configuration::COMMANDS => [
                'test' => [
                    Configuration::COMMAND => ['test'],
                    Configuration::USER => 'test',
                    Configuration::LOG_FILE_NAME => 'test.log',
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '0',
                        Configuration::HOUR => '0',
                        Configuration::DAY_OF_MONTH => '0',
                        Configuration::MONTH => '0',
                        Configuration::DAY_OF_WEEK => '0',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => true,
                    ],
                ],
            ],
        ];

        $confGenerateServiceMock = Mockery::mock(ConfGenerateService::class);
        $confGenerateServiceMock->shouldReceive('generate')
            ->once()
            ->andReturn(['test']);

        $cronjobCreateCommand = new CronjobCreateCommand($confGenerateServiceMock, $config);
        $commandTester = new CommandTester($cronjobCreateCommand);

        $commandTester->execute([]);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('test', $display);
    }

    public function testExecuteWithNullConfigOutputsWarning(): void
    {
        $confGenerateServiceMock = Mockery::mock(ConfGenerateService::class);

        $cronjobCreateCommand = new CronjobCreateCommand($confGenerateServiceMock, null);
        $commandTester = new CommandTester($cronjobCreateCommand);

        $commandTester->execute([]);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('no configuration is set', $display);
    }
}
