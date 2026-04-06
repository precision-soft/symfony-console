<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Contract;

use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;

interface TemplateInterface
{
    /** @param array<string, mixed> $commands */
    public function generate(ConfigInterface $configInterface, array $commands): ConfFilesDto;
}
