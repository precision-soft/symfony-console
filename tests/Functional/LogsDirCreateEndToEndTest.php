<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Functional;

use PHPUnit\Framework\Attributes\Group;
use PrecisionSoft\Symfony\Console\Command\LogsDirCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
#[Group('integration')]
final class LogsDirCreateEndToEndTest extends AbstractTestCase
{
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(LogsDirCreateCommand::class);
    }

    public function testCompiledContainerResolvesLogsDirsParameter(): void
    {
        $containerBuilder = $this->buildCompiledContainer();

        $logsDirs = $containerBuilder->getParameter('precision_soft_symfony_console.logs_dirs');

        static::assertSame([$this->baseDir . '/cron', $this->baseDir . '/worker'], $logsDirs);
    }

    public function testCompiledCommandCreatesLogsDirsOnDisk(): void
    {
        $containerBuilder = $this->buildCompiledContainer();

        $logsDirCreateCommand = $containerBuilder->get(LogsDirCreateCommand::class);

        static::assertInstanceOf(LogsDirCreateCommand::class, $logsDirCreateCommand);

        $commandTester = new CommandTester($logsDirCreateCommand);

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertDirectoryExists($this->baseDir . '/cron');
        static::assertDirectoryExists($this->baseDir . '/worker');
        static::assertStringContainsString('ensured `2` logs dirs', $commandTester->getDisplay());
    }

    public function testCompiledCommandCreatesExtraLogsDirsFromConfiguration(): void
    {
        $containerBuilder = $this->buildCompiledContainer([
            'logs_dirs' => ['%kernel.logs_dir%/command'],
        ]);

        $logsDirCreateCommand = $containerBuilder->get(LogsDirCreateCommand::class);

        static::assertInstanceOf(LogsDirCreateCommand::class, $logsDirCreateCommand);

        $commandTester = new CommandTester($logsDirCreateCommand);

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertDirectoryExists($this->baseDir . '/command');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = \sys_get_temp_dir() . '/logs_dir_e2e_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);

        parent::tearDown();
    }

    /** @param array<string, mixed> $configuration */
    private function buildCompiledContainer(array $configuration = []): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.logs_dir', $this->baseDir);
        $containerBuilder->setParameter('kernel.project_dir', $this->baseDir);

        $containerBuilder->register(Filesystem::class, Filesystem::class);

        (new PrecisionSoftSymfonyConsoleExtension())->load([$configuration], $containerBuilder);

        /* nothing references the command, so RemoveUnusedDefinitionsPass would drop the private definition */
        $containerBuilder->getDefinition(LogsDirCreateCommand::class)->setPublic(true);

        $containerBuilder->compile();

        return $containerBuilder;
    }
}
