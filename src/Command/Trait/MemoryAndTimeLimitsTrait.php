<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;

trait MemoryAndTimeLimitsTrait
{
    use TimeLimitTrait;
    use MemoryLimitTrait;

    protected function initializeMemoryAndTimeLimits(): void
    {
        $this->initializeMemoryLimit();
        $this->initializeTimeLimit();
    }

    protected function configureMemoryAndTimeLimits(
        string $defaultMemoryLimit = '512M',
        int $defaultTimeLimit = 600,
    ): void {
        $this->configureMemoryLimit($defaultMemoryLimit);

        $this->configureTimeLimit($defaultTimeLimit);
    }

    protected function stopScriptIfLimitsReached(): void
    {
        if (true === $this->hasScriptReachedLimits()) {
            throw new LimitExceededException('memory or time limit exceeded');
        }
    }

    protected function hasScriptReachedLimits(): bool
    {
        return $this->isMemoryLimitReached() || $this->isTimeLimitReached();
    }
}
