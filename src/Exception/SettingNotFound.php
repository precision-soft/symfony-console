<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Exception;

class SettingNotFound extends Exception
{
    public function __construct(string $setting, string $class)
    {
        $message = \sprintf('the setting `%s` is not set for `%s`', $setting, $class);

        parent::__construct($message);
    }
}
