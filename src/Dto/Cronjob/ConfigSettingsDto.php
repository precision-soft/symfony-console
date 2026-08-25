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

    protected bool $log = false;
    protected string $destinationFile = '';
    /** @var array<int, string> */
    protected array $destinationFiles = [];
    protected bool $heartbeat = false;
    protected ?string $user = null;

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

    /** @return array<int, string> */
    public function getDestinationFiles(): array
    {
        return $this->destinationFiles;
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
