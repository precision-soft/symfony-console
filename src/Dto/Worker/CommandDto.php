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
    /** @var array<int, string> */
    private readonly array $command;
    private readonly CommandSettingsDto $settings;

    /**
     * @param array<string, mixed> $parameters
     */
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

    /** @return array<int, string> */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function getSettings(): CommandSettingsDto
    {
        return $this->settings;
    }
}
