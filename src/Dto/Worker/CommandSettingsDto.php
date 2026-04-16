<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Worker;

use PrecisionSoft\Symfony\Console\Contract\SettingInterface;
use PrecisionSoft\Symfony\Console\Dto\Trait\SettingsTrait;
use PrecisionSoft\Symfony\Console\Dto\Trait\SupervisorSettingsTrait;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class CommandSettingsDto implements SettingInterface
{
    use SettingsTrait;
    use SupervisorSettingsTrait;

    /**
     * @param array<string, mixed> $settings
     * @throws InvalidValueException
     */
    public function __construct(array $settings)
    {
        $this->loadProperties($settings);
    }
}
