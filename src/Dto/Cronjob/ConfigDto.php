<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Trait\ConfigTrait;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class ConfigDto implements ConfigInterface
{
    use ConfigTrait;

    private ConfigSettingsDto $settings;

    /**
     * @param array<string, mixed> $config
     * @throws InvalidValueException
     */
    public function __construct(array $config)
    {
        $this->setConfigs($config);

        $this->settings = new ConfigSettingsDto($config[Configuration::SETTINGS]);
    }

    public function getSettings(): ConfigSettingsDto
    {
        return $this->settings;
    }
}
