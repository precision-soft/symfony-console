<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Service\MemoryService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class MemoryServiceTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(MemoryService::class);
    }

    public function testReturnBytesWithPlainNumber(): void
    {
        static::assertSame(1024, MemoryService::returnBytes('1024'));
    }

    public function testReturnBytesWithUnlimitedMemory(): void
    {
        static::assertSame(-1, MemoryService::returnBytes('-1'));
    }

    public function testReturnBytesWithZeroThrowsException(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the memory value must be a positive integer or -1 (unlimited)');

        MemoryService::returnBytes('0');
    }

    public function testReturnBytesWithNegativeNumberThrowsException(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the memory value must be a positive integer or -1 (unlimited)');

        MemoryService::returnBytes('-100');
    }

    public function testReturnBytesWithKilobytes(): void
    {
        static::assertSame(1024, MemoryService::returnBytes('1K'));
    }

    public function testReturnBytesWithKilobytesLowercase(): void
    {
        static::assertSame(1024, MemoryService::returnBytes('1k'));
    }

    public function testReturnBytesWithKB(): void
    {
        static::assertSame(2048, MemoryService::returnBytes('2KB'));
    }

    public function testReturnBytesWithKBLowercase(): void
    {
        static::assertSame(2048, MemoryService::returnBytes('2kb'));
    }

    public function testReturnBytesWithMegabytes(): void
    {
        static::assertSame(128 * 1024 * 1024, MemoryService::returnBytes('128M'));
    }

    public function testReturnBytesWithMegabytesLowercase(): void
    {
        static::assertSame(256 * 1024 * 1024, MemoryService::returnBytes('256m'));
    }

    public function testReturnBytesWithMB(): void
    {
        static::assertSame(512 * 1024 * 1024, MemoryService::returnBytes('512MB'));
    }

    public function testReturnBytesWithMBLowercase(): void
    {
        static::assertSame(512 * 1024 * 1024, MemoryService::returnBytes('512mb'));
    }

    public function testReturnBytesWithGigabytes(): void
    {
        static::assertSame(1024 * 1024 * 1024, MemoryService::returnBytes('1G'));
    }

    public function testReturnBytesWithGigabytesLowercase(): void
    {
        static::assertSame(2 * 1024 * 1024 * 1024, MemoryService::returnBytes('2g'));
    }

    public function testReturnBytesWithGB(): void
    {
        static::assertSame(1024 * 1024 * 1024, MemoryService::returnBytes('1GB'));
    }

    public function testReturnBytesWithGBLowercase(): void
    {
        static::assertSame(1024 * 1024 * 1024, MemoryService::returnBytes('1gb'));
    }

    public function testReturnBytesWithWhitespace(): void
    {
        static::assertSame(128 * 1024 * 1024, MemoryService::returnBytes('  128M  '));
    }

    public function testReturnBytesWithSpaceBetweenValueAndUnit(): void
    {
        static::assertSame(128 * 1024 * 1024, MemoryService::returnBytes('128 M'));
    }

    public function testReturnBytesWithTerabytes(): void
    {
        static::assertSame(1024 * 1024 * 1024 * 1024, MemoryService::returnBytes('1T'));
    }

    public function testReturnBytesWithTB(): void
    {
        static::assertSame(1024 * 1024 * 1024 * 1024, MemoryService::returnBytes('1TB'));
    }

    public function testReturnBytesWithPetabytes(): void
    {
        static::assertSame(1024 * 1024 * 1024 * 1024 * 1024, MemoryService::returnBytes('1P'));
    }

    public function testReturnBytesWithPB(): void
    {
        static::assertSame(1024 * 1024 * 1024 * 1024 * 1024, MemoryService::returnBytes('1PB'));
    }

    public function testReturnBytesThrowsExceptionOnIntegerOverflow(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('integer overflow');

        MemoryService::returnBytes('999999999999999999PB');
    }

    public function testReturnBytesThrowsExceptionForUnrecognizedUnit(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unrecognized unit of measurement');

        MemoryService::returnBytes('1XB');
    }

    public function testConvertBytesToHumanReadableWithNegativeBytes(): void
    {
        static::assertSame('0 B', MemoryService::convertBytesToHumanReadable(-100));
    }

    public function testConvertBytesToHumanReadableWithZero(): void
    {
        static::assertSame('0 B', MemoryService::convertBytesToHumanReadable(0));
    }

    public function testConvertBytesToHumanReadableWithBytes(): void
    {
        static::assertSame('512 B', MemoryService::convertBytesToHumanReadable(512));
    }

    public function testConvertBytesToHumanReadableWithKilobytes(): void
    {
        static::assertSame('1 KB', MemoryService::convertBytesToHumanReadable(1024));
    }

    public function testConvertBytesToHumanReadableWithMegabytes(): void
    {
        static::assertSame('1 MB', MemoryService::convertBytesToHumanReadable(1024 * 1024));
    }

    public function testConvertBytesToHumanReadableWithGigabytes(): void
    {
        static::assertSame('1 GB', MemoryService::convertBytesToHumanReadable(1024 * 1024 * 1024));
    }

    public function testConvertBytesToHumanReadableWithDecimal(): void
    {
        $humanReadableBytes = MemoryService::convertBytesToHumanReadable(1536);
        static::assertSame('1.5 KB', $humanReadableBytes);
    }

    public function testGetMemoryUsageReturnsString(): void
    {
        $memoryUsage = MemoryService::getMemoryUsage();
        static::assertIsString($memoryUsage);
        static::assertMatchesRegularExpression('/^\d+\.?\d*\s(B|KB|MB|GB|TB|PB)$/', $memoryUsage);
    }

    public function testSetMemoryLimitIfNotHigherWithUnlimitedMemory(): void
    {
        $originalLimit = \ini_get('memory_limit');

        try {
            \ini_set('memory_limit', '-1');

            MemoryService::setMemoryLimitIfNotHigher('512M');

            static::assertSame('-1', \ini_get('memory_limit'));
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }

    public function testSetMemoryLimitIfNotHigherWhenNewLimitIsHigher(): void
    {
        $originalLimit = \ini_get('memory_limit');

        try {
            \ini_set('memory_limit', '64M');

            MemoryService::setMemoryLimitIfNotHigher('256M');

            static::assertSame('256M', \ini_get('memory_limit'));
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }

    public function testSetMemoryLimitIfNotHigherWhenCurrentLimitIsHigher(): void
    {
        $originalLimit = \ini_get('memory_limit');

        try {
            \ini_set('memory_limit', '512M');

            MemoryService::setMemoryLimitIfNotHigher('128M');

            static::assertSame('512M', \ini_get('memory_limit'));
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }

    public function testSetMemoryLimitIfNotHigherWhenEqual(): void
    {
        $originalLimit = \ini_get('memory_limit');

        try {
            \ini_set('memory_limit', '256M');

            MemoryService::setMemoryLimitIfNotHigher('256M');

            static::assertSame('256M', \ini_get('memory_limit'));
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }
}
