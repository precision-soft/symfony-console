<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command\Trait;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\Trait\MemoryLimitTrait;
use PrecisionSoft\Symfony\Console\OutputStyle\Trait\SymfonyStyleTrait;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MemoryLimitTraitTestObject
{
    use MemoryLimitTrait;
    use SymfonyStyleTrait;

    public InputInterface $input;
}

/**
 * @internal
 */
final class MemoryLimitTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(MemoryLimitTraitTestObject::class, [], true);
    }

    public function testInitializeMemoryLimitSetsNull(): void
    {
        /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('memory-limit')->andReturn(false);

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeMemoryLimit');
        $reflectionMethod->invoke($traitObject);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');

        static::assertNull($memoryLimitProperty->getValue($traitObject));
    }

    public function testInitializeMemoryLimitSetsValueFromOption(): void
    {
        $originalLimit = \ini_get('memory_limit');

        try {
            /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
            $traitObject = $this->get(MemoryLimitTraitTestObject::class);

            $inputInterface = Mockery::mock(InputInterface::class);
            $inputInterface->shouldReceive('hasOption')->with('memory-limit')->andReturn(true);
            $inputInterface->shouldReceive('getOption')->with('memory-limit')->andReturn('256M');

            $reflectionProperty = new ReflectionProperty($traitObject, 'input');
            $reflectionProperty->setValue($traitObject, $inputInterface);

            $reflectionMethod = new ReflectionMethod($traitObject, 'initializeMemoryLimit');
            $reflectionMethod->invoke($traitObject);

            $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');

            static::assertSame('256M', $memoryLimitProperty->getValue($traitObject));
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }

    public function testInitializeMemoryLimitWithNullOption(): void
    {
        /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('memory-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('memory-limit')->andReturn(null);

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeMemoryLimit');
        $reflectionMethod->invoke($traitObject);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');

        static::assertNull($memoryLimitProperty->getValue($traitObject));
    }

    public function testInitializeMemoryLimitWithEmptyString(): void
    {
        /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryLimitTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('hasOption')->with('memory-limit')->andReturn(true);
        $inputInterface->shouldReceive('getOption')->with('memory-limit')->andReturn('');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeMemoryLimit');
        $reflectionMethod->invoke($traitObject);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');

        static::assertNull($memoryLimitProperty->getValue($traitObject));
    }

    public function testIsMemoryLimitReachedReturnsFalseWhenNoLimit(): void
    {
        /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryLimitTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, null);

        $reflectionMethod = new ReflectionMethod($traitObject, 'getMemoryLimitReached');
        $getMemoryLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertFalse($getMemoryLimitReached);
    }

    public function testIsMemoryLimitReachedReturnsFalseWhenUnderLimit(): void
    {
        /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryLimitTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, '10G');

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldNotReceive('warning');

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'getMemoryLimitReached');
        $getMemoryLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertFalse($getMemoryLimitReached);
    }

    public function testIsMemoryLimitReachedReturnsTrueWhenOverLimit(): void
    {
        /** @var MemoryLimitTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryLimitTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, '1');

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')->once();

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'getMemoryLimitReached');
        $getMemoryLimitReached = $reflectionMethod->invoke($traitObject);

        static::assertTrue($getMemoryLimitReached);
    }
}
