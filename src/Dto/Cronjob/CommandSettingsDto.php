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

    private ?bool $log = null;

    /** @param array<string, mixed> $settings */
    public function __construct(array $settings)
    {
        $this->loadProperties($settings);
    }

    public function getLog(): ?bool
    {
        return $this->log;
    }
}
