<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Contract;

interface SettingsInterface
{
    public function getSettings(): SettingInterface;
}
