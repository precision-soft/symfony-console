<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Utility;

use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;

final class UnreadableConfFileWriter extends ConfFileWriter
{
    protected function readFile(string $path): string|false
    {
        return false;
    }
}
