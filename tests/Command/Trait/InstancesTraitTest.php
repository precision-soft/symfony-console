<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command\Trait;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\Trait\InstancesTrait;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;

class InstancesTraitTestObject
{
    use InstancesTrait;

    public InputInterface $input;
}

/**
 * @internal
 */
final class InstancesTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(InstancesTraitTestObject::class, [], true);
    }

    public function testComputeInstancesReturnsValidValues(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('2');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $result = $reflectionMethod->invoke($traitObject);

        static::assertSame([3, 2], $result);
    }

    public function testComputeInstancesThrowsExceptionWhenMaxInstancesIsZero(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('0');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid instances and instance index provided');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenInstanceIndexIsZero(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('0');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid instances and instance index provided');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenIndexExceedsMax(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('5');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid instances and instance index provided');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenNegativeMaxInstances(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('-1');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $this->expectException(Exception::class);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testFormatMessageWithInstances(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('5');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('3');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $reflectionMethod = new ReflectionMethod($traitObject, 'formatMessageWithInstances');
        $result = $reflectionMethod->invoke($traitObject, 'Processing items');

        static::assertSame('[3/5] Processing items', $result);
    }

    public function testComputeInstancesWithIndexEqualToMax(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('3');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $result = $reflectionMethod->invoke($traitObject);

        static::assertSame([3, 3], $result);
    }

    public function testComputeInstancesSingleInstance(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->with('max-instances')->andReturn('1');
        $input->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $result = $reflectionMethod->invoke($traitObject);

        static::assertSame([1, 1], $result);
    }
}
