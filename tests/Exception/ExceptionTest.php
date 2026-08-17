<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Exception;

use Exception as BaseException;
use PrecisionSoft\Symfony\Console\Contract\ExceptionInterface;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;
use PrecisionSoft\Symfony\Console\Exception\SettingNotFoundException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use TypeError;

class ConfGenerateExceptionStub extends ConfGenerateException {}

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

    public function testSettingNotFoundExceptionExtendsException(): void
    {
        $settingNotFoundException = new SettingNotFoundException('mySetting', 'MyClass');

        static::assertInstanceOf(Exception::class, $settingNotFoundException);
        static::assertSame('the setting `mySetting` is not set for `MyClass`', $settingNotFoundException->getMessage());
    }

    public function testSettingNotFoundExceptionWithDifferentValues(): void
    {
        $settingNotFoundException = new SettingNotFoundException('timeout', 'App\\Config\\Settings');

        static::assertSame('the setting `timeout` is not set for `App\\Config\\Settings`', $settingNotFoundException->getMessage());
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

    public function testExceptionImplementsExceptionInterface(): void
    {
        static::assertInstanceOf(ExceptionInterface::class, new Exception('test message'));
    }

    public function testContextDefaultsToAnEmptyArray(): void
    {
        static::assertSame([], (new Exception('test message'))->getContext());
        static::assertSame([], (new Exception('test message', 0, null, null))->getContext());
    }

    public function testContextIsReadBackFromTheConstructor(): void
    {
        $exception = new Exception('test message', 0, null, ['destinationDir' => '/tmp/conf', 'attempt' => 2]);

        static::assertSame(['destinationDir' => '/tmp/conf', 'attempt' => 2], $exception->getContext());
    }

    public function testSetContextReplacesTheContextAndIsFluent(): void
    {
        $exception = new Exception('test message', 0, null, ['first' => 1]);

        static::assertSame($exception, $exception->setContext(['second' => 2]));
        static::assertSame(['second' => 2], $exception->getContext());

        $exception->setContext(null);

        static::assertSame([], $exception->getContext());
    }

    public function testFromKeepsTheOriginAsPreviousAndCarriesContext(): void
    {
        $typeError = new TypeError('argument #1 must be of type string, int given', 7);

        $confGenerateException = ConfGenerateException::from($typeError, ['templateClass' => 'MyTemplate']);

        static::assertSame('argument #1 must be of type string, int given', $confGenerateException->getMessage());
        static::assertSame(7, $confGenerateException->getCode());
        static::assertSame($typeError, $confGenerateException->getPrevious());
        static::assertSame(['templateClass' => 'MyTemplate'], $confGenerateException->getContext());
    }

    public function testFromAcceptsAnOverridingMessage(): void
    {
        $baseException = new BaseException('root cause');

        $confGenerateException = ConfGenerateException::from($baseException, [], 'generate failed');

        static::assertSame('generate failed', $confGenerateException->getMessage());
        static::assertSame($baseException, $confGenerateException->getPrevious());
        static::assertSame([], $confGenerateException->getContext());
    }

    public function testFromReturnsTheLateStaticBoundClass(): void
    {
        $confGenerateException = ConfGenerateExceptionStub::from(new BaseException('root cause'));

        static::assertInstanceOf(ConfGenerateExceptionStub::class, $confGenerateException);
    }

    public function testTheConstructorDefaultsToAnEmptyMessageZeroCodeAndNoPrevious(): void
    {
        $exception = new Exception();

        static::assertSame('', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
        static::assertSame([], $exception->getContext());
    }
}
