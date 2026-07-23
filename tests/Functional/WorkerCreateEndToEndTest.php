<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Functional;

use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @info compiles a real container and runs the command end to end, covering what the unit tests cannot: the worker
 * configuration surviving `%kernel.project_dir%` expansion inside an array parameter, `WorkerDto` being built from it,
 * and `destination_sub_dir` reaching the filesystem as an actual directory tree
 *
 * @internal
 */
final class WorkerCreateEndToEndTest extends AbstractTestCase
{
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(WorkerCreateCommand::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = \sys_get_temp_dir() . '/worker_create_e2e_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);

        parent::tearDown();
    }

    public function testCompiledCommandWritesTheSubDirectoryTreeOnDisk(): void
    {
        $commandTester = $this->runWorkerCreate([
            'inherits' => [
                'command' => 'bin/console app:one',
            ],
            'overrides' => [
                'command' => 'bin/console app:two',
                'destination_sub_dir' => 'machine-b/eu-west',
            ],
            'opts_out' => [
                'command' => 'bin/console app:three',
                'destination_sub_dir' => '',
            ],
            'suffixed' => [
                'command' => 'bin/console app:four',
                'destination_suffix' => 'blue',
            ],
        ], [
            'destination_sub_dir' => 'machine-a',
        ]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $confFilesDir = $this->baseDir . '/generated_conf/worker';

        static::assertFileExists($confFilesDir . '/machine-a/inherits.conf');
        static::assertFileExists($confFilesDir . '/machine-b/eu-west/overrides.conf');
        static::assertFileExists($confFilesDir . '/opts_out.conf');
        static::assertFileExists($confFilesDir . '/machine-a/suffixed.blue.conf');
    }

    public function testCompiledCommandReportsThePathsItActuallyWrote(): void
    {
        $commandTester = $this->runWorkerCreate([
            'reported' => [
                'command' => 'bin/console app:one',
                'destination_sub_dir' => '/machine-a/',
            ],
        ]);

        $display = $commandTester->getDisplay();

        $expectedPath = $this->baseDir . '/generated_conf/worker/machine-a/reported.conf';

        static::assertStringContainsString($expectedPath, $display);
        static::assertFileExists($expectedPath);
    }

    public function testCompiledCommandRendersTheSupervisorProgramForASubDirectoryWorker(): void
    {
        $this->runWorkerCreate([
            'rendered' => [
                'command' => 'bin/console app:one',
                'destination_sub_dir' => 'machine-a',
            ],
        ]);

        $contents = \file_get_contents($this->baseDir . '/generated_conf/worker/machine-a/rendered.conf');

        static::assertIsString($contents);
        static::assertStringContainsString('[program:app-rendered]', $contents);
        static::assertStringContainsString('command = bin/console app:one', $contents);
        /** @info the log file stays flat in `logs_dir`, the sub dir only splits the conf files */
        static::assertStringContainsString(\sprintf('stdout_logfile = %s/worker/rendered.log', $this->baseDir), $contents);
    }

    public function testCompiledCommandFailsCleanlyOnCollidingDestinationPaths(): void
    {
        $commandTester = $this->runWorkerCreate([
            'worker' => [
                'command' => 'bin/console app:one',
                'destination_suffix' => 'blue',
            ],
            'worker.blue' => [
                'command' => 'bin/console app:two',
            ],
        ]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('the file path is in use', $commandTester->getDisplay());
    }

    /**
     * @param array<string, mixed> $commands
     * @param array<string, mixed> $settings
     */
    private function runWorkerCreate(array $commands, array $settings = []): CommandTester
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.logs_dir', $this->baseDir);
        $containerBuilder->setParameter('kernel.project_dir', $this->baseDir);

        /** @info FrameworkBundle registers this in a real application, the bundle only autowires it */
        $containerBuilder->register(Filesystem::class, Filesystem::class);

        (new PrecisionSoftSymfonyConsoleExtension())->load([
            [
                'worker' => [
                    'config' => [
                        'settings' => \array_merge(['prefix' => 'app', 'user' => 'root'], $settings),
                    ],
                    'commands' => $commands,
                ],
            ],
        ], $containerBuilder);

        /** @info without `AddConsoleCommandPass` nothing references the command, so `RemoveUnusedDefinitionsPass` would drop the private definition before we can fetch it */
        $containerBuilder->getDefinition(WorkerCreateCommand::class)->setPublic(true);

        $containerBuilder->compile();

        $workerCreateCommand = $containerBuilder->get(WorkerCreateCommand::class);

        static::assertInstanceOf(WorkerCreateCommand::class, $workerCreateCommand);

        $commandTester = new CommandTester($workerCreateCommand);

        $commandTester->execute([]);

        return $commandTester;
    }
}
