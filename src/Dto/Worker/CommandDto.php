<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Worker;

use PrecisionSoft\Symfony\Console\Contract\SettingsInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;

class CommandDto implements SettingsInterface
{
    private readonly array $command;
    private readonly CommandSettingsDto $settings;

    public function __construct(
        private readonly string $name,
        array $parameters,
    ) {
        $this->command = $parameters[Configuration::COMMAND];
        $this->settings = new CommandSettingsDto($parameters[Configuration::SETTINGS] ?? []);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommand(): array
    {
        return $this->command;
    }

    public function getSettings(): CommandSettingsDto
    {
        return $this->settings;
    }
}
