<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template\Trait;

use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use ReflectionMethod;
use stdClass;

/**
 * @internal
 */
final class KubernetesJobTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(stdClass::class);
    }

    public function testConvertArrayToStringFlat(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        $convertedString = $this->callMethod($kubernetesJobTraitObject, 'convertArrayToString', [
            ['key1' => 'value1', 'key2' => 'value2'],
        ]);

        static::assertStringContainsString('key1: value1', $convertedString);
        static::assertStringContainsString('key2: value2', $convertedString);
    }

    public function testConvertArrayToStringNested(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        $convertedString = $this->callMethod($kubernetesJobTraitObject, 'convertArrayToString', [
            ['parent' => ['child' => 'value']],
        ]);

        static::assertStringContainsString('parent:', $convertedString);
        static::assertStringContainsString('    child: value', $convertedString);
    }

    public function testConvertArrayToStringWithCustomIndent(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        $convertedString = $this->callMethod($kubernetesJobTraitObject, 'convertArrayToString', [
            ['key' => 'value'],
            1,
            2,
        ]);

        static::assertStringContainsString('  key: value', $convertedString);
    }

    public function testSanitizeReplacesSpecialCharacters(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        static::assertSame('app-test-command', $this->callMethod($kubernetesJobTraitObject, 'sanitize', ['app:test:command']));
        static::assertSame('simple', $this->callMethod($kubernetesJobTraitObject, 'sanitize', ['simple']));
        static::assertSame('with-spaces', $this->callMethod($kubernetesJobTraitObject, 'sanitize', ['with spaces']));
        static::assertSame('test-123', $this->callMethod($kubernetesJobTraitObject, 'sanitize', ['test_123']));
    }

    public function testSanitizePreservesAlphanumericAndDash(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        static::assertSame('already-valid-123', $this->callMethod($kubernetesJobTraitObject, 'sanitize', ['already-valid-123']));
    }

    public function testEscapeYamlValueWithNewlines(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        $escapedValue = $this->callMethod($kubernetesJobTraitObject, 'escapeYamlValue', ["line1\nline2"]);

        static::assertSame('"line1\\nline2"', $escapedValue);
    }

    public function testEscapeYamlValueWithTab(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        $escapedValue = $this->callMethod($kubernetesJobTraitObject, 'escapeYamlValue', ["value\twith\ttabs"]);

        static::assertSame('"value\\twith\\ttabs"', $escapedValue);
    }

    public function testGetIndent(): void
    {
        $kubernetesJobTraitObject = $this->createTraitObject();

        static::assertSame('    ', $this->callMethod($kubernetesJobTraitObject, 'getIndent', [1, 4]));
        static::assertSame('        ', $this->callMethod($kubernetesJobTraitObject, 'getIndent', [2, 4]));
        static::assertSame('', $this->callMethod($kubernetesJobTraitObject, 'getIndent', [0, 4]));
        static::assertSame('  ', $this->callMethod($kubernetesJobTraitObject, 'getIndent', [1, 2]));
    }

    private function createTraitObject(): object
    {
        return new class {
            use KubernetesJobTrait;
        };
    }

    private function callMethod(object $kubernetesJobTraitObject, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($kubernetesJobTraitObject, $method);

        return $reflection->invokeArgs($kubernetesJobTraitObject, $args);
    }
}
