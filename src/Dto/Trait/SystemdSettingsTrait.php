<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Trait;

trait SystemdSettingsTrait
{
    protected ?string $workingDirectory = null;
    protected ?string $environmentFile = null;
    protected ?string $restartPolicy = null;
    protected ?string $standardOutput = null;
    protected ?string $standardError = null;

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function getEnvironmentFile(): ?string
    {
        return $this->environmentFile;
    }

    public function getRestartPolicy(): ?string
    {
        return $this->restartPolicy;
    }

    public function getStandardOutput(): ?string
    {
        return $this->standardOutput;
    }

    public function getStandardError(): ?string
    {
        return $this->standardError;
    }
}
