<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use Symfony\Component\Console\Command\Command;

trait MemoryAndTimeLimitsTrait
{
    use TimeLimitTrait;
    use MemoryLimitTrait;

    /** @info call this in initialize() */
    protected function initializeMemoryAndTimeLimits(): void
    {
        $this->initializeMemoryLimit();
        $this->initializeTimeLimit();
    }

    /** @info call this in configure() */
    protected function configureMemoryAndTimeLimits(
        string $defaultMemoryLimit = '512M',
        int $defaultTimeLimit = 600,
    ): void {
        $this->configureMemoryLimit($defaultMemoryLimit);

        $this->configureTimeLimit($defaultTimeLimit);
    }

    protected function stopScriptIfLimitsReached(): void
    {
        if (true === $this->didScriptReachedLimits()) {
            exit(Command::INVALID);
        }
    }

    protected function didScriptReachedLimits(): bool
    {
        return $this->isMemoryLimitReached() || $this->isTimeLimitReached();
    }
}
