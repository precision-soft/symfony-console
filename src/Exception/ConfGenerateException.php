<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Exception;

use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class ConfGenerateException extends Exception
{
    /**
     * @param array<string, mixed> $context
     */
    public static function from(
        Throwable $throwable,
        array $context = [],
        ?string $message = null,
    ): static {
        return new static(
            $message ?? $throwable->getMessage(),
            (int)$throwable->getCode(),
            $throwable,
            $context,
        );
    }
}
