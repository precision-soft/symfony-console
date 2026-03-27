<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\OutputStyle\Trait;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\OutputStyle\Trait\SymfonyStyleTrait;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Style\SymfonyStyle;

class SymfonyStyleTraitTestObject
{
    use SymfonyStyleTrait;
}

/**
 * @internal
 */
final class SymfonyStyleTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(SymfonyStyleTraitTestObject::class, [], true);
    }

    public function testWriteln(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('writeln')
            ->once()
            ->with(Mockery::pattern('/^\[.*\]\[.*\] test message$/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'writeln');
        $reflectionMethod->invoke($traitObject, 'test message');

        static::assertTrue(true);
    }

    public function testError(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/error message/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'error');
        $reflectionMethod->invoke($traitObject, 'error message');

        static::assertTrue(true);
    }

    public function testErrorWithThrowable(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/error message.*Exception/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $exception = new Exception('inner error');

        $reflectionMethod = new ReflectionMethod($traitObject, 'error');
        $reflectionMethod->invoke($traitObject, 'error message', $exception);

        static::assertTrue(true);
    }

    public function testErrorWithThrowableAndExposeTrace(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/error message.*Exception/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $exception = new Exception('inner error');

        $reflectionMethod = new ReflectionMethod($traitObject, 'error');
        $reflectionMethod->invoke($traitObject, 'error message', $exception, true);

        static::assertTrue(true);
    }

    public function testInfo(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/info message/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'info');
        $reflectionMethod->invoke($traitObject, 'info message');

        static::assertTrue(true);
    }

    public function testWarning(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/warning message/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'warning');
        $reflectionMethod->invoke($traitObject, 'warning message');

        static::assertTrue(true);
    }

    public function testSuccess(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('success')
            ->once()
            ->with(Mockery::pattern('/success message/'));

        $reflectionProperty = new ReflectionProperty($traitObject, 'style');
        $reflectionProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'success');
        $reflectionMethod->invoke($traitObject, 'success message');

        static::assertTrue(true);
    }

    public function testFormatIncludesTimestampAndMemory(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $reflectionMethod = new ReflectionMethod($traitObject, 'format');
        $result = $reflectionMethod->invoke($traitObject, 'test');

        static::assertMatchesRegularExpression('/^\[\d{2}:\d{2}:\d{2}\]/', $result);
        static::assertMatchesRegularExpression('/\[[\d.]+ (B|KB|MB|GB|TB|PB)\]/', $result);
        static::assertStringContainsString('test', $result);
    }

    public function testFormatThrowableWithoutTrace(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $exception = new Exception('test error');

        $reflectionMethod = new ReflectionMethod($traitObject, 'formatThrowable');
        $result = $reflectionMethod->invoke($traitObject, $exception);

        static::assertStringContainsString('Exception', $result);
        static::assertStringContainsString($exception->getFile(), $result);
        static::assertStringContainsString((string)$exception->getLine(), $result);
    }

    public function testFormatThrowableWithTrace(): void
    {
        /** @var SymfonyStyleTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(SymfonyStyleTraitTestObject::class);

        $exception = new Exception('test error');

        $reflectionMethod = new ReflectionMethod($traitObject, 'formatThrowable');
        $result = $reflectionMethod->invoke($traitObject, $exception, true);

        static::assertStringContainsString('Exception', $result);
        static::assertStringContainsString($exception->getTraceAsString(), $result);
    }
}
