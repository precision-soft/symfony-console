<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;

trait WorkerNumberOfProcessesTrait
{
    protected function getNumberOfProcesses(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): int {
        $numberOfProcesses = $commandDto->getSettings()->getNumberOfProcesses() ?? $configDto->getSettings()->getNumberOfProcesses();

        if (true === empty($numberOfProcesses)) {
            throw new Exception('invalid `number of processes`');
        }

        return $numberOfProcesses;
    }
}
