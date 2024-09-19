<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;

class CronjobDto
{
    private ConfigDto $config;
    private array $commands;

    public function __construct(array $cron)
    {
        $this->config = new ConfigDto($cron[Configuration::CONFIG]);

        $this->commands = [];
        foreach ($cron[Configuration::COMMANDS] as $name => $parameters) {
            $this->commands[$name] = new CommandDto($name, $parameters);
        }

        if (true === $this->config->getSettings()->getHeartbeat()
            && !isset($this->commands[Configuration::HEARTBEAT])
        ) {
            $this->commands[Configuration::HEARTBEAT] = new CommandDto(
                Configuration::HEARTBEAT,
                [
                    Configuration::COMMAND => ['/bin/touch', \sprintf('%s/cron.heartbeat', $this->config->getLogsDir())],
                    Configuration::SCHEDULE => [
                        Configuration::MINUTE => '*',
                        Configuration::HOUR => '*',
                        Configuration::DAY_OF_MONTH => '*',
                        Configuration::MONTH => '*',
                        Configuration::DAY_OF_WEEK => '*',
                    ],
                    Configuration::SETTINGS => [
                        Configuration::LOG => false,
                    ],
                ],
            );
        }
    }

    public function getConfig(): ?ConfigDto
    {
        return $this->config;
    }

    /** @return CommandDto[] */
    public function getCommands(): ?array
    {
        return $this->commands;
    }
}
