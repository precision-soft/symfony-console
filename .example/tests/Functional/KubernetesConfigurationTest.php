<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Functional;

use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\Example\Test\Utility\AbstractConfigurationTestCase;
use Symfony\Component\Yaml\Yaml;

/** @internal */
final class KubernetesConfigurationTest extends AbstractConfigurationTestCase
{
    protected const ENVIRONMENT = 'kubernetes';

    public function testTheCronJobValuesListEveryScheduledCommand(): void
    {
        $commandTester = $this->runCommand(CronjobCreateCommand::NAME);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $jobs = $this->getEntries($this->getGeneratedConfigurationDir() . '/cron/cronjobs.yaml', 'CronJobs', 'jobs');

        static::assertSame(
            [
                'name' => 'exchange-rate-refresh',
                'command' => \sprintf('/usr/bin/env php %s catalogue:exchange-rate-refresh --time-limit=50', $this->getConsolePath()),
                'schedule' => '*/15 * * * *',
            ],
            $jobs['exchange-rate-refresh'],
        );
        static::assertSame('30 2 * * 1-5', $jobs['price-list-import']['schedule']);
    }

    public function testTheWorkerValuesCarryTheParallelism(): void
    {
        $commandTester = $this->runCommand(WorkerCreateCommand::NAME);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $workers = $this->getEntries($this->getGeneratedConfigurationDir() . '/worker/workers.yaml', 'Jobs', 'workers');

        static::assertSame(
            \sprintf('/usr/bin/env php %s catalogue:price-list-import --memory-limit=256M --time-limit=300', $this->getConsolePath()),
            $workers['price-list-import']['command'],
        );
        static::assertSame(2, $workers['price-list-import']['parallelism']);
    }

    /** @return array<string, array<string, mixed>> */
    private function getEntries(string $path, string $rootKey, string $listKey): array
    {
        $manifest = Yaml::parse($this->readFile($path));

        static::assertIsArray($manifest);
        static::assertIsArray($manifest[$rootKey]);
        static::assertIsArray($manifest[$rootKey][$listKey]);

        $entries = [];

        foreach ($manifest[$rootKey][$listKey] as $entry) {
            static::assertIsArray($entry);
            static::assertIsString($entry['name']);

            $entries[$entry['name']] = $entry;
        }

        return $entries;
    }
}
