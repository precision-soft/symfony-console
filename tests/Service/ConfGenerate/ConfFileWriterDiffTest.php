<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service\ConfGenerate;

use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileStatus;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Console\Test\Utility\UnreadableConfFileWriter;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ConfFileWriterDiffTest extends AbstractTestCase
{
    private Filesystem $filesystem;
    private ConfFileWriter $confFileWriter;
    private string $destinationDirectory;

    public static function getMockDto(): MockDto
    {
        return new MockDto(
            ConfFileWriter::class,
            [new Filesystem()],
            true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->confFileWriter = new ConfFileWriter($this->filesystem);
        $this->destinationDirectory = \sys_get_temp_dir() . '/cfw_diff_' . \uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->destinationDirectory);

        parent::tearDown();
    }

    public function testDiffReportsAnUnwrittenFileAsAdded(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/added.conf', 'new');

        $confFileChangeDto = $this->getChange($confFilesDto, '/added.conf');

        static::assertSame(ConfFileStatus::Added, $confFileChangeDto->getStatus());
        static::assertSame('new', $confFileChangeDto->getExpectedContent());
        static::assertNull($confFileChangeDto->getCurrentContent());
    }

    public function testDiffReportsADifferentContentAsChangedCarryingBothSides(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/changed.conf', 'before');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/changed.conf', 'after');

        $confFileChangeDto = $this->getChange($confFilesDto, '/changed.conf');

        static::assertSame(ConfFileStatus::Changed, $confFileChangeDto->getStatus());
        static::assertSame('after', $confFileChangeDto->getExpectedContent());
        static::assertSame('before', $confFileChangeDto->getCurrentContent());
    }

    public function testDiffReportsAnIdenticalContentAsUnchanged(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/unchanged.conf', 'same');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/unchanged.conf', 'same');

        static::assertSame(
            ConfFileStatus::Unchanged,
            $this->getChange($confFilesDto, '/unchanged.conf')->getStatus(),
        );
    }

    public function testDiffReportsAFileNoCommandDeclaresAsRemoved(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/stale.conf', 'old');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/kept.conf', 'kept');

        static::assertSame(
            ConfFileStatus::Removed,
            $this->getChange($confFilesDto, '/stale.conf')->getStatus(),
        );
    }

    public function testDiffWalksSubDirectoriesForRemovedFiles(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/machine-a/eu-west/stale.conf', 'old');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/machine-a/kept.conf', 'kept');

        static::assertSame(
            ConfFileStatus::Removed,
            $this->getChange($confFilesDto, '/machine-a/eu-west/stale.conf')->getStatus(),
        );
    }

    public function testDiffToleratesATrailingSeparatorOnTheDestination(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/stale.conf', 'old');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/kept.conf', 'kept');

        $changes = $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory . '/')->getChanges();

        static::assertSame(ConfFileStatus::Added, $changes[$this->destinationDirectory . '/kept.conf']->getStatus());
        static::assertSame(ConfFileStatus::Removed, $changes[$this->destinationDirectory . '/stale.conf']->getStatus());
    }

    public function testDiffSortsChangesByPath(): void
    {
        $confFilesDto = (new ConfFilesDto())
            ->addFile($this->destinationDirectory . '/second.conf', 'second')
            ->addFile($this->destinationDirectory . '/first.conf', 'first');

        static::assertSame(
            [$this->destinationDirectory . '/first.conf', $this->destinationDirectory . '/second.conf'],
            \array_keys($this->confFileWriter->diff($confFilesDto, $this->destinationDirectory)->getChanges()),
        );
    }

    /* `save()` returns early on an empty dto, so reporting removals here would be drift no generation could ever clear */
    public function testDiffOnAnEmptyDtoReportsNothingEvenWithAPopulatedDestination(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/existing.conf', 'kept by save');

        static::assertSame(
            [],
            $this->confFileWriter->diff(new ConfFilesDto(), $this->destinationDirectory)->getChanges(),
        );
    }

    public function testDiffOnAMissingDestinationReportsEverythingAsAdded(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/added.conf', 'new');

        static::assertSame(
            ConfFileStatus::Added,
            $this->getChange($confFilesDto, '/added.conf')->getStatus(),
        );
    }

    public function testDiffReportsASymlinkedGeneratedPathAsAdded(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/target.conf', 'target');
        \symlink($this->destinationDirectory . '/target.conf', $this->destinationDirectory . '/linked.conf');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/linked.conf', 'target');

        static::assertSame(
            ConfFileStatus::Added,
            $this->getChange($confFilesDto, '/linked.conf')->getStatus(),
        );
    }

    /* activation renames the destination path itself, so a symlink there would be replaced rather than followed */
    public function testDiffThrowsOnASymlinkedDestination(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/real/stale.conf', 'old');
        \symlink($this->destinationDirectory . '/real', $this->destinationDirectory . '/linked');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/linked/kept.conf', 'kept');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('is a symlink, and the atomic activation would replace it');

        $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory . '/linked');
    }

    /* an unnormalized destination used to spell the same file two ways and report it as current and removed at once */
    public function testDiffDoesNotDoubleReportAFileUnderAnUnnormalizedDestination(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/kept.conf', 'kept');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/kept.conf', 'kept');

        $changes = $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory . '//')->getChanges();

        static::assertSame([$this->destinationDirectory . '/kept.conf'], \array_keys($changes));
        static::assertSame(ConfFileStatus::Unchanged, $changes[$this->destinationDirectory . '/kept.conf']->getStatus());
    }

    public function testDiffReportsRemovalsUnderAnUnnormalizedDestination(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/stale.conf', 'old');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/kept.conf', 'kept');

        $changes = $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory . '//')->getChanges();

        static::assertSame(
            [$this->destinationDirectory . '/kept.conf', $this->destinationDirectory . '/stale.conf'],
            \array_keys($changes),
        );
    }

    public function testDiffThrowsOnAPathOutsideTheDestination(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile(\sys_get_temp_dir() . '/outside.conf', 'outside');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('is outside destination directory');

        $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory);
    }

    public function testDiffThrowsOnPathTraversal(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/../escaped.conf', 'escaped');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('invalid generated path');

        $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory);
    }

    public function testDiffThrowsOnAnEmptyRelativePath(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/', 'nothing');

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('invalid generated path');

        $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory);
    }

    public function testDiffThrowsWhenAGeneratedPathCannotBeRead(): void
    {
        $this->filesystem->dumpFile($this->destinationDirectory . '/unreadable.conf', 'secret');

        $confFilesDto = (new ConfFilesDto())->addFile($this->destinationDirectory . '/unreadable.conf', 'other');

        $unreadableConfFileWriter = new UnreadableConfFileWriter($this->filesystem);

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('could not be read');

        $unreadableConfFileWriter->diff($confFilesDto, $this->destinationDirectory);
    }

    private function getChange(ConfFilesDto $confFilesDto, string $relativePath): ConfFileChangeDto
    {
        $changes = $this->confFileWriter->diff($confFilesDto, $this->destinationDirectory)->getChanges();

        static::assertArrayHasKey($this->destinationDirectory . $relativePath, $changes);

        return $changes[$this->destinationDirectory . $relativePath];
    }
}
