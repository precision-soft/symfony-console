<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template\Trait;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;

/**
 * @internal
 */
final class KubernetesJobTraitTest extends TestCase
{
    public function testConvertArrayToStringFlat(): void
    {
        $object = $this->createTraitObject();

        $result = $this->callMethod($object, 'convertArrayToString', [
            ['key1' => 'value1', 'key2' => 'value2'],
        ]);

        static::assertStringContainsString('key1: value1', $result);
        static::assertStringContainsString('key2: value2', $result);
    }

    public function testConvertArrayToStringNested(): void
    {
        $object = $this->createTraitObject();

        $result = $this->callMethod($object, 'convertArrayToString', [
            ['parent' => ['child' => 'value']],
        ]);

        static::assertStringContainsString('parent:', $result);
        static::assertStringContainsString('    child: value', $result);
    }

    public function testConvertArrayToStringWithCustomIndent(): void
    {
        $object = $this->createTraitObject();

        $result = $this->callMethod($object, 'convertArrayToString', [
            ['key' => 'value'],
            1,
            2,
        ]);

        static::assertStringContainsString('  key: value', $result);
    }

    public function testSanitizeReplacesSpecialCharacters(): void
    {
        $object = $this->createTraitObject();

        static::assertSame('app-test-command', $this->callMethod($object, 'sanitize', ['app:test:command']));
        static::assertSame('simple', $this->callMethod($object, 'sanitize', ['simple']));
        static::assertSame('with-spaces', $this->callMethod($object, 'sanitize', ['with spaces']));
        static::assertSame('test-123', $this->callMethod($object, 'sanitize', ['test_123']));
    }

    public function testSanitizePreservesAlphanumericAndDash(): void
    {
        $object = $this->createTraitObject();

        static::assertSame('already-valid-123', $this->callMethod($object, 'sanitize', ['already-valid-123']));
    }

    public function testGetIndent(): void
    {
        $object = $this->createTraitObject();

        static::assertSame('    ', $this->callMethod($object, 'getIndent', [1, 4]));
        static::assertSame('        ', $this->callMethod($object, 'getIndent', [2, 4]));
        static::assertSame('', $this->callMethod($object, 'getIndent', [0, 4]));
        static::assertSame('  ', $this->callMethod($object, 'getIndent', [1, 2]));
    }

    private function createTraitObject(): object
    {
        return new class {
            use KubernetesJobTrait;
        };
    }

    private function callMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
