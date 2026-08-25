<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Exception\SettingNotFoundException;
use stdClass;
use TypeError;

trait SettingsTrait
{
    protected stdClass $settings;

    /** @throws SettingNotFoundException */
    public function getSetting(string $setting): ?string
    {
        if (false === \property_exists($this->settings, $setting)) {
            throw new SettingNotFoundException($setting, static::class);
        }

        $value = $this->settings->{$setting};

        return match (true) {
            null === $value => null,
            true === $value => 'true',
            false === $value => 'false',
            default => (string)$value,
        };
    }

    /**
     * The inverse of `loadProperties()`: modelled properties and the unmodelled bag are returned under the same
     * snake case keys they were loaded from, so the result can be fed straight back into the constructor.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (\get_object_vars($this) as $propertyName => $propertyValue) {
            if ('settings' === $propertyName) {
                continue;
            }

            $data[$this->toSnakeCase($propertyName)] = $propertyValue;
        }

        foreach (\get_object_vars($this->settings) as $settingName => $settingValue) {
            $data[$this->toSnakeCase($settingName)] = $settingValue;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @throws InvalidValueException
     */
    protected function loadProperties(array $data): void
    {
        $this->settings = new stdClass();

        foreach ($data as $dataKey => $dataValue) {
            $propertyName = $this->toCamelCase($dataKey);

            if ('settings' === $propertyName) {
                continue;
            }

            if (true === \property_exists($this, $propertyName)) {
                try {
                    $this->{$propertyName} = $dataValue;
                } catch (TypeError $typeError) {
                    throw new InvalidValueException(
                        \sprintf('invalid type for property `%s` in `%s`: %s', $propertyName, static::class, $typeError->getMessage()),
                    );
                }

                continue;
            }

            if (false === \is_scalar($dataValue) && null !== $dataValue) {
                throw new InvalidValueException(
                    \sprintf('setting `%s` in `%s` must be a scalar value or null', $propertyName, static::class),
                );
            }

            $this->settings->{$propertyName} = $dataValue;
        }
    }

    protected function toCamelCase(string $input): string
    {
        $camelCaseString = \str_replace(' ', '', \ucwords(\str_replace(['_', '-'], ' ', $input)));

        return \lcfirst($camelCaseString);
    }

    protected function toSnakeCase(string $input): string
    {
        return \strtolower((string)\preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
