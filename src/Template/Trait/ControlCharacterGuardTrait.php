<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;

trait ControlCharacterGuardTrait
{
    /**
     * The text templates are line oriented: a newline inside a value ends the directive and starts another one, and a
     * carriage return or a tab is read differently by every consumer, so none of them may reach a generated file.
     *
     * @throws InvalidConfigurationException
     */
    protected function rejectControlCharacters(string $name, string $value): string
    {
        if (1 === \preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidConfigurationException(
                \sprintf('the `%s` must not contain control characters, got `%s`', $name, \addcslashes($value, "\0..\37\177")),
            );
        }

        return $value;
    }
}
