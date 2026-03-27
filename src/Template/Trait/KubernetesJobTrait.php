<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

trait KubernetesJobTrait
{
    private function convertArrayToString(
        array $array,
        int $baseIndentLevel = 0,
        int $indentSize = 4,
    ): string {
        $command = [];

        $baseIndent = $this->getIndent($baseIndentLevel, $indentSize);

        foreach ($array as $entryKey => $entryValue) {
            if (true === \is_array($entryValue)) {
                $command[] = \sprintf('%s%s:', $baseIndent, $entryKey);
                $command[] = $this->convertArrayToString($entryValue, $baseIndentLevel + 1, $indentSize);
                continue;
            }

            $command[] = \sprintf('%s%s: %s', $baseIndent, $entryKey, $entryValue);
        }

        return \implode(\PHP_EOL, $command);
    }

    private function sanitize(string $input): string
    {
        return (string)\preg_replace('/[^a-z0-9\\-]+/i', '-', $input);
    }

    private function getIndent(int $level = 1, int $size = 4): string
    {
        return \str_repeat(' ', $level * $size);
    }
}
