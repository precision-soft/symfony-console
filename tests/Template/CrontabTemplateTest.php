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
use PrecisionSoft\Symfony\Console\Template\CrontabTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class CrontabTemplateTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(CrontabTemplate::class, [], true);
    }

    public function test(): void
    {
        /** @var CrontabTemplate|MockInterface $crontabTemplate */
        $crontabTemplate = $this->get(CrontabTemplate::class);

        $configDto = new ConfigDto(
            [
                Configuration::TEMPLATE_CLASS => 'test',
                Configuration::CONF_FILES_DIR => 'test',
                Configuration::LOGS_DIR => 'test',
                Configuration::SETTINGS => [
                    Configuration::DESTINATION_FILE => 'test',
                    Configuration::HEARTBEAT => true,
                ],
            ],
        );
        $commands = [
            new CommandDto(
                'test',
                [
                    Configuration::COMMAND => ['test'],
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
            ),
        ];

        $confFilesDto = $crontabTemplate->generate($configDto, $commands);

        $files = $confFilesDto->getFiles();
        static::assertCount(1, $files);

        $content = \reset($files);
        static::assertStringContainsString('* * * * * test', $content);
        static::assertStringContainsString('GENERATED FILE', $content);
        static::assertStringContainsString(">> 'test/test.log' 2>&1", $content);
        static::assertStringContainsString('/bin/touch', $content);
    }
}
