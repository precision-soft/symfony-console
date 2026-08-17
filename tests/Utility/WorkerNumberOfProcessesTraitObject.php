<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Utility;

use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;

/* named rather than anonymous: an anonymous class can only be typed `object` for static analysis */

class WorkerNumberOfProcessesTraitObject
{
    use WorkerNumberOfProcessesTrait;

    /** @throws InvalidConfigurationException */
    public function resolveNumberOfProcesses(ConfigDto $configDto, CommandDto $commandDto): int
    {
        return $this->getNumberOfProcesses($configDto, $commandDto);
    }
}
