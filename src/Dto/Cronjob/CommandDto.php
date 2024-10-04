<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\Contract\SettingsInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;

class CommandDto implements SettingsInterface
{
    private string $name;
    private ?string $logFileName;
    private ?string $user;
    private array $command;
    private ScheduleDto $schedule;
    private CommandSettingsDto $settings;

    public function __construct(
        string $name,
        array $parameters,
    ) {
        $this->name = $name;
        $this->logFileName = $parameters[Configuration::LOG_FILE_NAME] ?? null;
        $this->user = $parameters[Configuration::USER] ?? null;
        $this->command = $parameters[Configuration::COMMAND];
        $this->schedule = new ScheduleDto($parameters[Configuration::SCHEDULE]);
        $this->settings = new CommandSettingsDto($parameters[Configuration::SETTINGS]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogFileName(): ?string
    {
        return $this->logFileName;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getCommand(): array
    {
        return $this->command;
    }

    public function getSchedule(): ScheduleDto
    {
        return $this->schedule;
    }

    public function getSettings(): CommandSettingsDto
    {
        return $this->settings;
    }
}
