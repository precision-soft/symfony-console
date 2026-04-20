<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Trait;

trait SupervisorSettingsTrait
{
    protected ?int $numberOfProcesses = null;
    protected ?bool $autoStart = null;
    protected ?bool $autoRestart = null;
    protected ?string $prefix = null;
    protected ?string $user = null;
    protected ?string $logFile = null;

    public function getNumberOfProcesses(): ?int
    {
        return $this->numberOfProcesses;
    }

    public function getAutoStart(): ?bool
    {
        return $this->autoStart;
    }

    public function getAutoRestart(): ?bool
    {
        return $this->autoRestart;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getLogFile(): ?string
    {
        return $this->logFile;
    }
}
