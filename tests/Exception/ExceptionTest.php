<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Exception;

use Exception as BaseException;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;
use PrecisionSoft\Symfony\Console\Exception\SettingNotFound;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class ExceptionTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(Exception::class);
    }

    public function testExceptionExtendsBaseException(): void
    {
        $exception = new Exception('test message');

        static::assertInstanceOf(BaseException::class, $exception);
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

    public function testConfGenerateExceptionExtendsException(): void
    {
        $confGenerateException = new ConfGenerateException('generate failed');

        static::assertInstanceOf(Exception::class, $confGenerateException);
        static::assertSame('generate failed', $confGenerateException->getMessage());
    }

    public function testConfGenerateExceptionWithPreviousThrowable(): void
    {
        $previousException = new Exception('root cause');
        $confGenerateException = new ConfGenerateException('generate failed', 0, $previousException);

        static::assertSame($previousException, $confGenerateException->getPrevious());
    }

    public function testLimitExceededExceptionExtendsException(): void
    {
        $limitExceededException = new LimitExceededException('limit reached');

        static::assertInstanceOf(Exception::class, $limitExceededException);
        static::assertSame('limit reached', $limitExceededException->getMessage());
    }

    public function testInvalidConfigurationExceptionExtendsException(): void
    {
        $invalidConfigurationException = new InvalidConfigurationException('missing setting');

        static::assertInstanceOf(Exception::class, $invalidConfigurationException);
        static::assertSame('missing setting', $invalidConfigurationException->getMessage());
    }

    public function testInvalidValueExceptionExtendsException(): void
    {
        $invalidValueException = new InvalidValueException('bad value');

        static::assertInstanceOf(Exception::class, $invalidValueException);
        static::assertSame('bad value', $invalidValueException->getMessage());
    }
}
