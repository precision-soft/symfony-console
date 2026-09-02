<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Command;

use PrecisionSoft\Symfony\Console\Example\Catalogue\Product;
use PrecisionSoft\Symfony\Console\Example\Catalogue\ProductRepository;
use PrecisionSoft\Symfony\Console\Example\Command\PriceListImportCommand;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** @internal */
final class PriceListImportCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(ProductRepository::class);
    }

    public function testEachInstanceImportsItsOwnShardOfTheCatalogue(): void
    {
        $commandTester = $this->runImport(['--max-instances' => '2', '--instance-index' => '2']);
        $display = $commandTester->getDisplay();

        static::assertSame(PriceListImportCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('[2/2] imported `Coffee Grinder` at 89.00 EUR', $display);
        static::assertStringContainsString('[2/2] imported `Filter Papers` at 5.00 RON', $display);
        static::assertStringNotContainsString('Espresso Machine', $display);
        static::assertStringContainsString('imported `2` products', $display);
    }

    public function testTheMemoryLimitStopsTheImportBeforeTheNextProduct(): void
    {
        $commandTester = $this->runImport(['--memory-limit' => '1M']);
        /* the warning block wraps long lines, so the assertion reads it with every whitespace run collapsed */
        $display = (string)\preg_replace('/\s+/', ' ', $commandTester->getDisplay());

        static::assertSame(PriceListImportCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('max allowed memory usage reached', $display);
        static::assertStringContainsString('memory or time limit exceeded, `0` products imported before stopping', $display);
    }

    public function testAnInstanceIndexAboveTheInstanceCountIsRejected(): void
    {
        $commandTester = $this->runImport(['--max-instances' => '2', '--instance-index' => '3']);

        static::assertSame(PriceListImportCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('invalid instances and instance index provided', $commandTester->getDisplay());
    }

    public function testANonPositiveTimeLimitIsRejected(): void
    {
        $commandTester = $this->runImport(['--time-limit' => '0']);

        static::assertSame(PriceListImportCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('must be a positive integer', $commandTester->getDisplay());
    }

    /** @param array<string, string> $input */
    private function runImport(array $input): CommandTester
    {
        $this->get(ProductRepository::class)
            ->shouldReceive('findAll')
            ->andReturn([
                new Product('Espresso Machine', 24900, 'EUR'),
                new Product('Coffee Grinder', 8900, 'EUR'),
                new Product('Milk Frother', 3900, 'USD'),
                new Product('Filter Papers', 500, 'RON'),
            ]);

        $commandTester = new CommandTester(new PriceListImportCommand($this->get(ProductRepository::class)));
        $commandTester->execute($input);

        return $commandTester;
    }
}
