<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Service\MemoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

trait MemoryAndTimeLimitsTrait
{
    protected const MEMORY_LIMIT = 'memory-limit';
    protected const TIME_LIMIT = 'time-limit';

    private int $startTime;
    protected ?string $memoryLimit;
    protected ?int $timeLimit;

    protected function stopScriptIfLimitsReached(): void
    {
        if (true === $this->didScriptReachedLimits()) {
            exit(Command::INVALID);
        }
    }

    protected function didScriptReachedLimits(): bool
    {
        return $this->isMemoryLimitReached()
            || $this->isTimeLimitReached();
    }

    protected function isTimeLimitReached(): bool
    {
        if (null === $this->timeLimit) {
            return false;
        }

        $timeUsed = \time() - $this->startTime;

        if ($timeUsed >= $this->timeLimit) {
            $this->warning(
                \sprintf('max run time reached `%s`/`%s` seconds', $timeUsed, $this->timeLimit),
            );

            return true;
        }

        return false;
    }

    protected function isMemoryLimitReached(): bool
    {
        if (null === $this->memoryLimit) {
            return false;
        }

        $memoryLimit = MemoryService::returnBytes($this->memoryLimit);

        $memoryUsage = \memory_get_usage(true);

        if ($memoryUsage > $memoryLimit) {
            $humanReadableMemoryUsed = MemoryService::convertBytesToHumanReadable($memoryUsage);
            $humanReadableMemoryLimit = MemoryService::convertBytesToHumanReadable($memoryLimit);

            $this->warning(
                \sprintf(
                    'max allowed memory usage reached `%s`/`%s`',
                    $humanReadableMemoryUsed,
                    $humanReadableMemoryLimit,
                ),
            );

            return true;
        }

        return false;
    }

    private function initializeMemoryAndTimeLimits(): void
    {
        $this->startTime = \time();
        $this->memoryLimit = null;
        $this->timeLimit = null;

        if (true === $this->input->hasOption(self::MEMORY_LIMIT) && $memoryLimit = $this->input->getOption(self::MEMORY_LIMIT)) {
            $this->memoryLimit = (string)$memoryLimit;

            MemoryService::setMemoryLimitIfNotHigher($this->memoryLimit);
        }

        if (true === $this->input->hasOption(self::TIME_LIMIT) && $timeLimit = $this->input->getOption(self::TIME_LIMIT)) {
            $this->timeLimit = (int)$timeLimit;
        }
    }

    private function configureMemoryAndTimeLimits(): void
    {
        $this->addOption(
            self::MEMORY_LIMIT,
            null,
            InputOption::VALUE_OPTIONAL,
            'max memory allowed to be used before the command automatically stops',
            '512M',
        )
            ->addOption(
                self::TIME_LIMIT,
                null,
                InputOption::VALUE_OPTIONAL,
                'max runtime in seconds that the command is allowed to run for',
                600,
            );
    }
}
