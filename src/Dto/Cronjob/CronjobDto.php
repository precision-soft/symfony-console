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
    protected ConfigDto $config;
    /** @var array<string, CommandDto> */
    protected array $commands;

    /**
     * @param array<string, mixed> $cronjob
     * @throws InvalidConfigurationException
     * @throws InvalidValueException
     */
    public function __construct(array $cronjob)
    {
        $this->config = new ConfigDto($cronjob[Configuration::CONFIG]);

        $this->commands = [];
        foreach ($cronjob[Configuration::COMMANDS] as $name => $parameters) {
            $this->commands[$name] = new CommandDto($name, $parameters);
        }
    }

    public function getConfig(): ConfigDto
    {
        return $this->config;
    }

    /** @return array<string, CommandDto> */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
