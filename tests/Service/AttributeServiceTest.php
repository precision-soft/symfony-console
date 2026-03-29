<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service;

use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Service\AttributeService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'test:attribute-service')]
class AttributeServiceTestCommand extends Command {}

/**
 * @internal
 */
final class AttributeServiceTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(AttributeService::class);
    }

    public function testGetCommandNameReturnsNameForClassWithAttribute(): void
    {
        $commandName = AttributeService::getCommandName(AttributeServiceTestCommand::class);

        static::assertSame('test:attribute-service', $commandName);
    }

    public function testGetCommandNameThrowsExceptionForClassWithoutAttribute(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('could not compute the name for');

        AttributeService::getCommandName(self::class);
    }
}
