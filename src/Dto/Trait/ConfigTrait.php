<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Trait;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;

trait ConfigTrait
{
    protected string $templateClass;
    protected string $confFilesDir;
    protected string $logsDir;

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

    /** @param array<string, mixed> $configuration */
    protected function setConfig(array $configuration): void
    {
        $this->templateClass = $configuration[Configuration::TEMPLATE_CLASS];
        $this->confFilesDir = $configuration[Configuration::CONF_FILES_DIR];
        $this->logsDir = $configuration[Configuration::LOGS_DIR];
    }
}
