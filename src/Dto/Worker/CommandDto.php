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
    protected readonly ?string $destinationSubDir;
    protected readonly ?string $destinationSuffix;
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
        $this->destinationSubDir = $parameters[Configuration::DESTINATION_SUB_DIR] ?? null;
        $this->destinationSuffix = $parameters[Configuration::DESTINATION_SUFFIX] ?? null;
        $this->command = $parameters[Configuration::COMMAND];
        $this->settings = new CommandSettingsDto($parameters[Configuration::SETTINGS] ?? []);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDestinationSubDir(): ?string
    {
        return $this->destinationSubDir;
    }

    public function getDestinationSuffix(): ?string
    {
        return $this->destinationSuffix;
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
