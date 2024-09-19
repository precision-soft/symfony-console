<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\Contract\SettingInterface;
use PrecisionSoft\Symfony\Console\Dto\Trait\SettingsTrait;

class CommandSettingsDto implements SettingInterface
{
    use SettingsTrait;

    private ?bool $log;

    public function __construct(array $settings)
    {
        $this->log = null;

        $this->loadProperties($settings);
    }

    public function getLog(): ?bool
    {
        return $this->log;
    }
}
