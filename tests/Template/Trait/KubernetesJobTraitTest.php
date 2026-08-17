<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template\Trait;

use PrecisionSoft\Symfony\Console\Test\Utility\KubernetesJobTraitObject;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class KubernetesJobTraitTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(KubernetesJobTraitObject::class, [], true);
    }

    public function testConvertArrayToStringFlat(): void
    {
        $dumpedYaml = (new KubernetesJobTraitObject())->dump(['key1' => 'value1', 'key2' => 'value2']);

        static::assertSame(['key1' => 'value1', 'key2' => 'value2'], Yaml::parse($dumpedYaml));
    }

    public function testConvertArrayToStringNested(): void
    {
        $dumpedYaml = (new KubernetesJobTraitObject())->dump(['parent' => ['child' => 'value']]);

        static::assertSame(['parent' => ['child' => 'value']], Yaml::parse($dumpedYaml));
        static::assertStringContainsString('    child: value', $dumpedYaml);
    }

    public function testConvertArrayToStringEmitsAListAsAYamlSequence(): void
    {
        $dumpedYaml = (new KubernetesJobTraitObject())->dump([
            'collection' => [
                ['name' => 'first'],
                ['name' => 'second'],
            ],
        ]);

        $parsedYaml = Yaml::parse($dumpedYaml, Yaml::PARSE_OBJECT_FOR_MAP);

        static::assertInstanceOf(stdClass::class, $parsedYaml);
        static::assertIsArray($parsedYaml->collection);
        static::assertCount(2, $parsedYaml->collection);
    }

    public function testConvertArrayToStringQuotesValuesThatWouldOtherwiseChangeMeaning(): void
    {
        $values = [
            'schedule' => '*/5 * * * *',
            'command' => 'bin/console app:run --tag=#one',
            'reserved' => 'true',
            'numeric' => '42',
            'empty' => '',
            'multiline' => "first\nsecond",
        ];

        static::assertSame($values, Yaml::parse((new KubernetesJobTraitObject())->dump($values)));
    }

    public function testSanitizeReplacesSpecialCharacters(): void
    {
        $kubernetesJobTraitObject = new KubernetesJobTraitObject();

        static::assertSame('app-test-command', $kubernetesJobTraitObject->sanitizeInput('app:test:command'));
        static::assertSame('simple', $kubernetesJobTraitObject->sanitizeInput('simple'));
        static::assertSame('with-spaces', $kubernetesJobTraitObject->sanitizeInput('with spaces'));
        static::assertSame('test-123', $kubernetesJobTraitObject->sanitizeInput('test_123'));
    }

    public function testSanitizePreservesAlphanumericAndDash(): void
    {
        static::assertSame('already-valid-123', (new KubernetesJobTraitObject())->sanitizeInput('already-valid-123'));
    }
}
