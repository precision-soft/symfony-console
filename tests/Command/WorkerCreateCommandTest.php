<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Worker\WorkerDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class WorkerCreateCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
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

        $class = WorkerCreateCommand::class;

        return new MockDto(
            $class,
            null,
            false,
            function (MockInterface $mock) use ($config, $class): void {
                $property = new ReflectionProperty($class, 'workerDto');
                $property->setAccessible(true);
                $property->setValue($mock, new WorkerDto($config));

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
        $method = new ReflectionMethod(WorkerCreateCommand::class, 'execute');
        $method->setAccessible(true);

        $input = Mockery::mock(InputInterface::class);
        $output = Mockery::mock(OutputInterface::class);

        $mock = $this->get(WorkerCreateCommand::class);

        $response = $method->invoke($mock, $input, $output);

        static::assertSame(WorkerCreateCommand::SUCCESS, $response);
    }
}
