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
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class SupervisorTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(SupervisorTemplate::class, [], true);
    }

    public function test(): void
    {
        /** @var SupervisorTemplate|MockInterface $mock */
        $mock = $this->get(SupervisorTemplate::class);

        $config = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::AUTO_START => true,
                    Configuration::AUTO_RESTART => true,
                ],
            ],
        );
        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['test'],
                    Configuration::SETTINGS => [
                        Configuration::PREFIX => 'test',
                        Configuration::USER => 'test',
                        Configuration::NUMBER_OF_PROCESSES => 1,
                    ],
                ],
            ),
        ];

        $confFilesDto = $mock->generate($config, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('[program:test-test]', $content);
        static::assertStringContainsString('command = test', $content);
        static::assertStringContainsString('numprocs = 1', $content);
        static::assertStringContainsString('autostart = true', $content);
        static::assertStringContainsString('autorestart = true', $content);
        static::assertStringContainsString('user = test', $content);
    }
}
