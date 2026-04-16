<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto\Cronjob;

use PrecisionSoft\Symfony\Console\Contract\SettingInterface;
use PrecisionSoft\Symfony\Console\Dto\Trait\SettingsTrait;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class ConfigSettingsDto implements SettingInterface
{
    use SettingsTrait;

    private bool $log = false;
    private string $destinationFile = '';
    private bool $heartbeat = false;
    private ?string $user = null;

    /**
     * @param array<string, mixed> $settings
     * @throws InvalidValueException
     */
    public function __construct(array $settings)
    {
        $this->loadProperties($settings);
    }

    public function getLog(): bool
    {
        return $this->log;
    }

    public function getDestinationFile(): string
    {
        return $this->destinationFile;
    }

    public function getHeartbeat(): bool
    {
        return $this->heartbeat;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }
}
