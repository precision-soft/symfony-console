<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\OutputStyle\Trait;

use DateTime;
use PrecisionSoft\Symfony\Console\Service\MemoryService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

trait SymfonyStyleTrait
{
    protected SymfonyStyle $style;

    protected function writeln(string $text): void
    {
        $this->style->writeln($this->format($text));
    }

    protected function error(string $text, Throwable $t = null, bool $exposeTrace = false): void
    {
        if ($t !== null) {
            $text = sprintf('%s / %s', $text, $this->formatThrowable($t, $exposeTrace));
        }

        $this->style->error($this->format($text));
    }

    protected function warning(string $text): void
    {
        $this->style->warning($this->format($text));
    }

    protected function success(string $text): void
    {
        $this->style->success($this->format($text));
    }

    protected function formatThrowable(Throwable $t, bool $exposeTrace = false): string
    {
        $text = \sprintf('%s::%s::%s', $t::class, $t->getFile(), $t->getLine());

        if (true === $exposeTrace) {
            $text = \sprintf('%s / %s', $text, $t->getTraceAsString());
        }

        return $text;
    }

    protected function format(string $text): string
    {
        return \sprintf(
            '[%s][%s] %s',
            (new DateTime())->format('H:i:s'),
            MemoryService::getMemoryUsage(),
            $text,
        );
    }

    private function initializeSymfonyStyle(InputInterface $input, OutputInterface $output): void
    {
        $this->style = new SymfonyStyle($input, $output);
    }
}
