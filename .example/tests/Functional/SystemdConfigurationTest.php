<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Functional;

use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\Example\Test\Utility\AbstractConfigurationTestCase;

/** @internal */
final class SystemdConfigurationTest extends AbstractConfigurationTestCase
{
    protected const ENVIRONMENT = 'systemd';

    public function testOneUnitPerInstanceRunsTheImportFromTheProjectDirectory(): void
    {
        $commandTester = $this->runCommand(WorkerCreateCommand::NAME);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated `2` conf files', $commandTester->getDisplay());

        foreach ($this->getUnitPaths() as $unitPath) {
            $unit = \parse_ini_string($this->readFile($unitPath), true, \INI_SCANNER_RAW);

            static::assertIsArray($unit);
            static::assertSame('www-data', $unit['Service']['User']);
            static::assertSame($this->getProjectDir(), $unit['Service']['WorkingDirectory']);
            static::assertSame(
                \sprintf('/usr/bin/env php %s catalogue:price-list-import --memory-limit=256M --time-limit=300', $this->getConsolePath()),
                $unit['Service']['ExecStart'],
            );
            static::assertSame('-' . $this->getProjectDir() . '/.env.local', $unit['Service']['EnvironmentFile']);
            static::assertSame('on-failure', $unit['Service']['Restart']);
            static::assertSame('append:' . $this->getLogsDir() . '/worker/price_list_import.log', $unit['Service']['StandardOutput']);
            static::assertSame(['WantedBy' => 'multi-user.target'], $unit['Install']);
        }
    }

    /* the suite reads the units back as text; systemd is the authority on whether they start, so the units are verified
       with `systemd-analyze` wherever it is on the path and exported for a host that has systemd but no php */
    public function testTheUnitsAreAcceptedBySystemd(): void
    {
        $this->runCommand(WorkerCreateCommand::NAME);

        $unitPaths = $this->getUnitPaths();

        foreach ($unitPaths as $unitPath) {
            static::assertFileExists($unitPath);
        }

        $exportDir = \getenv('SYSTEMD_UNITS_EXPORT_DIR');

        if (false !== $exportDir && '' !== $exportDir) {
            foreach ($unitPaths as $unitPath) {
                $this->filesystem->copy($unitPath, $exportDir . '/' . \basename($unitPath), true);
            }
        }

        \exec('command -v systemd-analyze 2>/dev/null', $lookupOutput, $lookupExitCode);

        if (0 !== $lookupExitCode) {
            return;
        }

        \exec(
            'systemd-analyze verify --man=no --generators=no '
            . \implode(' ', \array_map(static fn(string $unitPath): string => \escapeshellarg($unitPath), $unitPaths))
            . ' 2>&1',
            $verifyOutput,
            $verifyExitCode,
        );

        static::assertSame(0, $verifyExitCode, \implode(\PHP_EOL, $verifyOutput));
        static::assertSame([], $verifyOutput);
    }

    /** @return array<int, string> */
    private function getUnitPaths(): array
    {
        return [
            $this->getGeneratedConfigurationDir() . '/worker/catalogue-price_list_import-1.service',
            $this->getGeneratedConfigurationDir() . '/worker/catalogue-price_list_import-2.service',
        ];
    }
}
