<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class LogsDirCreateCommand extends AbstractCommand
{
    public const NAME = 'precision-soft:symfony:console:logs-dir-create';

    /** @param array<int, string> $logsDirs */
    public function __construct(
        protected readonly ConfFileWriter $confFileWriter,
        protected readonly array $logsDirs,
    ) {
        parent::__construct(static::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 === \count($this->logsDirs)) {
            $this->warning('no logs dirs are configured');

            return static::SUCCESS;
        }

        try {
            foreach ($this->logsDirs as $logsDir) {
                $this->confFileWriter->initLogsDir($logsDir);
            }
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage(), $throwable);

            return static::FAILURE;
        }

        $this->success(\sprintf('ensured `%d` logs dirs', \count($this->logsDirs)));

        foreach ($this->logsDirs as $logsDir) {
            $this->writeln($logsDir);
        }

        return static::SUCCESS;
    }
}
