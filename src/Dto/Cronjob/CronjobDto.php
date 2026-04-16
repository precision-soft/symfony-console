<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class CronjobDto
{
    private ConfigDto $config;
    /** @var array<string, CommandDto> */
    private array $commands;

    /**
     * @param array<string, mixed> $cron
     * @throws InvalidConfigurationException
     * @throws InvalidValueException
     */
    public function __construct(array $cron)
    {
        $this->config = new ConfigDto($cron[Configuration::CONFIG]);

        $this->commands = [];
        foreach ($cron[Configuration::COMMANDS] as $name => $parameters) {
            $this->commands[$name] = new CommandDto($name, $parameters);
        }
    }

    public function getConfig(): ConfigDto
    {
        return $this->config;
    }

    /** @return CommandDto[] */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
