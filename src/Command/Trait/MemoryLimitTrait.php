<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Service\MemoryService;
use Symfony\Component\Console\Input\InputOption;

trait MemoryLimitTrait
{
    protected const MEMORY_LIMIT = 'memory-limit';

    protected ?string $memoryLimit = null;

    /** @info cached byte value parsed from `$memoryLimit`, populated lazily on first `getMemoryLimitReached()` call so the hot loop does not re-parse on each iteration */
    protected ?int $memoryLimitBytes = null;

    /** @throws InvalidValueException */
    protected function initializeMemoryLimit(): void
    {
        $this->memoryLimit = null;
        $this->memoryLimitBytes = null;

        if (true === $this->input->hasOption(static::MEMORY_LIMIT)) {
            $memoryLimit = $this->input->getOption(static::MEMORY_LIMIT);

            if (null !== $memoryLimit && '' !== $memoryLimit) {
                $this->memoryLimit = $memoryLimit;

                MemoryService::setMemoryLimitIfNotHigher($this->memoryLimit);
            }
        }
    }

    protected function configureMemoryLimit(string $default = '512M'): void
    {
        $this->addOption(
            static::MEMORY_LIMIT,
            null,
            InputOption::VALUE_OPTIONAL,
            'max memory allowed to be used before the command automatically stops',
            $default,
        );
    }

    protected function getMemoryLimitReached(): bool
    {
        if (null === $this->memoryLimit) {
            return false;
        }

        $this->memoryLimitBytes ??= MemoryService::returnBytes($this->memoryLimit);

        if (-1 === $this->memoryLimitBytes) {
            return false;
        }

        $memoryUsage = \memory_get_usage(true);

        if ($this->memoryLimitBytes < $memoryUsage) {
            $humanReadableMemoryUsed = MemoryService::convertBytesToHumanReadable($memoryUsage);
            $humanReadableMemoryLimit = MemoryService::convertBytesToHumanReadable($this->memoryLimitBytes);

            $this->warning(\sprintf('max allowed memory usage reached `%s`/`%s`', $humanReadableMemoryUsed, $humanReadableMemoryLimit));

            return true;
        }

        return false;
    }
}
