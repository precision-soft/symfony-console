<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use DateTimeImmutable;
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

        /** @info skip the decorated title block when stdout cannot render it (piped / redirected / non-decorated) or when the user has requested quiet output — otherwise the title pollutes machine-readable output */
        if (false === $output->isDecorated() || OutputInterface::VERBOSITY_QUIET >= $output->getVerbosity()) {
            return;
        }

        $commandName = $this->getName();
        $this->style->title(\sprintf('<bg=blue>[%s]</> %s', (new DateTimeImmutable())->format('Y-m-d'), $commandName ?? 'unknown'));
    }
}
