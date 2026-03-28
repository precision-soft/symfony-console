<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Service\MemoryService;
use Symfony\Component\Console\Input\InputOption;

trait MemoryLimitTrait
{
    protected const MEMORY_LIMIT = 'memory-limit';

    protected ?string $memoryLimit = null;

    protected function initializeMemoryLimit(): void
    {
        $this->memoryLimit = null;

        if (true === $this->input->hasOption(self::MEMORY_LIMIT)) {
            $memoryLimit = $this->input->getOption(self::MEMORY_LIMIT);

            if (null !== $memoryLimit && '' !== $memoryLimit) {
                $this->memoryLimit = (string)$memoryLimit;

                MemoryService::setMemoryLimitIfNotHigher($this->memoryLimit);
            }
        }
    }

    protected function configureMemoryLimit(string $default = '512M'): void
    {
        $this->addOption(
            self::MEMORY_LIMIT,
            null,
            InputOption::VALUE_OPTIONAL,
            'max memory allowed to be used before the command automatically stops',
            $default,
        );
    }

    protected function isMemoryLimitReached(): bool
    {
        if (null === $this->memoryLimit) {
            return false;
        }

        $memoryLimit = MemoryService::returnBytes($this->memoryLimit);
        $memoryUsage = \memory_get_usage(true);

        if ($memoryLimit < $memoryUsage) {
            $humanReadableMemoryUsed = MemoryService::convertBytesToHumanReadable($memoryUsage);
            $humanReadableMemoryLimit = MemoryService::convertBytesToHumanReadable($memoryLimit);

            $this->warning(\sprintf('max allowed memory usage reached `%s`/`%s`', $humanReadableMemoryUsed, $humanReadableMemoryLimit));

            return true;
        }

        return false;
    }
}
