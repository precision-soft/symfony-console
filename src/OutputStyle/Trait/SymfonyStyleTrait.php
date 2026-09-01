<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\OutputStyle\Trait;

use PrecisionSoft\Symfony\Console\Contract\ExceptionInterface;
use PrecisionSoft\Symfony\Console\Service\MemoryService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

trait SymfonyStyleTrait
{
    protected const THROWABLE_CHAIN_LIMIT = 10;

    protected SymfonyStyle $style;

    protected ?int $cachedPrefixSecond = null;

    protected string $cachedPrefix = '';

    protected function writeln(string $text): void
    {
        $this->style->writeln($this->format($text));
    }

    /* the timestamp and memory prefix would break every line of a diff or of any other verbatim block */
    protected function writelnUnformatted(string $text): void
    {
        $this->style->writeln($text);
    }

    protected function error(
        string $text,
        ?Throwable $throwable = null,
        bool $exposeTrace = false,
    ): void {
        if (null !== $throwable) {
            $text = \sprintf('%s / %s', $text, $this->formatThrowable($throwable, $exposeTrace));
        }

        $this->style->error($this->format($text));
    }

    protected function info(string $text): void
    {
        $this->style->info($this->format($text));
    }

    protected function warning(string $text): void
    {
        $this->style->warning($this->format($text));
    }

    protected function success(string $text): void
    {
        $this->style->success($this->format($text));
    }

    protected function formatThrowable(Throwable $throwable, bool $exposeTrace = false): string
    {
        $chainParts = [];

        $rootThrowable = $throwable;
        $currentThrowable = $throwable;
        $depth = 0;

        while (null !== $currentThrowable && static::THROWABLE_CHAIN_LIMIT > $depth) {
            $chainParts[] = $this->formatThrowableLink($currentThrowable);

            $rootThrowable = $currentThrowable;
            $currentThrowable = $currentThrowable->getPrevious();
            ++$depth;
        }

        $text = \implode(' <- ', $chainParts);

        if (true === $exposeTrace) {
            $text = \sprintf('%s / %s', $text, $rootThrowable->getTraceAsString());
        }

        return $text;
    }

    protected function formatThrowableLink(Throwable $throwable): string
    {
        $text = \sprintf('%s::%s::%s', $throwable::class, $throwable->getFile(), $throwable->getLine());

        if (false === ($throwable instanceof ExceptionInterface)) {
            return $text;
        }

        $context = $throwable->getContext();

        if (0 === \count($context)) {
            return $text;
        }

        $encodedContext = \json_encode($context);

        return \sprintf('%s::%s', $text, false === $encodedContext ? 'un-encodable context' : $encodedContext);
    }

    protected function format(string $text): string
    {
        $currentSecond = \time();

        if ($currentSecond !== $this->cachedPrefixSecond) {
            $this->cachedPrefixSecond = $currentSecond;
            $this->cachedPrefix = \sprintf(
                '[%s][%s]',
                \date('H:i:s', $currentSecond),
                MemoryService::getMemoryUsage(),
            );
        }

        return $this->cachedPrefix . ' ' . $text;
    }

    protected function initializeSymfonyStyle(InputInterface $input, OutputInterface $output): void
    {
        $this->style = new SymfonyStyle($input, $output);
    }
}
