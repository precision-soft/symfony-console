<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Contract;

use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto as CronjobCommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto as CronjobConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto as WorkerCommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto as WorkerConfigDto;

interface TemplateInterface
{
    /**
     * @param WorkerConfigDto|CronjobConfigDto $configDto
     * @param WorkerCommandDto[]|CronjobCommandDto[] $commands
     */
    public function generate(ConfigInterface $configDto, array $commands): ConfFilesDto;
}
