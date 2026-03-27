<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CronjobDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class CronjobCreateCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
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

        $class = CronjobCreateCommand::class;

        return new MockDto(
            $class,
            null,
            false,
            function (MockInterface $mock) use ($config, $class): void {
                $property = new ReflectionProperty($class, 'cronjobDto');
                $property->setAccessible(true);
                $property->setValue($mock, new CronjobDto($config));

                $confGenerateServiceMock = Mockery::mock(ConfGenerateService::class);

                $confGenerateServiceMock->shouldReceive('generate')
                    ->once()
                    ->andReturn(['test']);

                $property = new ReflectionProperty($class, 'confGenerateService');
                $property->setAccessible(true);
                $property->setValue($mock, $confGenerateServiceMock);

                $mock->shouldAllowMockingProtectedMethods();

                $mock->shouldReceive('error')
                    ->byDefault()
                    ->andReturnUsing(
                        function (string $message): void {
                            throw new Exception($message);
                        },
                    );

                $mock->shouldReceive('writeln')
                    ->once();

                $mock->shouldReceive('success')
                    ->once();
            },
        );
    }

    public function test(): void
    {
        $method = new ReflectionMethod(CronjobCreateCommand::class, 'execute');
        $method->setAccessible(true);

        $input = Mockery::mock(InputInterface::class);
        $output = Mockery::mock(OutputInterface::class);

        $mock = $this->get(CronjobCreateCommand::class);

        $response = $method->invoke($mock, $input, $output);

        static::assertSame(CronjobCreateCommand::SUCCESS, $response);
    }
}
