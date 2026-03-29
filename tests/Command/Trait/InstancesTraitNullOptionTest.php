<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command\Trait;

use Mockery;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class InstancesTraitNullOptionTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(InstancesTraitTestObject::class, [], true);
    }

    public function testComputeInstancesThrowsExceptionWhenMaxInstancesIsNull(): void
    {
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn(null);
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('max-instances and instance-index options are required');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenInstanceIndexIsNull(): void
    {
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn(null);

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('max-instances and instance-index options are required');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenBothOptionsAreNull(): void
    {
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn(null);
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn(null);

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('max-instances and instance-index options are required');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }
}
