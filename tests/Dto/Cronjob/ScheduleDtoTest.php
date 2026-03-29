<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto\Cronjob;

use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ScheduleDto;

/**
 * @internal
 */
final class ScheduleDtoTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(ScheduleDto::class);
    }

    public function testAllGetters(): void
    {
        $scheduleDto = new ScheduleDto([
            Configuration::MINUTE => '15',
            Configuration::HOUR => '3',
            Configuration::DAY_OF_MONTH => '1',
            Configuration::MONTH => '6',
            Configuration::DAY_OF_WEEK => '0',
        ]);

        static::assertSame('15', $scheduleDto->getMinute());
        static::assertSame('3', $scheduleDto->getHour());
        static::assertSame('1', $scheduleDto->getDayOfMonth());
        static::assertSame('6', $scheduleDto->getMonth());
        static::assertSame('0', $scheduleDto->getDayOfWeek());
    }

    public function testWildcardValues(): void
    {
        $scheduleDto = new ScheduleDto([
            Configuration::MINUTE => '*',
            Configuration::HOUR => '*',
            Configuration::DAY_OF_MONTH => '*',
            Configuration::MONTH => '*',
            Configuration::DAY_OF_WEEK => '*',
        ]);

        static::assertSame('*', $scheduleDto->getMinute());
        static::assertSame('*', $scheduleDto->getHour());
        static::assertSame('*', $scheduleDto->getDayOfMonth());
        static::assertSame('*', $scheduleDto->getMonth());
        static::assertSame('*', $scheduleDto->getDayOfWeek());
    }

    public function testComplexScheduleExpressions(): void
    {
        $scheduleDto = new ScheduleDto([
            Configuration::MINUTE => '*/5',
            Configuration::HOUR => '1-5',
            Configuration::DAY_OF_MONTH => '1,15',
            Configuration::MONTH => '1-6',
            Configuration::DAY_OF_WEEK => '1-5',
        ]);

        static::assertSame('*/5', $scheduleDto->getMinute());
        static::assertSame('1-5', $scheduleDto->getHour());
        static::assertSame('1,15', $scheduleDto->getDayOfMonth());
        static::assertSame('1-6', $scheduleDto->getMonth());
        static::assertSame('1-5', $scheduleDto->getDayOfWeek());
    }
}
