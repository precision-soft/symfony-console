<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command\Trait;

use Mockery;
use Mockery\MockInterface;
use PrecisionSoft\Symfony\Console\Command\Trait\MemoryAndTimeLimitsTrait;
use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;
use PrecisionSoft\Symfony\Console\OutputStyle\Trait\SymfonyStyleTrait;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MemoryAndTimeLimitsTraitTestObject
{
    use MemoryAndTimeLimitsTrait;
    use SymfonyStyleTrait;

    public InputInterface $input;
}

/**
 * @internal
 */
final class MemoryAndTimeLimitsTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(MemoryAndTimeLimitsTraitTestObject::class, [], true);
    }

    public function testInitializeMemoryAndTimeLimits(): void
    {
        $originalLimit = \ini_get('memory_limit');

        /** @var MemoryAndTimeLimitsTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryAndTimeLimitsTraitTestObject::class);

        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('hasOption')->with('memory-limit')->andReturn(true);
        $input->shouldReceive('getOption')->with('memory-limit')->andReturn('512M');
        $input->shouldReceive('hasOption')->with('time-limit')->andReturn(true);
        $input->shouldReceive('getOption')->with('time-limit')->andReturn('600');

        $reflectionProperty = new ReflectionProperty($traitObject, 'input');
        $reflectionProperty->setValue($traitObject, $input);

        $reflectionMethod = new ReflectionMethod($traitObject, 'initializeMemoryAndTimeLimits');
        $reflectionMethod->invoke($traitObject);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');

        static::assertSame('512M', $memoryLimitProperty->getValue($traitObject));
        static::assertSame(600, $timeLimitProperty->getValue($traitObject));

        \ini_set('memory_limit', $originalLimit);
    }

    public function testDidScriptReachedLimitsReturnsFalseWhenNoLimitsReached(): void
    {
        /** @var MemoryAndTimeLimitsTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryAndTimeLimitsTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, null);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, null);

        $reflectionMethod = new ReflectionMethod($traitObject, 'hasScriptReachedLimits');
        $result = $reflectionMethod->invoke($traitObject);

        static::assertFalse($result);
    }

    public function testDidScriptReachedLimitsReturnsTrueWhenTimeLimitReached(): void
    {
        /** @var MemoryAndTimeLimitsTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryAndTimeLimitsTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, null);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, 0);

        $startTimeProperty = new ReflectionProperty($traitObject, 'startTime');
        $startTimeProperty->setValue($traitObject, \time() - 10);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')->once();

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'hasScriptReachedLimits');
        $result = $reflectionMethod->invoke($traitObject);

        static::assertTrue($result);
    }

    public function testDidScriptReachedLimitsReturnsTrueWhenMemoryLimitReached(): void
    {
        /** @var MemoryAndTimeLimitsTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryAndTimeLimitsTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, '1');

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, null);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')->once();

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $reflectionMethod = new ReflectionMethod($traitObject, 'hasScriptReachedLimits');
        $result = $reflectionMethod->invoke($traitObject);

        static::assertTrue($result);
    }

    public function testStopScriptIfLimitsReachedDoesNothingWhenNoLimits(): void
    {
        $this->expectNotToPerformAssertions();

        /** @var MemoryAndTimeLimitsTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryAndTimeLimitsTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, null);

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, null);

        $reflectionMethod = new ReflectionMethod($traitObject, 'stopScriptIfLimitsReached');
        $reflectionMethod->invoke($traitObject);
    }

    public function testStopScriptIfLimitsReachedThrowsRuntimeException(): void
    {
        /** @var MemoryAndTimeLimitsTraitTestObject|MockInterface $traitObject */
        $traitObject = $this->get(MemoryAndTimeLimitsTraitTestObject::class);

        $memoryLimitProperty = new ReflectionProperty($traitObject, 'memoryLimit');
        $memoryLimitProperty->setValue($traitObject, '1');

        $timeLimitProperty = new ReflectionProperty($traitObject, 'timeLimit');
        $timeLimitProperty->setValue($traitObject, null);

        $symfonyStyle = Mockery::mock(SymfonyStyle::class);
        $symfonyStyle->shouldReceive('warning')->once();

        $styleProperty = new ReflectionProperty($traitObject, 'style');
        $styleProperty->setValue($traitObject, $symfonyStyle);

        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('memory or time limit exceeded');

        $reflectionMethod = new ReflectionMethod($traitObject, 'stopScriptIfLimitsReached');
        $reflectionMethod->invoke($traitObject);
    }
}
