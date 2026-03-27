<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\OutputStyle;

use PrecisionSoft\Symfony\Console\OutputStyle\SymfonyStyle;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionProperty;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle as BaseSymfonyStyle;

/**
 * @internal
 */
final class SymfonyStyleTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(SymfonyStyle::class, [
            new MockDto(InputInterface::class, null, false, function ($inputInterfaceMock): void {
                $inputInterfaceMock->shouldReceive('isInteractive')->andReturn(false);
                $inputInterfaceMock->shouldReceive('hasArgument')->andReturn(false);
            }),
            new MockDto(OutputInterface::class, null, false, function ($outputInterfaceMock): void {
                $outputInterfaceMock->shouldReceive('getFormatter')->andReturn(new OutputFormatter());
                $outputInterfaceMock->shouldReceive('getVerbosity')->andReturn(OutputInterface::VERBOSITY_NORMAL);
                $outputInterfaceMock->shouldReceive('isDecorated')->andReturn(false);
            }),
        ]);
    }

    public function testConstructorInitializesStyle(): void
    {
        $symfonyStyle = $this->get(SymfonyStyle::class);

        $reflectionProperty = new ReflectionProperty($symfonyStyle, 'style');
        $reflectionProperty->setAccessible(true);

        static::assertInstanceOf(BaseSymfonyStyle::class, $reflectionProperty->getValue($symfonyStyle));
    }
}
