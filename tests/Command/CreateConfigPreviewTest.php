<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Command;

use Mockery;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileChangesDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileStatus;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileDiffRenderer;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Console\Template\SupervisorTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class CreateConfigPreviewTest extends AbstractTestCase
{
    private const PATH = '/tmp/generated-worker/worker.conf';

    public static function getMockDto(): MockDto
    {
        return new MockDto(
            WorkerCreateCommand::class,
            null,
            true,
        );
    }

    public function testDryRunReportsTheStatusWithoutGenerating(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Changed), false);

        $commandTester->execute(['--dry-run' => true], ['interactive' => false]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('[changed] ' . static::PATH, $commandTester->getDisplay());
        static::assertStringContainsString('generated conf file changes detected', $commandTester->getDisplay());
    }

    public function testDryRunDoesNotRenderTheContentDiff(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Changed), false);

        $commandTester->execute(['--dry-run' => true], ['interactive' => false]);

        static::assertStringNotContainsString('@@', $commandTester->getDisplay());
    }

    public function testDiffRendersTheContentDiff(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Changed), true);

        $commandTester->execute(['--diff' => true], ['interactive' => false]);

        $display = $commandTester->getDisplay();

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('[changed] ' . static::PATH, $display);
        static::assertStringContainsString('--- ' . static::PATH, $display);
        static::assertStringContainsString('-user = old', $display);
        static::assertStringContainsString('+user = new', $display);
    }

    /* the console formatter would swallow an angle bracket it reads as a tag, so a diff line is escaped */
    public function testDiffEscapesMarkupCharactersInTheRenderedContent(): void
    {
        $confFileChangesDto = (new ConfFileChangesDto())->addChange(
            new ConfFileChangeDto(static::PATH, ConfFileStatus::Changed, 'args: <after>', 'args: <before>'),
        );

        $commandTester = $this->getCommandTester($confFileChangesDto, true);

        $commandTester->execute(['--diff' => true], ['interactive' => false]);

        static::assertStringContainsString('-args: <before>', $commandTester->getDisplay());
        static::assertStringContainsString('+args: <after>', $commandTester->getDisplay());
    }

    public function testDiffEmitsTheWholeContentOfAnAddedPath(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Added), true);

        $commandTester->execute(['--diff' => true], ['interactive' => false]);

        $display = $commandTester->getDisplay();

        static::assertStringContainsString('--- ' . ConfFileDiffRenderer::NO_FILE, $display);
        static::assertStringContainsString('+user = new', $display);
    }

    public function testDiffEmitsTheWholeContentOfARemovedPath(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Removed), true);

        $commandTester->execute(['--diff' => true], ['interactive' => false]);

        $display = $commandTester->getDisplay();

        static::assertStringContainsString('+++ ' . ConfFileDiffRenderer::NO_FILE, $display);
        static::assertStringContainsString('-user = old', $display);
    }

    public function testCheckFailsWhenChangesArePending(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Added), false);

        $commandTester->execute(['--check' => true], ['interactive' => false]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[added] ' . static::PATH, $commandTester->getDisplay());
    }

    public function testCheckReportsARemovedFile(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Removed), false);

        $commandTester->execute(['--check' => true], ['interactive' => false]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('[removed] ' . static::PATH, $commandTester->getDisplay());
    }

    public function testCheckPassesWhenFilesAreCurrent(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Unchanged), false);

        $commandTester->execute(['--check' => true], ['interactive' => false]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated conf files are current', $commandTester->getDisplay());
    }

    public function testCheckPassesWhenNothingIsDeclared(): void
    {
        $commandTester = $this->getCommandTester(new ConfFileChangesDto(), false);

        $commandTester->execute(['--check' => true], ['interactive' => false]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('generated conf files are current', $commandTester->getDisplay());
    }

    public function testCheckCombinedWithDiffRendersAndStillFails(): void
    {
        $commandTester = $this->getCommandTester($this->getChanges(ConfFileStatus::Changed), true);

        $commandTester->execute(['--check' => true, '--diff' => true], ['interactive' => false]);

        static::assertSame(WorkerCreateCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('+user = new', $commandTester->getDisplay());
    }

    private function getCommandTester(ConfFileChangesDto $confFileChangesDto, bool $diffExpected): CommandTester
    {
        $confGenerateServiceMock = Mockery::mock(ConfGenerateService::class);
        $confGenerateServiceMock->shouldReceive('preview')
            ->once()
            ->andReturn($confFileChangesDto);
        $confGenerateServiceMock->shouldNotReceive('generate');

        $confFileDiffRenderer = new ConfFileDiffRenderer();
        $confGenerateServiceMock->shouldReceive('renderChangeDiff')
            ->times(true === $diffExpected ? \count($confFileChangesDto->getPendingChanges()) : 0)
            ->andReturnUsing(
                static fn(ConfFileChangeDto $confFileChangeDto): array => $confFileDiffRenderer->render($confFileChangeDto),
            );

        return new CommandTester(new WorkerCreateCommand($confGenerateServiceMock, $this->getConfiguration()));
    }

    private function getChanges(ConfFileStatus $confFileStatus): ConfFileChangesDto
    {
        return (new ConfFileChangesDto())->addChange(
            new ConfFileChangeDto(
                static::PATH,
                $confFileStatus,
                ConfFileStatus::Removed === $confFileStatus ? null : 'user = new',
                ConfFileStatus::Added === $confFileStatus ? null : 'user = old',
            ),
        );
    }

    /** @return array<string, mixed> */
    private function getConfiguration(): array
    {
        return [
            Configuration::CONFIG => [
                Configuration::TEMPLATE_CLASS => SupervisorTemplate::class,
                Configuration::CONF_FILES_DIR => '/tmp/generated-worker',
                Configuration::LOGS_DIR => '/tmp/generated-worker-logs',
                Configuration::SETTINGS => [],
            ],
            Configuration::COMMANDS => [],
        ];
    }
}
