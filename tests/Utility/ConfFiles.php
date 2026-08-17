<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Utility;

use PHPUnit\Framework\Assert;

final class ConfFiles
{
    /** @param array<string, string> $files */
    public static function getFirstContent(array $files): string
    {
        $content = \reset($files);

        Assert::assertIsString($content, 'no conf file was generated');

        return $content;
    }
}
