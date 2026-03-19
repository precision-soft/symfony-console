<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Worker;

use PrecisionSoft\Symfony\Console\Contract\SettingInterface;
use PrecisionSoft\Symfony\Console\Dto\Trait\SettingsTrait;
use PrecisionSoft\Symfony\Console\Dto\Trait\SupervisorSettingsTrait;

class ConfigSettingsDto implements SettingInterface
{
    use SettingsTrait;
    use SupervisorSettingsTrait;

    private ?string $destinationFile = null;

    public function __construct(array $settings)
    {
        $this->initSupervisorSettings();

        $this->loadProperties($settings);
    }

    public function getDestinationFile(): ?string
    {
        return $this->destinationFile;
    }
}
