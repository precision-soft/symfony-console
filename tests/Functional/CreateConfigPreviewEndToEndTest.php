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
final class CreateConfigPreviewEndToEndTest extends AbstractTestCase
{
    private const COMMANDS = [
        'orders' => ['command' => 'bin/console app:consume'],
    ];

    private Filesystem $filesystem;
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(WorkerCreateCommand::class);
    }

    public function testCheckFailsAndWritesNothingWhenTheDestinationIsMissing(): void
    {
        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--check' => true]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[added] ' . $this->getConfFilesDir() . '/orders.conf', $commandTester->getDisplay());
        static::assertFileDoesNotExist($this->getConfFilesDir() . '/orders.conf');
    }

    public function testDryRunWritesNothing(): void
    {
        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--dry-run' => true]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertDirectoryDoesNotExist($this->getConfFilesDir());
    }

    public function testCheckPassesRightAfterAGeneration(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--check' => true]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated conf files are current', $commandTester->getDisplay());
    }

    public function testCheckDetectsAManuallyEditedFile(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);
        $this->filesystem->dumpFile($this->getConfFilesDir() . '/orders.conf', 'hand edited');

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--check' => true]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[changed] ' . $this->getConfFilesDir() . '/orders.conf', $commandTester->getDisplay());
    }

    public function testDiffShowsTheEditedLinesOfAManuallyEditedFile(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);

        $contents = \file_get_contents($this->getConfFilesDir() . '/orders.conf');

        static::assertIsString($contents);

        $this->filesystem->dumpFile(
            $this->getConfFilesDir() . '/orders.conf',
            \str_replace('user = root', 'user = intruder', $contents),
        );

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--diff' => true]);
        $display = $commandTester->getDisplay();

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('--- ' . $this->getConfFilesDir() . '/orders.conf', $display);
        static::assertStringContainsString('-user = intruder', $display);
        static::assertStringContainsString('+user = root', $display);
    }

    public function testCheckDetectsAFileNoCommandDeclaresAnyMore(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);
        $this->filesystem->dumpFile($this->getConfFilesDir() . '/retired.conf', 'left behind');

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--check' => true]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[removed] ' . $this->getConfFilesDir() . '/retired.conf', $commandTester->getDisplay());
    }

    public function testAGenerationClearsTheDriftCheckReported(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);
        $this->filesystem->dumpFile($this->getConfFilesDir() . '/retired.conf', 'left behind');

        $this->runWorkerCreate(static::COMMANDS, []);

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--check' => true]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertFileDoesNotExist($this->getConfFilesDir() . '/retired.conf');
    }

    /* `generate` returns early on zero declared files, so `--check` must not report drift it could never clear */
    public function testCheckPassesOnAConfigurationWithoutCommands(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);

        $commandTester = $this->runWorkerCreate([], ['--check' => true]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated conf files are current', $commandTester->getDisplay());
    }

    public function testDiffShowsTheLinesOfAFileNoCommandDeclaresAnyMore(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);
        $this->filesystem->dumpFile($this->getConfFilesDir() . '/retired.conf', "left behind\nsecond line");

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--diff' => true]);
        $display = $commandTester->getDisplay();

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('--- ' . $this->getConfFilesDir() . '/retired.conf', $display);
        static::assertStringContainsString('+++ /dev/null', $display);
        static::assertStringContainsString('@@ -1,2 +0,0 @@', $display);
        static::assertStringContainsString('-left behind', $display);
        static::assertStringContainsString('-second line', $display);
    }

    public function testCheckAndDiffAgreeOnAFileThatDiffersOnlyInLineEndings(): void
    {
        $this->runWorkerCreate(static::COMMANDS, []);

        $contents = \file_get_contents($this->getConfFilesDir() . '/orders.conf');

        static::assertIsString($contents);

        $this->filesystem->dumpFile($this->getConfFilesDir() . '/orders.conf', \str_replace("\n", "\r\n", $contents));

        $commandTester = $this->runWorkerCreate(static::COMMANDS, ['--check' => true, '--diff' => true]);
        $display = $commandTester->getDisplay();

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[changed] ' . $this->getConfFilesDir() . '/orders.conf', $display);
        static::assertStringContainsString('\ line endings differ', $display);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->baseDir = \sys_get_temp_dir() . '/preview_e2e_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->baseDir);

        parent::tearDown();
    }

    private function getConfFilesDir(): string
    {
        return $this->baseDir . '/generated_conf/worker';
    }

    /**
     * @param array<string, mixed> $commands
     * @param array<string, mixed> $input
     */
    private function runWorkerCreate(array $commands, array $input): CommandTester
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.logs_dir', $this->baseDir);
        $containerBuilder->setParameter('kernel.project_dir', $this->baseDir);

        $containerBuilder->register(Filesystem::class, Filesystem::class);

        (new PrecisionSoftSymfonyConsoleExtension())->load([
            [
                'worker' => [
                    'config' => [
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

        $commandTester->execute($input);

        return $commandTester;
    }
}
