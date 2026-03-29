<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command\Trait;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\Trait\TimeLimitTrait;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\OutputStyle\Trait\SymfonyStyleTrait;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TimeLimitTraitTestObject
{
    use TimeLimitTrait;
    use SymfonyStyleTrait;

    public InputInterface $input;
}

/**
 * @internal
 */
final class TimeLimitTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(TimeLimitTraitTestObject::class, [], true);
    }

    public function testInitializeTimeLimitSetsStartTimeAndNull(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(false);

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $startTimeProperty = new ReflectionProperty($traitObject, 'startTime');

        static::assertNull($timeLimitProperty->getValue($traitObject));
        static::assertIsInt($startTimeProperty->getValue($traitObject));
    }

    public function testInitializeTimeLimitSetsValueFromOption(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('time-limit')->andReturn('300');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');

        static::assertSame(300, $timeLimitProperty->getValue($traitObject));
    }

    public function testInitializeTimeLimitWithNullOption(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('time-limit')->andReturn(null);

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');

        static::assertNull($timeLimitProperty->getValue($traitObject));
    }

    public function testInitializeTimeLimitWithEmptyString(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('time-limit')->andReturn('');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');

        static::assertNull($timeLimitProperty->getValue($traitObject));
    }

    public function testInitializeTimeLimitThrowsOnNonNumericValue(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('time-limit')->andReturn('abc');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the `--time-limit` option must be a positive integer, `abc` given');

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);
    }

    public function testInitializeTimeLimitThrowsOnZeroValue(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('time-limit')->andReturn('0');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the `--time-limit` option must be a positive integer, `0` given');

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);
    }

    public function testInitializeTimeLimitThrowsOnNegativeValue(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('time-limit')->andReturn('-5');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the `--time-limit` option must be a positive integer, `-5` given');

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeTimeLimit');
        $reflectionMethod->invoke($traitObject);
    }

    public function testIsTimeLimitReachedReturnsFalseWhenNoLimit(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, null);

        $reflectionMethod = new ReflectionMethod($traitObject, 'isTimeLimitReached');
        $isTimeLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertFalse($isTimeLimitReached);
    }

    public function testIsTimeLimitReachedReturnsFalseWhenUnderLimit(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, 3600);

        $startTimeProperty = new ReflectionProperty($traitObject, 'startTime');
        $startTimeProperty->setValue($traitObject, \time());

        $reflectionMethod = new ReflectionMethod($traitObject, 'isTimeLimitReached');
        $isTimeLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertFalse($isTimeLimitReached);
    }

    public function testIsTimeLimitReachedReturnsTrueWhenOverLimit(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, 10);

        $startTimeProperty = new ReflectionProperty($traitObject, 'startTime');
        $startTimeProperty->setValue($traitObject, \time() - 20);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')->once();

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'isTimeLimitReached');
        $isTimeLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertTrue($isTimeLimitReached);
    }

    public function testIsTimeLimitReachedReturnsTrueWhenExactlyAtLimit(): void
    {
        /** @var TimeLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(TimeLimitTraitTestObject::class);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, 0);

        $startTimeProperty = new ReflectionProperty($traitObject, 'startTime');
        $startTimeProperty->setValue($traitObject, \time());

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')->once();

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'isTimeLimitReached');
        $isTimeLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertTrue($isTimeLimitReached);
    }
}
