<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class ScheduleDto
{
    protected const CRON_FIELD_PATTERN = '/^(\*(\/[1-9]\d*)?|\d+(-\d+)?(\/[1-9]\d*)?)(,(\*(\/[1-9]\d*)?|\d+(-\d+)?(\/[1-9]\d*)?))*$/';

    protected readonly string $minute;
    protected readonly string $hour;
    protected readonly string $dayOfMonth;
    protected readonly string $month;
    protected readonly string $dayOfWeek;

    /**
     * @param array<string, string> $schedule
     * @throws InvalidValueException
     */
    public function __construct(array $schedule)
    {
        $this->minute = $schedule[Configuration::MINUTE];
        $this->hour = $schedule[Configuration::HOUR];
        $this->dayOfMonth = $schedule[Configuration::DAY_OF_MONTH];
        $this->month = $schedule[Configuration::MONTH];
        $this->dayOfWeek = $schedule[Configuration::DAY_OF_WEEK];

        $this->validateField('minute', $this->minute, 0, 59);
        $this->validateField('hour', $this->hour, 0, 23);
        $this->validateField('day of month', $this->dayOfMonth, 1, 31);
        $this->validateField('month', $this->month, 1, 12);
        $this->validateField('day of week', $this->dayOfWeek, 0, 7);
    }

    public function getMinute(): string
    {
        return $this->minute;
    }

    public function getHour(): string
    {
        return $this->hour;
    }

    public function getDayOfMonth(): string
    {
        return $this->dayOfMonth;
    }

    public function getMonth(): string
    {
        return $this->month;
    }

    public function getDayOfWeek(): string
    {
        return $this->dayOfWeek;
    }

    public function toCronExpression(): string
    {
        return \implode(
            ' ',
            [
                $this->minute,
                $this->hour,
                $this->dayOfMonth,
                $this->month,
                $this->dayOfWeek,
            ],
        );
    }

    /** @throws InvalidValueException */
    protected function validateField(string $fieldName, string $value, int $min, int $max): void
    {
        if ('*' === $value) {
            return;
        }

        if (1 !== \preg_match(static::CRON_FIELD_PATTERN, $value)) {
            throw new InvalidValueException(\sprintf('invalid cron %s value: `%s`', $fieldName, $value));
        }

        $parts = \explode(',', $value);

        foreach ($parts as $part) {
            $rangeOnly = \explode('/', $part)[0];

            if ('*' === $rangeOnly) {
                continue;
            }

            if (true === \str_contains($rangeOnly, '-')) {
                $rangeParts = \explode('-', $rangeOnly);

                /** @info after `str_contains('-')` + `explode('-')` we always have at least 2 parts, but individual parts may be empty strings (e.g. `5-` → ['5', '']) or non-numeric (e.g. `-5` → ['', '5']); both are invalid */
                if (
                    2 !== \count($rangeParts)
                    || '' === $rangeParts[0]
                    || '' === $rangeParts[1]
                    || 1 !== \preg_match('/^\d+$/', $rangeParts[0])
                    || 1 !== \preg_match('/^\d+$/', $rangeParts[1])
                ) {
                    throw new InvalidValueException(
                        \sprintf('cron %s range `%s` is invalid: expected start-end format with numeric bounds', $fieldName, $rangeOnly),
                    );
                }

                if ((int)$rangeParts[0] > (int)$rangeParts[1]) {
                    throw new InvalidValueException(
                        \sprintf('cron %s range `%s` is invalid: start must be less than or equal to end', $fieldName, $rangeOnly),
                    );
                }
            }

            \preg_match_all('/\d+/', $rangeOnly, $matches);

            foreach ($matches[0] as $numericValue) {
                $integerValue = (int)$numericValue;

                if ($min > $integerValue || $max < $integerValue) {
                    throw new InvalidValueException(
                        \sprintf('cron %s value `%s` is out of range (%d-%d)', $fieldName, $numericValue, $min, $max),
                    );
                }
            }
        }
    }
}
