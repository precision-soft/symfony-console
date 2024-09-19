<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Contract;

interface SettingInterface
{
    public function getSetting(string $setting): ?string;
}
