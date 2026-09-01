<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto;

enum ConfFileStatus: string
{
    case Added = 'added';
    case Changed = 'changed';
    case Removed = 'removed';
    case Unchanged = 'unchanged';
}
