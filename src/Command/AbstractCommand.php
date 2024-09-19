<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use DateTime;
use PrecisionSoft\Symfony\Console\OutputStyle\Trait\SymfonyStyleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    use SymfonyStyleTrait;

    protected InputInterface $input;
    protected OutputInterface $output;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;

        $this->initializeSymfonyStyle($input, $output);

        $this->style->title(\sprintf('<bg=blue>[%s]</> %s', (new DateTime())->format('Y-m-d'), $this->getName()));
    }
}
