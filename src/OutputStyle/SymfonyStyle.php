<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\OutputStyle;

use PrecisionSoft\Symfony\Console\OutputStyle\Trait\SymfonyStyleTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SymfonyStyle
{
    use SymfonyStyleTrait;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->initializeSymfonyStyle($input, $output);
    }
}
