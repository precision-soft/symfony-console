<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command\Trait;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\Trait\InstancesTrait;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstancesTraitTestObject
{
    use InstancesTrait;

    public InputInterface $input;
}

#[AsCommand(name: 'test:instances-trait')]
class InstancesTraitConfigureTestCommand extends Command
{
    use InstancesTrait;

    protected function configure(): void
    {
        $this->configureInstances();
    }

    protected function execute(InputInterface $inputInterface, OutputInterface $outputInterface): int
    {
        return self::SUCCESS;
    }
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

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('2');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $computedInstances = $reflectionMethod->invoke($traitObject);

        static::assertSame([3, 2], $computedInstances);
    }

    public function testComputeInstancesThrowsExceptionWhenMaxInstancesIsZero(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('0');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid instances and instance index provided');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenInstanceIndexIsZero(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('0');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid instances and instance index provided');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenIndexExceedsMax(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('5');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid instances and instance index provided');

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testComputeInstancesThrowsExceptionWhenNegativeMaxInstances(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('-1');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $this->expectException(InvalidConfigurationException::class);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $reflectionMethod->invoke($traitObject);
    }

    public function testFormatMessageWithInstances(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('5');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('3');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'formatMessageWithInstances');
        $formattedMessage = $reflectionMethod->invoke($traitObject, 'Processing items');

        static::assertSame('[3/5] Processing items', $formattedMessage);
    }

    public function testComputeInstancesWithIndexEqualToMax(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('3');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('3');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $computedInstances = $reflectionMethod->invoke($traitObject);

        static::assertSame([3, 3], $computedInstances);
    }

    public function testConfigureInstancesRegistersOptions(): void
    {
        $instancesTraitConfigureTestCommand = new InstancesTraitConfigureTestCommand();

        $maxInstancesDefinition = $instancesTraitConfigureTestCommand->getDefinition()->getOption('max-instances');
        $instanceIndexDefinition = $instancesTraitConfigureTestCommand->getDefinition()->getOption('instance-index');

        static::assertTrue($maxInstancesDefinition->isValueOptional());
        static::assertSame(1, $maxInstancesDefinition->getDefault());
        static::assertTrue($instanceIndexDefinition->isValueOptional());
        static::assertSame(1, $instanceIndexDefinition->getDefault());
    }

    public function testComputeInstancesSingleInstance(): void
    {
        /** @var InstancesTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(InstancesTraitTestObject::class);

        $inputInterface = Mockery::mock(InputInterface::class);
        $inputInterface->shouldReceive('getOption')->with('max-instances')->andReturn('1');
        $inputInterface->shouldReceive('getOption')->with('instance-index')->andReturn('1');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $inputInterface);

        $reflectionMethod = new ReflectionMethod($traitObject, 'computeInstances');
        $computedInstances = $reflectionMethod->invoke($traitObject);

        static::assertSame([1, 1], $computedInstances);
    }
}
