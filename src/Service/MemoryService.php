<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class MemoryService
{
    private function __construct() {}

    public static function setMemoryLimitIfNotHigher(string $newLimit): void
    {
        $currentLimit = \ini_get('memory_limit');

        if (false === $currentLimit || '-1' === $currentLimit) {
            return;
        }

        if (self::returnBytes($currentLimit) < self::returnBytes($newLimit)) {
            \ini_set('memory_limit', $newLimit);
        }
    }

    public static function getMemoryUsage(): string
    {
        $bytes = \memory_get_usage(true);

        return self::convertBytesToHumanReadable($bytes);
    }

    public static function convertBytesToHumanReadable(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        if (0 >= $bytes) {
            return '0 ' . $units[0];
        }

        $unitIndex = \min((int)\floor(\log($bytes, 1024)), \count($units) - 1);

        return \round($bytes / 1024 ** $unitIndex, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * @throws InvalidValueException
     */
    public static function returnBytes(string $value): int
    {
        $value = \trim($value);

        if (1 === \preg_match('/^-?\d+$/', $value)) {
            $integerValue = (int)$value;

            if (0 >= $integerValue && '-1' !== $value) {
                throw new InvalidValueException(\sprintf('the memory value must be a positive integer or -1 (unlimited), `%s` given', $value));
            }

            return $integerValue;
        }

        if (1 !== \preg_match('#^([0-9]{1,18})[\s]*([a-z]{1,2})$#i', $value, $matches)) {
            throw new InvalidValueException(\sprintf('unrecognized memory value `%s`', $value));
        }

        $numericValue = (int)$matches[1];
        $unitOfMeasurement = \strtolower($matches[2]);

        $multiplier = match ($unitOfMeasurement) {
            'p', 'pb' => 1024 * 1024 * 1024 * 1024 * 1024,
            't', 'tb' => 1024 * 1024 * 1024 * 1024,
            'g', 'gb' => 1024 * 1024 * 1024,
            'm', 'mb' => 1024 * 1024,
            'k', 'kb' => 1024,
            default => throw new InvalidValueException(\sprintf('unrecognized unit of measurement `%s`', $unitOfMeasurement)),
        };

        if ((int)(\PHP_INT_MAX / $multiplier) < $numericValue) {
            throw new InvalidValueException(\sprintf('the memory value `%s` causes integer overflow', $value));
        }

        return $numericValue * $multiplier;
    }
}
