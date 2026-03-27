<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Trait;

use PrecisionSoft\Symfony\Console\Exception\SettingNotFound;
use stdClass;

trait SettingsTrait
{
    private stdClass $settings;

    public function getSetting(string $setting): ?string
    {
        if (false === \property_exists($this->settings, $setting)) {
            throw new SettingNotFound($setting, static::class);
        }

        $value = $this->settings->{$setting};

        return null !== $value ? (string)$value : null;
    }

    protected function loadProperties(array $data): void
    {
        $this->settings = new stdClass();

        foreach ($data as $dataKey => $dataValue) {
            $propertyName = $this->toCamelCase($dataKey);

            if ('settings' === $propertyName) {
                continue;
            }

            if (true === \property_exists($this, $propertyName)) {
                $this->{$propertyName} = $dataValue;
                continue;
            }

            $this->settings->{$propertyName} = $dataValue;
        }
    }

    private function toCamelCase(string $input): string
    {
        $camelCaseString = \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $input)));

        return \lcfirst($camelCaseString);
    }
}
