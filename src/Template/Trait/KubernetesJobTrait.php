<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use Symfony\Component\Yaml\Yaml;

trait KubernetesJobTrait
{
    /* a manifest is three levels deep, so anything below 4 collapses an entry onto a single line */
    protected const YAML_INLINE_LEVEL = 4;

    protected const YAML_INDENT_SIZE = 4;

    /** @param array<string, mixed> $array */
    protected function convertArrayToString(array $array): string
    {
        return Yaml::dump($array, static::YAML_INLINE_LEVEL, static::YAML_INDENT_SIZE);
    }

    /** @throws InvalidValueException */
    protected function sanitize(string $input): string
    {
        $sanitizedInput = \preg_replace('/[^a-z0-9\\-]+/i', '-', $input);

        if (null === $sanitizedInput) {
            throw new InvalidValueException(\sprintf('failed to sanitize input `%s`', $input));
        }

        return \trim($sanitizedInput, '-');
    }
}
