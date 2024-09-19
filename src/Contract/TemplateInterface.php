<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Contract;

use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;

interface TemplateInterface
{
    public function generate(ConfigInterface $configDto, array $commands): ConfFilesDto;
}
