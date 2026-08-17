<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Functional;

use PHPUnit\Framework\Attributes\Group;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
#[Group('integration')]
final class CronjobCreateEndToEndTest extends AbstractTestCase
{
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(CronjobCreateCommand::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = \sys_get_temp_dir() . '/cronjob_create_e2e_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);

        parent::tearDown();
    }

    public function testCompiledCommandWritesEveryLineAsAValidCrontabEntry(): void
    {
        $commandTester = $this->runCronjobCreate([
            'cleanup' => [
                Configuration::COMMAND => 'bin/console app:cleanup',
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '0',
                    Configuration::HOUR => '3',
                ],
            ],
        ]);

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $entries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab');

        static::assertCount(2, $entries);
        static::assertSame(
            [
                'schedule' => '0 3 * * *',
                'user' => 'root',
                'command' => \sprintf("bin/console app:cleanup >> '%s/cron/cleanup.log' 2>&1", $this->baseDir),
            ],
            $entries[0],
        );
    }

    public function testCompiledCommandAppendsOneHeartbeatPerDestinationFile(): void
    {
        $this->runCronjobCreate([
            'cleanup' => [
                Configuration::COMMAND => 'bin/console app:cleanup',
                Configuration::SCHEDULE => [Configuration::MINUTE => '0'],
            ],
            'report' => [
                Configuration::COMMAND => 'bin/console app:report',
                Configuration::SCHEDULE => [Configuration::MINUTE => '30'],
                Configuration::DESTINATION_FILE => 'crontab-reports',
            ],
        ]);

        $defaultEntries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab');
        $reportEntries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab-reports');

        static::assertCount(2, $defaultEntries);
        static::assertCount(2, $reportEntries);

        static::assertSame(
            [
                'schedule' => '* * * * *',
                'user' => 'root',
                'command' => \sprintf('/bin/touch %s/cron/heartbeat.crontab', $this->baseDir),
            ],
            $defaultEntries[1],
        );
        static::assertSame(
            \sprintf('/bin/touch %s/cron/heartbeat.crontab-reports', $this->baseDir),
            $reportEntries[1]['command'],
        );
    }

    public function testCompiledCommandSplitsCommandsOntoTheirOwnDestinationFiles(): void
    {
        $commandTester = $this->runCronjobCreate([
            'cleanup' => [
                Configuration::COMMAND => 'bin/console app:cleanup',
                Configuration::SCHEDULE => [Configuration::MINUTE => '0'],
            ],
            'report' => [
                Configuration::COMMAND => 'bin/console app:report',
                Configuration::SCHEDULE => [Configuration::MINUTE => '30'],
                Configuration::DESTINATION_FILE => 'crontab-reports',
            ],
        ]);

        $display = $commandTester->getDisplay();

        static::assertStringContainsString('generated `2` conf files', $display);
        static::assertFileExists($this->baseDir . '/generated_conf/cron/crontab');
        static::assertFileExists($this->baseDir . '/generated_conf/cron/crontab-reports');

        $defaultEntries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab');

        static::assertStringContainsString('app:cleanup', $defaultEntries[0]['command']);
        static::assertStringNotContainsString('app:report', $defaultEntries[0]['command']);
    }

    public function testCompiledCommandOmitsTheHeartbeatWhenItIsDisabled(): void
    {
        $this->runCronjobCreate(
            [
                'cleanup' => [
                    Configuration::COMMAND => 'bin/console app:cleanup',
                    Configuration::SCHEDULE => [Configuration::MINUTE => '0'],
                ],
            ],
            [Configuration::HEARTBEAT => false],
        );

        $entries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab');

        static::assertCount(1, $entries);
        static::assertStringContainsString('app:cleanup', $entries[0]['command']);
    }

    public function testCompiledCommandOmitsTheLogRedirectionWhenLoggingIsDisabled(): void
    {
        $this->runCronjobCreate(
            [
                'cleanup' => [
                    Configuration::COMMAND => 'bin/console app:cleanup',
                    Configuration::SCHEDULE => [Configuration::MINUTE => '0'],
                    Configuration::SETTINGS => [Configuration::LOG => false],
                ],
            ],
            [Configuration::HEARTBEAT => false],
        );

        $entries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab');

        static::assertSame('bin/console app:cleanup', $entries[0]['command']);
    }

    public function testCompiledCommandOmitsTheUserFieldWhenNoUserIsConfigured(): void
    {
        $this->runCronjobCreate(
            [
                'cleanup' => [
                    Configuration::COMMAND => 'bin/console app:cleanup',
                    Configuration::SCHEDULE => [Configuration::MINUTE => '0'],
                ],
            ],
            [Configuration::USER => null, Configuration::HEARTBEAT => false],
        );

        $entries = $this->parseCrontab($this->baseDir . '/generated_conf/cron/crontab', false);

        static::assertCount(1, $entries);
        static::assertSame('0 * * * *', $entries[0]['schedule']);
        static::assertSame(
            \sprintf("bin/console app:cleanup >> '%s/cron/cleanup.log' 2>&1", $this->baseDir),
            $entries[0]['command'],
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCrontab(string $path, bool $withUser = true): array
    {
        $contents = \file_get_contents($path);

        static::assertIsString($contents);

        $fieldCount = true === $withUser ? 7 : 6;

        $entries = [];

        foreach (\explode("\n", $contents) as $line) {
            $line = \trim($line);

            if ('' === $line || true === \str_starts_with($line, '#')) {
                continue;
            }

            $fields = \preg_split('/\s+/', $line, $fieldCount);

            static::assertIsArray($fields);
            static::assertCount($fieldCount, $fields, \sprintf('crontab line `%s` does not have the %d expected fields', $line, $fieldCount));

            $entries[] = [
                'schedule' => \implode(' ', \array_slice($fields, 0, 5)),
                'user' => true === $withUser ? $fields[5] : '',
                'command' => $fields[$fieldCount - 1],
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $commands
     * @param array<string, mixed> $settings
     */
    private function runCronjobCreate(array $commands, array $settings = []): CommandTester
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.logs_dir', $this->baseDir);
        $containerBuilder->setParameter('kernel.project_dir', $this->baseDir);

        $containerBuilder->register(Filesystem::class, Filesystem::class);

        (new PrecisionSoftSymfonyConsoleExtension())->load([
            [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::SETTINGS => \array_merge([Configuration::USER => 'root'], $settings),
                    ],
                    Configuration::COMMANDS => $commands,
                ],
            ],
        ], $containerBuilder);

        /* nothing references the command, so RemoveUnusedDefinitionsPass would drop the private definition */
        $containerBuilder->getDefinition(CronjobCreateCommand::class)->setPublic(true);

        $containerBuilder->compile();

        $cronjobCreateCommand = $containerBuilder->get(CronjobCreateCommand::class);

        static::assertInstanceOf(CronjobCreateCommand::class, $cronjobCreateCommand);

        $commandTester = new CommandTester($cronjobCreateCommand);

        $commandTester->execute([]);

        return $commandTester;
    }
}
