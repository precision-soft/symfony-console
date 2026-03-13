<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service;

use PrecisionSoft\Symfony\Console\Exception\Exception;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;

class AttributeService
{
    /**
     * @throws ReflectionException
     */
    public static function getCommandName(string $commandClass): string
    {
        $reflectionClass = new ReflectionClass($commandClass);
        $attributes = $reflectionClass->getAttributes(AsCommand::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            return $instance->name;
        }

        throw new Exception(
            \sprintf('could not compute the name for `%s`', $commandClass),
        );
    }
}
