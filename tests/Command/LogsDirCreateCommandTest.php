<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use PrecisionSoft\Symfony\Console\Command\LogsDirCreateCommand;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class LogsDirCreateCommandTest extends AbstractTestCase
{
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(
            LogsDirCreateCommand::class,
            null,
            true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = \sys_get_temp_dir() . '/logs_dir_create_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);

        parent::tearDown();
    }

    public function testExecuteCreatesMissingLogsDirs(): void
    {
        $logsDirs = [$this->baseDir . '/cron', $this->baseDir . '/worker'];

        $commandTester = new CommandTester(new LogsDirCreateCommand(new ConfFileWriter(new Filesystem()), $logsDirs));

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::SUCCESS, $commandTester->getStatusCode());

        foreach ($logsDirs as $logsDir) {
            static::assertDirectoryExists($logsDir);
        }

        static::assertStringContainsString('ensured `2` logs dirs', $commandTester->getDisplay());
    }

    public function testExecuteIsIdempotentWhenLogsDirsAlreadyExist(): void
    {
        $logsDirs = [$this->baseDir . '/cron'];

        (new Filesystem())->mkdir($logsDirs[0], 0755);

        $commandTester = new CommandTester(new LogsDirCreateCommand(new ConfFileWriter(new Filesystem()), $logsDirs));

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertDirectoryExists($logsDirs[0]);
    }

    public function testExecuteWithNoLogsDirsOutputsWarning(): void
    {
        $commandTester = new CommandTester(new LogsDirCreateCommand(new ConfFileWriter(new Filesystem()), []));

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('no logs dirs are configured', $commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenLogsDirCannotBeCreated(): void
    {
        (new Filesystem())->dumpFile($this->baseDir, '');

        $logsDir = $this->baseDir . '/cron';

        $commandTester = new CommandTester(new LogsDirCreateCommand(new ConfFileWriter(new Filesystem()), [$logsDir]));

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertDirectoryDoesNotExist($logsDir);
    }

    public function testExecutePartialFailureKeepsAlreadyCreatedDirs(): void
    {
        $blockedParent = $this->baseDir . '/blocked';

        (new Filesystem())->dumpFile($blockedParent, '');

        $createdLogsDir = $this->baseDir . '/cron';
        $blockedLogsDir = $blockedParent . '/worker';

        $commandTester = new CommandTester(
            new LogsDirCreateCommand(new ConfFileWriter(new Filesystem()), [$createdLogsDir, $blockedLogsDir]),
        );

        $commandTester->execute([]);

        static::assertSame(LogsDirCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertDirectoryExists($createdLogsDir);
        static::assertDirectoryDoesNotExist($blockedLogsDir);
    }
}
