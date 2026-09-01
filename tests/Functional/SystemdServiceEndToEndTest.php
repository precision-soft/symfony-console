<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Functional;

use PHPUnit\Framework\Attributes\Group;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Console\Template\SystemdServiceTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
#[Group('integration')]
final class SystemdServiceEndToEndTest extends AbstractTestCase
{
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(WorkerCreateCommand::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = \sys_get_temp_dir() . '/systemd_e2e_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);

        parent::tearDown();
    }

    /* the template is only reachable through the CONSOLE_TEMPLATE tag, so this proves the wiring, not just the class */
    public function testTheTaggedTemplateIsResolvedFromTheCompiledContainer(): void
    {
        $commandTester = $this->runWorkerCreate([
            'orders' => [
                'command' => ['/usr/bin/php', 'bin/console', 'app:consume'],
            ],
        ]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertFileExists($this->getUnitsDir() . '/app-orders.service');
    }

    public function testOneUnitIsWrittenPerConfiguredInstance(): void
    {
        $this->runWorkerCreate([
            'orders' => [
                'command' => ['/usr/bin/php', 'bin/console', 'app:consume'],
                'settings' => ['number_of_processes' => 3],
            ],
        ]);

        static::assertFileExists($this->getUnitsDir() . '/app-orders-1.service');
        static::assertFileExists($this->getUnitsDir() . '/app-orders-2.service');
        static::assertFileExists($this->getUnitsDir() . '/app-orders-3.service');
        static::assertFileDoesNotExist($this->getUnitsDir() . '/app-orders.service');
    }

    public function testTheWrittenUnitCarriesTheResolvedContainerParameters(): void
    {
        $this->runWorkerCreate([
            'orders' => [
                'command' => ['/usr/bin/php', 'bin/console', 'app:consume'],
            ],
        ]);

        $contents = \file_get_contents($this->getUnitsDir() . '/app-orders.service');

        static::assertIsString($contents);

        $unit = \parse_ini_string($contents, true, \INI_SCANNER_RAW);

        static::assertIsArray($unit);
        static::assertSame(
            [
                'Type' => 'simple',
                'User' => 'root',
                'WorkingDirectory' => $this->baseDir,
                'ExecStart' => '/usr/bin/php bin/console app:consume',
                'Restart' => SystemdServiceTemplate::DEFAULT_RESTART_POLICY,
                'StandardOutput' => 'append:' . $this->baseDir . '/worker/orders.log',
                'StandardError' => 'append:' . $this->baseDir . '/worker/orders.log',
            ],
            $unit['Service'],
        );
        static::assertSame(['WantedBy' => 'multi-user.target'], $unit['Install']);
        static::assertSame('app-orders instance 1', $unit['Unit']['Description']);
    }

    public function testTheWrittenUnitCarriesTheOverriddenRuntimeSettings(): void
    {
        $this->runWorkerCreate([
            'orders' => [
                'command' => ['/usr/bin/php', 'bin/console', 'app:consume'],
                'settings' => [
                    'user' => 'www-data',
                    'working_directory' => '/srv/app',
                    'environment_file' => '/srv/app/.env',
                    'restart_policy' => 'on-failure',
                    'standard_output' => 'journal',
                    'standard_error' => 'journal',
                ],
            ],
        ]);

        $contents = \file_get_contents($this->getUnitsDir() . '/app-orders.service');

        static::assertIsString($contents);

        $unit = \parse_ini_string($contents, true, \INI_SCANNER_RAW);

        static::assertIsArray($unit);
        static::assertSame('www-data', $unit['Service']['User']);
        static::assertSame('/srv/app', $unit['Service']['WorkingDirectory']);
        static::assertSame('/srv/app/.env', $unit['Service']['EnvironmentFile']);
        static::assertSame('on-failure', $unit['Service']['Restart']);
        static::assertSame('journal', $unit['Service']['StandardOutput']);
    }

    public function testARelativeExecutableFailsTheCommandInsteadOfWritingAnUnstartableUnit(): void
    {
        $commandTester = $this->runWorkerCreate([
            'orders' => [
                'command' => ['php', 'bin/console', 'app:consume'],
            ],
        ]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('needs an absolute executable', $commandTester->getDisplay());
        static::assertFileDoesNotExist($this->getUnitsDir() . '/app-orders.service');
    }

    public function testUnitsAreWrittenIntoTheDestinationSubDirectory(): void
    {
        $this->runWorkerCreate([
            'orders' => [
                'command' => ['/usr/bin/php', 'bin/console', 'app:consume'],
                'destination_sub_dir' => 'machine-a/eu-west',
                'destination_suffix' => 'blue',
            ],
        ]);

        static::assertFileExists($this->getUnitsDir() . '/machine-a/eu-west/app-orders.blue.service');
    }

    private function getUnitsDir(): string
    {
        return $this->baseDir . '/generated_conf/worker';
    }

    /** @param array<string, mixed> $commands */
    private function runWorkerCreate(array $commands): CommandTester
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.logs_dir', $this->baseDir);
        $containerBuilder->setParameter('kernel.project_dir', $this->baseDir);

        $containerBuilder->register(Filesystem::class, Filesystem::class);

        (new PrecisionSoftSymfonyConsoleExtension())->load([
            [
                'worker' => [
                    'config' => [
                        'template_class' => SystemdServiceTemplate::class,
                        'settings' => ['prefix' => 'app', 'user' => 'root'],
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
