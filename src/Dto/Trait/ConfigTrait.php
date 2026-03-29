<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Trait;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;

trait ConfigTrait
{
    private string $templateClass;
    private string $confFilesDir;
    private string $logsDir;

    public function getTemplateClass(): string
    {
        return $this->templateClass;
    }

    public function getConfFilesDir(): string
    {
        return $this->confFilesDir;
    }

    public function getLogsDir(): string
    {
        return $this->logsDir;
    }

    /** @param array<string, mixed> $config */
    private function setConfigs(array $config): void
    {
        $this->templateClass = $config[Configuration::TEMPLATE_CLASS];
        $this->confFilesDir = $config[Configuration::CONF_FILES_DIR];
        $this->logsDir = $config[Configuration::LOGS_DIR];
    }
}
