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
final class MemoryServiceUnrecognizedFormatTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(MemoryService::class);
    }

    public function testReturnBytesThrowsExceptionForUnrecognizedFormat(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unrecognized memory value');

        MemoryService::returnBytes('abc');
    }

    public function testReturnBytesThrowsExceptionForSpecialCharacters(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unrecognized memory value');

        MemoryService::returnBytes('!@#');
    }

    public function testReturnBytesThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('unrecognized memory value');

        MemoryService::returnBytes('');
    }
}
