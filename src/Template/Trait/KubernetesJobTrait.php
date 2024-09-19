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

        foreach ($array as $key => $value) {
            if (true === \is_array($value)) {
                $command[] = \sprintf('%s%s:', $baseIndent, $key);
                $command[] = $this->convertArrayToString($value, $baseIndentLevel + 1, $indentSize);
                continue;
            }

            $command[] = \sprintf('%s%s: %s', $baseIndent, $key, $value);
        }

        return \implode(\PHP_EOL, $command);
    }

    private function sanitize(string $string): string
    {
        return \preg_replace('/[^a-z0-9\\-]+/i', '-', $string);
    }

    private function getIndent(int $level = 1, int $size = 4): string
    {
        return \str_repeat(' ', $level * $size);
    }
}
