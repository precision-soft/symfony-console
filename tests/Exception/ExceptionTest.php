<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Exception;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;
use PrecisionSoft\Symfony\Console\Exception\SettingNotFound;

/**
 * @internal
 */
final class ExceptionTest extends TestCase
{
    public function testExceptionExtendsBaseException(): void
    {
        $exception = new Exception('test message');

        static::assertInstanceOf(\Exception::class, $exception);
        static::assertSame('test message', $exception->getMessage());
    }

    public function testSettingNotFoundExtendsException(): void
    {
        $exception = new SettingNotFound('mySetting', 'MyClass');

        static::assertInstanceOf(Exception::class, $exception);
        static::assertSame('the setting `mySetting` is not set for `MyClass`', $exception->getMessage());
    }

    public function testSettingNotFoundWithDifferentValues(): void
    {
        $exception = new SettingNotFound('timeout', 'App\\Config\\Settings');

        static::assertSame('the setting `timeout` is not set for `App\\Config\\Settings`', $exception->getMessage());
    }

    public function testLimitExceededExceptionExtendsException(): void
    {
        $limitExceededException = new LimitExceededException('limit reached');

        static::assertInstanceOf(Exception::class, $limitExceededException);
        static::assertSame('limit reached', $limitExceededException->getMessage());
    }
}
