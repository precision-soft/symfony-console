<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class AbstractCommandTestStub extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('stub:command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::SUCCESS;
    }
}

/**
 * @internal
 */
final class AbstractCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(AbstractCommandTestStub::class, [], true);
    }

    public function testInitializeSkipsTitleWhenOutputIsNotDecorated(): void
    {
        $abstractCommandTestStub = new AbstractCommandTestStub();
        $commandTester = new CommandTester($abstractCommandTestStub);

        $commandTester->execute([], ['decorated' => false]);

        static::assertSame(AbstractCommandTestStub::SUCCESS, $commandTester->getStatusCode());
        static::assertStringNotContainsString('stub:command', $commandTester->getDisplay());
    }

    public function testInitializeSkipsTitleWhenVerbosityIsQuiet(): void
    {
        $abstractCommandTestStub = new AbstractCommandTestStub();
        $commandTester = new CommandTester($abstractCommandTestStub);

        $commandTester->execute([], [
            'decorated' => true,
            'verbosity' => OutputInterface::VERBOSITY_QUIET,
        ]);

        static::assertSame(AbstractCommandTestStub::SUCCESS, $commandTester->getStatusCode());
        static::assertSame('', $commandTester->getDisplay());
    }

    public function testInitializeEmitsTitleWhenDecoratedAndVerbose(): void
    {
        $abstractCommandTestStub = new AbstractCommandTestStub();
        $commandTester = new CommandTester($abstractCommandTestStub);

        $commandTester->execute([], ['decorated' => true]);

        static::assertSame(AbstractCommandTestStub::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('stub:command', $commandTester->getDisplay());
    }
}
