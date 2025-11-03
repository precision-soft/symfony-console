<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use Symfony\Component\Console\Input\InputOption;

trait TimeLimitTrait
{
    protected const TIME_LIMIT = 'time-limit';

    protected readonly int $startTime;
    protected ?int $timeLimit = null;

    /** @info call this in initialize() */
    protected function initializeTimeLimit(): void
    {
        $this->startTime = \time();
        $this->timeLimit = null;

        if (true === $this->input->hasOption(self::TIME_LIMIT) && $timeLimit = $this->input->getOption(self::TIME_LIMIT)) {
            $this->timeLimit = (int)$timeLimit;
        }
    }

    /** @info call this in configure() */
    protected function configureTimeLimit(int $default = 600): void
    {
        $this->addOption(
            self::TIME_LIMIT,
            null,
            InputOption::VALUE_OPTIONAL,
            'max runtime in seconds that the command is allowed to run for',
            $default,
        );
    }

    protected function isTimeLimitReached(): bool
    {
        if (null === $this->timeLimit) {
            return false;
        }

        $timeUsed = \time() - $this->startTime;

        if ($timeUsed >= $this->timeLimit) {
            $this->warning(\sprintf('max run time reached `%s`/`%s` seconds', $timeUsed, $this->timeLimit));

            return true;
        }

        return false;
    }
}
