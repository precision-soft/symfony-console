<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Functional;

use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\Example\Test\Utility\AbstractConfigurationTestCase;

/** @internal */
final class SupervisorConfigurationTest extends AbstractConfigurationTestCase
{
    public function testTheProgramRunsTheImportUnderTheCatalogueUserWithTwoProcesses(): void
    {
        $commandTester = $this->runCommand(WorkerCreateCommand::NAME);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $program = $this->readFile($this->getGeneratedConfigurationDir() . '/worker/price_list_import.conf');

        static::assertStringContainsString('[program:catalogue-price_list_import]', $program);
        static::assertStringContainsString(
            \sprintf('command = /usr/bin/env php %s catalogue:price-list-import --memory-limit=256M --time-limit=300', $this->getConsolePath()),
            $program,
        );
        static::assertStringContainsString('numprocs = 2', $program);
        static::assertStringContainsString('user = www-data', $program);
        static::assertStringContainsString('autostart = true', $program);
        static::assertStringContainsString('autorestart = true', $program);
        static::assertStringContainsString(\sprintf('stdout_logfile = %s/worker/price_list_import.log', $this->getLogsDir()), $program);
    }
}
