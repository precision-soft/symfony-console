<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Functional;

use PHPUnit\Framework\Attributes\Group;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
#[Group('integration')]
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

    public function testCompiledCommandRendersAParsableSupervisorProgramForASubDirectoryWorker(): void
    {
        $this->runWorkerCreate([
            'rendered' => [
                'command' => 'bin/console app:one',
                'destination_sub_dir' => 'machine-a',
            ],
        ]);

        $contents = \file_get_contents($this->baseDir . '/generated_conf/worker/machine-a/rendered.conf');

        static::assertIsString($contents);

        $program = \parse_ini_string($contents, true, \INI_SCANNER_RAW);

        static::assertIsArray($program);
        static::assertArrayHasKey('program:app-rendered', $program);
        static::assertSame(
            [
                'command' => 'bin/console app:one',
                'process_name' => '%(program_name)s_%(process_num)s',
                'numprocs' => '1',
                'autostart' => 'true',
                'autorestart' => 'true',
                'stdout_logfile' => $this->baseDir . '/worker/rendered.log',
                'stderr_logfile' => $this->baseDir . '/worker/rendered.log',
                'user' => 'root',
                'stopwaitsecs' => '30',
                'stdout_logfile_maxbytes' => '0',
                'stderr_logfile_maxbytes' => '0',
                'stdout_logfile_backups' => '0',
                'stderr_logfile_backups' => '0',
                'startsecs' => '0',
            ],
            $program['program:app-rendered'],
        );
    }

    public function testCompiledCommandRendersTheOverriddenSupervisorSettings(): void
    {
        $this->runWorkerCreate([
            'overridden' => [
                'command' => 'bin/console app:one',
                'settings' => [
                    'number_of_processes' => 4,
                    'auto_start' => false,
                    'auto_restart' => false,
                    'prefix' => 'other',
                    'user' => 'www-data',
                ],
            ],
        ]);

        $contents = \file_get_contents($this->baseDir . '/generated_conf/worker/overridden.conf');

        static::assertIsString($contents);

        $program = \parse_ini_string($contents, true, \INI_SCANNER_RAW);

        static::assertIsArray($program);
        static::assertArrayHasKey('program:other-overridden', $program);
        static::assertSame('4', $program['program:other-overridden']['numprocs']);
        static::assertSame('false', $program['program:other-overridden']['autostart']);
        static::assertSame('false', $program['program:other-overridden']['autorestart']);
        static::assertSame('www-data', $program['program:other-overridden']['user']);
    }

    public function testASemicolonInACommandTruncatesTheDirectiveWhenTheConfIsReadBack(): void
    {
        $this->runWorkerCreate([
            'truncated' => [
                'command' => ['sh', '-c', 'bin/console app:one; bin/console app:two'],
            ],
        ]);

        $contents = \file_get_contents($this->baseDir . '/generated_conf/worker/truncated.conf');

        static::assertIsString($contents);
        static::assertStringContainsString('command = sh -c bin/console app:one; bin/console app:two', $contents);

        $program = \parse_ini_string($contents, true, \INI_SCANNER_RAW);

        static::assertIsArray($program);
        static::assertSame('sh -c bin/console app:one', $program['program:app-truncated']['command']);
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

        /* nothing references the command, so RemoveUnusedDefinitionsPass would drop the private definition */
        $containerBuilder->getDefinition(WorkerCreateCommand::class)->setPublic(true);

        $containerBuilder->compile();

        $workerCreateCommand = $containerBuilder->get(WorkerCreateCommand::class);

        static::assertInstanceOf(WorkerCreateCommand::class, $workerCreateCommand);

        $commandTester = new CommandTester($workerCreateCommand);

        $commandTester->execute([]);

        return $commandTester;
    }
}
