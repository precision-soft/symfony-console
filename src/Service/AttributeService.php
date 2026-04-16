<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service;

use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

class AttributeService
{
    private function __construct() {}

    /**
     * @param class-string $commandClass
     * @throws InvalidConfigurationException
     */
    public static function getCommandName(string $commandClass): string
    {
        $reflectionClass = new ReflectionClass($commandClass);
        $reflectionAttributes = $reflectionClass->getAttributes(AsCommand::class);

        foreach ($reflectionAttributes as $reflectionAttribute) {
            return $reflectionAttribute->newInstance()->name;
        }

        throw new InvalidConfigurationException(
            \sprintf('could not compute the name for `%s`', $commandClass),
        );
    }
}
