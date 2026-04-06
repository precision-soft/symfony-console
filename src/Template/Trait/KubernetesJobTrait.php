<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

trait KubernetesJobTrait
{
    /**
     * @param array<string, mixed> $array
     */
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

            $command[] = \sprintf('%s%s: %s', $baseIndent, $entryKey, true === \is_string($entryValue) ? $this->escapeYamlValue($entryValue) : (string)$entryValue);
        }

        return \implode(\PHP_EOL, $command);
    }

    private function sanitize(string $input): string
    {
        $sanitizedInput = \preg_replace('/[^a-z0-9\\-]+/i', '-', $input);

        if (null === $sanitizedInput) {
            throw new InvalidValueException(\sprintf('failed to sanitize input `%s`', $input));
        }

        return \trim($sanitizedInput, '-');
    }

    private function escapeYamlValue(string $value): string
    {
        $yamlReservedWords = ['true', 'false', 'yes', 'no', 'on', 'off', 'null', '~'];

        if (
            1 === \preg_match('/[:#{}\\[\\],&*?|<>=!%@\\\\\'"\\n\\r\\t-]/', $value)
            || true === \in_array(\strtolower($value), $yamlReservedWords, true)
            || true === \is_numeric($value)
            || '' === $value
        ) {
            return \sprintf('"%s"', \str_replace(
                ['\\', '"', "\n", "\r", "\t"],
                ['\\\\', '\\"', '\\n', '\\r', '\\t'],
                $value,
            ));
        }

        return $value;
    }

    private function getIndent(int $level = 1, int $size = 4): string
    {
        return \str_repeat(' ', $level * $size);
    }
}
