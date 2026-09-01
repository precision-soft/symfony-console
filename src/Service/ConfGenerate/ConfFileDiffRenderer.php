<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service\ConfGenerate;

use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileStatus;

class ConfFileDiffRenderer
{
    public const NO_FILE = '/dev/null';

    protected const CONTEXT_LINES = 3;

    /* the longest common subsequence table is quadratic, so a pathologically large file falls back to a whole replacement */
    protected const MAX_COMPARED_LINES = 2000;

    /** @return array<int, string> */
    public function render(ConfFileChangeDto $confFileChangeDto): array
    {
        return match ($confFileChangeDto->getStatus()) {
            ConfFileStatus::Unchanged => [],
            ConfFileStatus::Added => $this->renderWholeFile(
                static::NO_FILE,
                $confFileChangeDto->getPath(),
                [],
                $this->splitLines($confFileChangeDto->getExpectedContent()),
            ),
            ConfFileStatus::Removed => $this->renderWholeFile(
                $confFileChangeDto->getPath(),
                static::NO_FILE,
                $this->splitLines($confFileChangeDto->getCurrentContent()),
                [],
            ),
            ConfFileStatus::Changed => $this->renderChanged($confFileChangeDto),
        };
    }

    /** @return array<int, string> */
    protected function renderChanged(ConfFileChangeDto $confFileChangeDto): array
    {
        $currentLines = $this->splitLines($confFileChangeDto->getCurrentContent());
        $expectedLines = $this->splitLines($confFileChangeDto->getExpectedContent());

        if (static::MAX_COMPARED_LINES < \count($currentLines) + \count($expectedLines)) {
            return $this->renderWholeFile(
                $confFileChangeDto->getPath(),
                $confFileChangeDto->getPath(),
                $currentLines,
                $expectedLines,
            );
        }

        $hunks = $this->buildHunks($this->buildOperations($currentLines, $expectedLines));

        if ([] === $hunks) {
            return [];
        }

        return [
            '--- ' . $confFileChangeDto->getPath(),
            '+++ ' . $confFileChangeDto->getPath(),
            ...$hunks,
        ];
    }

    /**
     * @param array<int, string> $currentLines
     * @param array<int, string> $expectedLines
     * @return array<int, string>
     */
    protected function renderWholeFile(string $currentPath, string $expectedPath, array $currentLines, array $expectedLines): array
    {
        $lines = ['--- ' . $currentPath, '+++ ' . $expectedPath];
        $lines[] = \sprintf(
            '@@ -%s,%s +%s,%s @@',
            [] === $currentLines ? 0 : 1,
            \count($currentLines),
            [] === $expectedLines ? 0 : 1,
            \count($expectedLines),
        );

        foreach ($currentLines as $currentLine) {
            $lines[] = '-' . $currentLine;
        }

        foreach ($expectedLines as $expectedLine) {
            $lines[] = '+' . $expectedLine;
        }

        return $lines;
    }

    /**
     * @param array<int, string> $currentLines
     * @param array<int, string> $expectedLines
     * @return array<int, array{marker: string, line: string, currentNumber: int, expectedNumber: int}>
     */
    protected function buildOperations(array $currentLines, array $expectedLines): array
    {
        $currentCount = \count($currentLines);
        $expectedCount = \count($expectedLines);
        $commonLengths = $this->buildCommonLengths($currentLines, $expectedLines);

        $operations = [];
        $currentIndex = 0;
        $expectedIndex = 0;

        while ($currentIndex < $currentCount || $expectedIndex < $expectedCount) {
            $currentNumber = $currentIndex + 1;
            $expectedNumber = $expectedIndex + 1;

            if (
                $currentIndex < $currentCount
                && $expectedIndex < $expectedCount
                && $currentLines[$currentIndex] === $expectedLines[$expectedIndex]
            ) {
                $operations[] = ['marker' => ' ', 'line' => $currentLines[$currentIndex], 'currentNumber' => $currentNumber, 'expectedNumber' => $expectedNumber];
                ++$currentIndex;
                ++$expectedIndex;

                continue;
            }

            /* a tie prefers the removal, so a replaced line reads as `-` then `+` like every other unified diff */
            if (
                $currentIndex < $currentCount
                && (
                    $expectedIndex === $expectedCount
                    || $commonLengths[$currentIndex + 1][$expectedIndex] >= $commonLengths[$currentIndex][$expectedIndex + 1]
                )
            ) {
                $operations[] = ['marker' => '-', 'line' => $currentLines[$currentIndex], 'currentNumber' => $currentNumber, 'expectedNumber' => $expectedNumber];
                ++$currentIndex;

                continue;
            }

            $operations[] = ['marker' => '+', 'line' => $expectedLines[$expectedIndex], 'currentNumber' => $currentNumber, 'expectedNumber' => $expectedNumber];
            ++$expectedIndex;
        }

        return $operations;
    }

    /**
     * `$commonLengths[$i][$j]` is the longest common subsequence of the two tails, so the walk above stays forward
     *
     * @param array<int, string> $currentLines
     * @param array<int, string> $expectedLines
     * @return array<int, array<int, int>>
     */
    protected function buildCommonLengths(array $currentLines, array $expectedLines): array
    {
        $currentCount = \count($currentLines);
        $expectedCount = \count($expectedLines);

        $commonLengths = \array_fill(0, $currentCount + 1, \array_fill(0, $expectedCount + 1, 0));

        for ($currentIndex = $currentCount - 1; 0 <= $currentIndex; --$currentIndex) {
            for ($expectedIndex = $expectedCount - 1; 0 <= $expectedIndex; --$expectedIndex) {
                $commonLengths[$currentIndex][$expectedIndex] = $currentLines[$currentIndex] === $expectedLines[$expectedIndex]
                    ? $commonLengths[$currentIndex + 1][$expectedIndex + 1] + 1
                    : \max($commonLengths[$currentIndex + 1][$expectedIndex], $commonLengths[$currentIndex][$expectedIndex + 1]);
            }
        }

        return $commonLengths;
    }

    /**
     * @param array<int, array{marker: string, line: string, currentNumber: int, expectedNumber: int}> $operations
     * @return array<int, string>
     */
    protected function buildHunks(array $operations): array
    {
        $changedIndexes = [];

        foreach ($operations as $operationIndex => $operation) {
            if (' ' !== $operation['marker']) {
                $changedIndexes[] = $operationIndex;
            }
        }

        if ([] === $changedIndexes) {
            return [];
        }

        $lines = [];
        $operationCount = \count($operations);
        $hunkStart = (int)\max(0, $changedIndexes[0] - static::CONTEXT_LINES);
        $hunkEnd = (int)\min($operationCount - 1, $changedIndexes[0] + static::CONTEXT_LINES);

        foreach (\array_slice($changedIndexes, 1) as $changedIndex) {
            if ($changedIndex - static::CONTEXT_LINES <= $hunkEnd + 1) {
                $hunkEnd = (int)\min($operationCount - 1, $changedIndex + static::CONTEXT_LINES);

                continue;
            }

            $lines = [...$lines, ...$this->renderHunk($operations, $hunkStart, $hunkEnd)];
            $hunkStart = (int)\max(0, $changedIndex - static::CONTEXT_LINES);
            $hunkEnd = (int)\min($operationCount - 1, $changedIndex + static::CONTEXT_LINES);
        }

        return [...$lines, ...$this->renderHunk($operations, $hunkStart, $hunkEnd)];
    }

    /**
     * @param array<int, array{marker: string, line: string, currentNumber: int, expectedNumber: int}> $operations
     * @return array<int, string>
     */
    protected function renderHunk(array $operations, int $hunkStart, int $hunkEnd): array
    {
        $hunkOperations = \array_slice($operations, $hunkStart, $hunkEnd - $hunkStart + 1);

        $currentCount = 0;
        $expectedCount = 0;
        $currentStart = 0;
        $expectedStart = 0;
        $lines = [];

        foreach ($hunkOperations as $hunkOperation) {
            if ('+' !== $hunkOperation['marker']) {
                ++$currentCount;
                $currentStart = 0 === $currentStart ? $hunkOperation['currentNumber'] : $currentStart;
            }

            if ('-' !== $hunkOperation['marker']) {
                ++$expectedCount;
                $expectedStart = 0 === $expectedStart ? $hunkOperation['expectedNumber'] : $expectedStart;
            }

            $lines[] = $hunkOperation['marker'] . $hunkOperation['line'];
        }

        return [
            \sprintf(
                '@@ -%s,%s +%s,%s @@',
                0 === $currentCount ? 0 : $currentStart,
                $currentCount,
                0 === $expectedCount ? 0 : $expectedStart,
                $expectedCount,
            ),
            ...$lines,
        ];
    }

    /** @return array<int, string> */
    protected function splitLines(?string $content): array
    {
        if (null === $content || '' === $content) {
            return [];
        }

        $lines = \preg_split('/\R/', $content);

        return false === $lines ? [] : $lines;
    }
}
