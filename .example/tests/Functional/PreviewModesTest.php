<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Functional;

use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Example\Test\Utility\AbstractConfigurationTestCase;

/* `--dry-run`, `--diff` and `--check` on the crontab: what a deployment pipeline runs before and after a generation */
/** @internal */
final class PreviewModesTest extends AbstractConfigurationTestCase
{
    public function testCheckReportsWhatAGenerationWouldAddAndWritesNothing(): void
    {
        $commandTester = $this->runCommand(CronjobCreateCommand::NAME, ['--check' => true]);

        static::assertSame(CronjobCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[added] ' . $this->getCrontabPath(), $commandTester->getDisplay());
        static::assertFileDoesNotExist($this->getCrontabPath());
    }

    public function testDryRunWritesNothing(): void
    {
        $commandTester = $this->runCommand(CronjobCreateCommand::NAME, ['--dry-run' => true]);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertFileDoesNotExist($this->getCrontabPath());
    }

    public function testCheckPassesRightAfterAGeneration(): void
    {
        $this->runCommand(CronjobCreateCommand::NAME);

        $commandTester = $this->runCommand(CronjobCreateCommand::NAME, ['--check' => true]);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated conf files are current', $commandTester->getDisplay());
    }

    public function testDiffShowsAManualEditAsTheDriftCheckFails(): void
    {
        $this->runCommand(CronjobCreateCommand::NAME);

        $this->filesystem->dumpFile(
            $this->getCrontabPath(),
            \str_replace('*/15 * * * * www-data', '*/15 * * * * root', $this->readFile($this->getCrontabPath())),
        );

        $commandTester = $this->runCommand(CronjobCreateCommand::NAME, ['--check' => true, '--diff' => true]);
        $display = $commandTester->getDisplay();

        static::assertSame(CronjobCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[changed] ' . $this->getCrontabPath(), $display);
        static::assertStringContainsString('-*/15 * * * * root', $display);
        static::assertStringContainsString('+*/15 * * * * www-data', $display);
    }

    public function testAFileNoCommandDeclaresAnyMoreIsReportedAsRemovedWithItsContent(): void
    {
        $this->runCommand(CronjobCreateCommand::NAME);

        $this->filesystem->dumpFile($this->getGeneratedConfigurationDir() . '/cron/retired', '0 4 * * * www-data /bin/true');

        $commandTester = $this->runCommand(CronjobCreateCommand::NAME, ['--check' => true, '--diff' => true]);
        $display = $commandTester->getDisplay();

        static::assertSame(CronjobCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[removed] ' . $this->getGeneratedConfigurationDir() . '/cron/retired', $display);
        static::assertStringContainsString('-0 4 * * * www-data /bin/true', $display);
    }

    private function getCrontabPath(): string
    {
        return $this->getGeneratedConfigurationDir() . '/cron/catalogue';
    }
}
