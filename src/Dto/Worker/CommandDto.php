<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Worker;

use PrecisionSoft\Symfony\Console\Contract\SettingsInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class CommandDto implements SettingsInterface
{
    /** @var array<int, string> */
    protected readonly array $command;
    protected readonly CommandSettingsDto $settings;

    /**
     * @param array<string, mixed> $parameters
     * @throws InvalidValueException
     */
    public function __construct(
        protected readonly string $name,
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
