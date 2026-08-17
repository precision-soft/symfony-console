<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Utility;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;

/* named rather than anonymous: an anonymous class can only be typed `object` for static analysis */

class KubernetesJobTraitObject
{
    use KubernetesJobTrait;

    /** @param array<string, mixed> $array */
    public function dump(array $array): string
    {
        return $this->convertArrayToString($array);
    }

    /** @throws InvalidValueException */
    public function sanitizeInput(string $input): string
    {
        return $this->sanitize($input);
    }
}
