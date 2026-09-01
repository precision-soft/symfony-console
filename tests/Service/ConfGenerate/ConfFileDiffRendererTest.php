<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service\ConfGenerate;

use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileStatus;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileDiffRenderer;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class ConfFileDiffRendererTest extends AbstractTestCase
{
    private const PATH = '/units/worker.service';

    private ConfFileDiffRenderer $confFileDiffRenderer;

    public static function getMockDto(): MockDto
    {
        return new MockDto(ConfFileDiffRenderer::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->confFileDiffRenderer = new ConfFileDiffRenderer();
    }

    public function testRenderReturnsNothingForAnUnchangedFile(): void
    {
        static::assertSame([], $this->confFileDiffRenderer->render($this->getChange(ConfFileStatus::Unchanged, 'same', 'same')));
    }

    public function testRenderReturnsNothingWhenAChangedFileHasNoDifferingLine(): void
    {
        static::assertSame([], $this->confFileDiffRenderer->render($this->getChange(ConfFileStatus::Changed, 'same', 'same')));
    }

    public function testRenderEmitsTheWholeContentForAnAddedFile(): void
    {
        static::assertSame(
            [
                '--- ' . ConfFileDiffRenderer::NO_FILE,
                '+++ ' . static::PATH,
                '@@ -0,0 +1,2 @@',
                '+one',
                '+two',
            ],
            $this->confFileDiffRenderer->render($this->getChange(ConfFileStatus::Added, "one\ntwo", null)),
        );
    }

    public function testRenderEmitsTheWholeContentForARemovedFile(): void
    {
        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . ConfFileDiffRenderer::NO_FILE,
                '@@ -1,2 +0,0 @@',
                '-one',
                '-two',
            ],
            $this->confFileDiffRenderer->render($this->getChange(ConfFileStatus::Removed, null, "one\ntwo")),
        );
    }

    public function testRenderPutsTheRemovalBeforeTheAdditionOfAReplacedLine(): void
    {
        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -1,3 +1,3 @@',
                ' one',
                '-old',
                '+new',
                ' three',
            ],
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, "one\nnew\nthree", "one\nold\nthree"),
            ),
        );
    }

    public function testRenderKeepsThreeLinesOfContextAroundAChange(): void
    {
        $currentLines = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $expectedLines = $currentLines;
        $expectedLines[4] = 'changed';

        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -2,7 +2,7 @@',
                ' two',
                ' three',
                ' four',
                '-five',
                '+changed',
                ' six',
                ' seven',
                ' eight',
            ],
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
            ),
        );
    }

    public function testRenderSplitsTwoDistantChangesIntoSeparateHunks(): void
    {
        $currentLines = \array_map(static fn(int $line): string => 'line' . $line, \range(1, 20));
        $expectedLines = $currentLines;
        $expectedLines[1] = 'changed2';
        $expectedLines[17] = 'changed18';

        $diffLines = $this->confFileDiffRenderer->render(
            $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
        );

        static::assertSame(['@@ -1,5 +1,5 @@', '@@ -15,6 +15,6 @@'], \array_values(\array_filter(
            $diffLines,
            static fn(string $diffLine): bool => true === \str_starts_with($diffLine, '@@'),
        )));
        static::assertContains('-line2', $diffLines);
        static::assertContains('+changed2', $diffLines);
        static::assertContains('-line18', $diffLines);
        static::assertContains('+changed18', $diffLines);
    }

    public function testRenderMergesTwoNearChangesIntoASingleHunk(): void
    {
        $currentLines = \array_map(static fn(int $line): string => 'line' . $line, \range(1, 12));
        $expectedLines = $currentLines;
        $expectedLines[4] = 'changed5';
        $expectedLines[6] = 'changed7';

        $diffLines = $this->confFileDiffRenderer->render(
            $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
        );

        static::assertCount(1, \array_filter(
            $diffLines,
            static fn(string $diffLine): bool => true === \str_starts_with($diffLine, '@@'),
        ));
    }

    public function testRenderReportsAnAppendedLine(): void
    {
        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -1,2 +1,3 @@',
                ' one',
                ' two',
                '+three',
            ],
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, "one\ntwo\nthree", "one\ntwo"),
            ),
        );
    }

    public function testRenderTreatsCarriageReturnsAsLineBreaks(): void
    {
        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -1,2 +1,2 @@',
                ' one',
                '-old',
                '+new',
            ],
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, "one\r\nnew", "one\r\nold"),
            ),
        );
    }

    public function testRenderKeepsMarkupCharactersVerbatim(): void
    {
        $diffLines = $this->confFileDiffRenderer->render(
            $this->getChange(ConfFileStatus::Changed, 'args: <after>', 'args: <before>'),
        );

        static::assertContains('-args: <before>', $diffLines);
        static::assertContains('+args: <after>', $diffLines);
    }

    /* the longest common subsequence table is quadratic, so an oversized pair is emitted as a whole replacement */
    public function testRenderFallsBackToAWholeReplacementBeyondTheComparedLineBudget(): void
    {
        $currentContent = \implode("\n", \array_map(static fn(int $line): string => 'line' . $line, \range(1, 1200)));
        $expectedContent = \implode("\n", \array_map(static fn(int $line): string => 'other' . $line, \range(1, 1200)));

        $diffLines = $this->confFileDiffRenderer->render(
            $this->getChange(ConfFileStatus::Changed, $expectedContent, $currentContent),
        );

        static::assertSame('@@ -1,1200 +1,1200 @@', $diffLines[2]);
        static::assertSame('-line1', $diffLines[3]);
        static::assertSame('+other1', $diffLines[1203]);
    }

    public function testRenderHandlesAnEmptyExpectedContent(): void
    {
        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -1,2 +0,0 @@',
                '-one',
                '-two',
            ],
            $this->confFileDiffRenderer->render($this->getChange(ConfFileStatus::Changed, '', "one\ntwo")),
        );
    }

    public function testRenderClampsTheHunkToTheFirstLine(): void
    {
        $currentLines = \array_map(static fn(int $line): string => 'line' . $line, \range(1, 10));
        $expectedLines = $currentLines;
        $expectedLines[0] = 'changed1';

        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -1,4 +1,4 @@',
                '-line1',
                '+changed1',
                ' line2',
                ' line3',
                ' line4',
            ],
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
            ),
        );
    }

    public function testRenderClampsTheHunkToTheLastLine(): void
    {
        $currentLines = \array_map(static fn(int $line): string => 'line' . $line, \range(1, 10));
        $expectedLines = $currentLines;
        $expectedLines[9] = 'changed10';

        static::assertSame(
            [
                '--- ' . static::PATH,
                '+++ ' . static::PATH,
                '@@ -7,4 +7,4 @@',
                ' line7',
                ' line8',
                ' line9',
                '-line10',
                '+changed10',
            ],
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
            ),
        );
    }

    /* seven lines apart is the widest gap whose context blocks still touch, so it stays one hunk */
    public function testRenderMergesChangesSevenLinesApart(): void
    {
        static::assertSame(
            ['@@ -1,11 +1,11 @@'],
            $this->getHunkHeaders(0, 7),
        );
    }

    public function testRenderSplitsChangesEightLinesApart(): void
    {
        static::assertSame(
            ['@@ -1,4 +1,4 @@', '@@ -6,7 +6,7 @@'],
            $this->getHunkHeaders(0, 8),
        );
    }

    /**
     * The property every unified diff must satisfy: each hunk header must describe its own body, and both sides of
     * that body must be the exact slices of the two sources it claims. A hand written diff is worth fuzzing, so the
     * seed is fixed and the failure message names the counter example.
     */
    public function testRenderSatisfiesTheUnifiedDiffInvariantAcrossFuzzedInputs(): void
    {
        \mt_srand(20260901);

        for ($iteration = 0; $iteration < 500; ++$iteration) {
            $currentLines = $this->getRandomLines();
            $expectedLines = $this->getRandomLines();

            static::assertNull(
                $this->getInvariantViolation($currentLines, $expectedLines),
                \sprintf(
                    'current %s against expected %s',
                    \json_encode($currentLines),
                    \json_encode($expectedLines),
                ),
            );
        }
    }

    /** @return array<int, string> */
    private function getRandomLines(): array
    {
        $lines = [];

        for ($line = 0, $lineCount = \mt_rand(0, 12); $line < $lineCount; ++$line) {
            $lines[] = 'abcd'[\mt_rand(0, 3)] . \mt_rand(0, 3);
        }

        return $lines;
    }

    /**
     * @param array<int, string> $currentLines
     * @param array<int, string> $expectedLines
     */
    private function getInvariantViolation(array $currentLines, array $expectedLines): ?string
    {
        $diffLines = $this->confFileDiffRenderer->render(
            $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
        );

        if ([] === $diffLines) {
            return $currentLines === $expectedLines ? null : 'an empty diff for differing content';
        }

        $bodyLines = \array_slice($diffLines, 2);
        $bodyIndex = 0;
        $bodyCount = \count($bodyLines);

        while ($bodyIndex < $bodyCount) {
            if (1 !== \preg_match('/^@@ -(\d+),(\d+) \+(\d+),(\d+) @@$/', $bodyLines[$bodyIndex], $matches)) {
                return \sprintf('a hunk header was expected, got `%s`', $bodyLines[$bodyIndex]);
            }

            [, $currentStart, $currentCount, $expectedStart, $expectedCount] = \array_map('\intval', $matches);
            ++$bodyIndex;

            $currentSide = [];
            $expectedSide = [];

            while ($bodyIndex < $bodyCount && false === \str_starts_with($bodyLines[$bodyIndex], '@@ ')) {
                $marker = $bodyLines[$bodyIndex][0];
                $line = \substr($bodyLines[$bodyIndex], 1);

                if ('+' !== $marker) {
                    $currentSide[] = $line;
                }

                if ('-' !== $marker) {
                    $expectedSide[] = $line;
                }

                ++$bodyIndex;
            }

            if (\count($currentSide) !== $currentCount) {
                return \sprintf('the hunk claims %s current lines and carries %s', $currentCount, \count($currentSide));
            }

            if (\count($expectedSide) !== $expectedCount) {
                return \sprintf('the hunk claims %s expected lines and carries %s', $expectedCount, \count($expectedSide));
            }

            if (0 !== $currentCount && $currentSide !== \array_slice($currentLines, $currentStart - 1, $currentCount)) {
                return \sprintf('the current side of the hunk at -%s,%s is not that slice of the source', $currentStart, $currentCount);
            }

            if (0 !== $expectedCount && $expectedSide !== \array_slice($expectedLines, $expectedStart - 1, $expectedCount)) {
                return \sprintf('the expected side of the hunk at +%s,%s is not that slice of the source', $expectedStart, $expectedCount);
            }
        }

        return null;
    }

    private function getChange(ConfFileStatus $confFileStatus, ?string $expectedContent, ?string $currentContent): ConfFileChangeDto
    {
        return new ConfFileChangeDto(static::PATH, $confFileStatus, $expectedContent, $currentContent);
    }

    /** @return array<int, string> */
    private function getHunkHeaders(int $firstChangedIndex, int $secondChangedIndex): array
    {
        $currentLines = \array_map(static fn(int $line): string => 'line' . $line, \range(1, 25));
        $expectedLines = $currentLines;
        $expectedLines[$firstChangedIndex] = 'first';
        $expectedLines[$secondChangedIndex] = 'second';

        return \array_values(\array_filter(
            $this->confFileDiffRenderer->render(
                $this->getChange(ConfFileStatus::Changed, \implode("\n", $expectedLines), \implode("\n", $currentLines)),
            ),
            static fn(string $diffLine): bool => true === \str_starts_with($diffLine, '@@'),
        ));
    }
}
