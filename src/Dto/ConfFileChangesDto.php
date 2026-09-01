<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class ConfFileChangesDto
{
    /** @var array<string, ConfFileChangeDto> */
    protected array $changes;

    public function __construct()
    {
        $this->changes = [];
    }

    /** @return array<string, ConfFileChangeDto> */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /** @return array<string, ConfFileChangeDto> */
    public function getPendingChanges(): array
    {
        return \array_filter(
            $this->changes,
            static fn(ConfFileChangeDto $confFileChangeDto): bool => ConfFileStatus::Unchanged !== $confFileChangeDto->getStatus(),
        );
    }

    /** @throws InvalidValueException */
    public function addChange(ConfFileChangeDto $confFileChangeDto): static
    {
        if (true === \array_key_exists($confFileChangeDto->getPath(), $this->changes)) {
            throw new InvalidValueException(
                \sprintf('the change path is in use `%s`', $confFileChangeDto->getPath()),
            );
        }

        $this->changes[$confFileChangeDto->getPath()] = $confFileChangeDto;

        return $this;
    }

    public function sort(): static
    {
        \ksort($this->changes);

        return $this;
    }
}
