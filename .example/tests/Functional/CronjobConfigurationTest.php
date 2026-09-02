<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Functional;

use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\LogsDirCreateCommand;
use PrecisionSoft\Symfony\Console\Example\Test\Utility\AbstractConfigurationTestCase;

/** @internal */
final class CronjobConfigurationTest extends AbstractConfigurationTestCase
{
    public function testTheLogsDirectoriesAreCreatedIdempotently(): void
    {
        $this->filesystem->remove([$this->getLogsDir() . '/cron', $this->getLogsDir() . '/worker']);

        $commandTester = $this->runCommand(LogsDirCreateCommand::NAME);

        static::assertSame(LogsDirCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('ensured `2` logs dirs', $commandTester->getDisplay());
        static::assertDirectoryExists($this->getLogsDir() . '/cron');
        static::assertDirectoryExists($this->getLogsDir() . '/worker');

        static::assertSame(LogsDirCreateCommand::SUCCESS, $this->runCommand(LogsDirCreateCommand::NAME)->getStatusCode());
    }

    public function testTheCrontabCarriesEveryScheduledCommandWithItsUserLogAndHeartbeat(): void
    {
        $commandTester = $this->runCommand(CronjobCreateCommand::NAME);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated `2` conf files', $commandTester->getDisplay());

        $crontab = $this->readFile($this->getGeneratedConfigurationDir() . '/cron/catalogue');

        static::assertStringContainsString(
            \sprintf(
                "*/15 * * * * www-data /usr/bin/env php %s catalogue:exchange-rate-refresh --time-limit=50 >> '%s/cron/exchange_rate_refresh.log' 2>&1",
                $this->getConsolePath(),
                $this->getLogsDir(),
            ),
            $crontab,
        );
        static::assertStringContainsString(
            \sprintf(
                "30 2 * * 1-5 www-data /usr/bin/env php %s catalogue:price-list-import --memory-limit=256M --time-limit=300 >> '%s/cron/price-list-import.nightly.log' 2>&1",
                $this->getConsolePath(),
                $this->getLogsDir(),
            ),
            $crontab,
        );
        static::assertStringContainsString(
            \sprintf('* * * * * www-data /bin/touch %s/cron/heartbeat.catalogue', $this->getLogsDir()),
            $crontab,
        );
    }

    /* a machine that runs nothing but the heartbeat still proves it is alive: the declared file holds only that row */
    public function testADeclaredFileNoCommandTargetsHoldsOnlyTheHeartbeat(): void
    {
        $this->runCommand(CronjobCreateCommand::NAME);

        $reports = $this->readFile($this->getGeneratedConfigurationDir() . '/cron/catalogue.reports');

        static::assertStringContainsString('/bin/touch ' . $this->getLogsDir() . '/cron/heartbeat.catalogue.reports', $reports);
        static::assertStringNotContainsString('catalogue:', $reports);
    }
}
