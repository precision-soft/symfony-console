<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Worker;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;

class WorkerDto
{
    private ConfigDto $config;
    /** @var array<string, CommandDto> */
    private array $commands;

    /** @param array<string, mixed> $worker */
    public function __construct(array $worker)
    {
        $this->config = new ConfigDto($worker[Configuration::CONFIG]);

        $this->commands = [];
        foreach ($worker[Configuration::COMMANDS] as $name => $parameters) {
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
