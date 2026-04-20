<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use Symfony\Component\Console\Input\InputOption;

trait TimeLimitTrait
{
    protected const TIME_LIMIT = 'time-limit';

    protected int $startTime;
    protected ?int $timeLimit = null;

    /** @throws InvalidValueException */
    protected function initializeTimeLimit(): void
    {
        $this->startTime = \time();
        $this->timeLimit = null;

        if (true === $this->input->hasOption(static::TIME_LIMIT)) {
            $timeLimit = $this->input->getOption(static::TIME_LIMIT);

            if (null !== $timeLimit && '' !== $timeLimit) {
                if (false === \is_numeric($timeLimit) || 0 >= (int)$timeLimit) {
                    throw new InvalidValueException(\sprintf('the `--time-limit` option must be a positive integer, `%s` given', $timeLimit));
                }

                $this->timeLimit = (int)$timeLimit;
            }
        }
    }

    protected function configureTimeLimit(int $default = 600): void
    {
        $this->addOption(
            static::TIME_LIMIT,
            null,
            InputOption::VALUE_OPTIONAL,
            'max runtime in seconds that the command is allowed to run for',
            $default,
        );
    }

    protected function getTimeLimitReached(): bool
    {
        if (null === $this->timeLimit) {
            return false;
        }

        $timeUsed = \time() - $this->startTime;

        if ($this->timeLimit <= $timeUsed) {
            $this->warning(\sprintf('max run time reached `%s`/`%s` seconds', $timeUsed, $this->timeLimit));

            return true;
        }

        return false;
    }
}
