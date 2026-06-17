<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\Contract\SettingsInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class CommandDto implements SettingsInterface
{
    protected readonly ?string $logFileName;
    protected readonly ?string $user;
    protected readonly ?string $destinationFile;
    /** @var array<int, string> */
    protected readonly array $command;
    protected readonly ScheduleDto $schedule;
    protected readonly CommandSettingsDto $settings;

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws InvalidConfigurationException
     * @throws InvalidValueException
     */
    public function __construct(
        protected readonly string $name,
        array $parameters,
    ) {
        $this->logFileName = $parameters[Configuration::LOG_FILE_NAME] ?? null;
        $this->user = $parameters[Configuration::USER] ?? null;
        $this->destinationFile = $parameters[Configuration::DESTINATION_FILE] ?? null;
        $this->command = $parameters[Configuration::COMMAND];

        if (false === isset($parameters[Configuration::SCHEDULE])) {
            throw new InvalidConfigurationException(\sprintf('the `%s` key is required for command `%s`', Configuration::SCHEDULE, $name));
        }

        $this->schedule = new ScheduleDto($parameters[Configuration::SCHEDULE]);
        $this->settings = new CommandSettingsDto($parameters[Configuration::SETTINGS] ?? []);
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

    public function getDestinationFile(): ?string
    {
        return $this->destinationFile;
    }

    /** @return array<int, string> */
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
