<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Contract;

interface ConfigInterface extends SettingsInterface
{
    public function getTemplateClass(): string;

    public function getLogsDir(): string;

    public function getConfFilesDir(): string;

    public function getSettings(): SettingInterface;
}
