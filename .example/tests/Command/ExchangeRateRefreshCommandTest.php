<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Test\Command;

use PrecisionSoft\Symfony\Console\Example\Catalogue\ExchangeRateProvider;
use PrecisionSoft\Symfony\Console\Example\Catalogue\ProductRepository;
use PrecisionSoft\Symfony\Console\Example\Command\ExchangeRateRefreshCommand;
use PrecisionSoft\Symfony\Console\Example\Exception\UnknownCurrencyException;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** @internal */
final class ExchangeRateRefreshCommandTest extends AbstractTestCase
{
    public static function getMockDto(): MockDto
    {
        return new MockDto(ProductRepository::class);
    }

    public function testEveryCurrencyOfTheCatalogueIsRefreshedWhenNoneIsGiven(): void
    {
        $commandTester = $this->getCommandTester();

        $this->get(ProductRepository::class)
            ->shouldReceive('findCurrencies')
            ->once()
            ->andReturn(['EUR', 'USD']);
        $this->get(ExchangeRateProvider::class)
            ->shouldReceive('getRateToEuro')
            ->with('EUR')
            ->andReturn(1.0);
        $this->get(ExchangeRateProvider::class)
            ->shouldReceive('getRateToEuro')
            ->with('USD')
            ->andReturn(0.92);

        $commandTester->execute([]);
        $display = $commandTester->getDisplay();

        static::assertSame(ExchangeRateRefreshCommand::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('USD to EUR: 0.9200', $display);
        static::assertStringContainsString('refreshed `2` currencies', $display);
    }

    /* the project's exception carries its context, and the console's error line prints it after the exception chain */
    public function testAnUnknownCurrencyFailsTheRefreshWithItsContext(): void
    {
        $commandTester = $this->getCommandTester();

        $this->get(ExchangeRateProvider::class)
            ->shouldReceive('getRateToEuro')
            ->with('XYZ')
            ->andThrow((new UnknownCurrencyException('unknown currency `XYZ`'))->setContext(['currency' => 'XYZ']));

        $commandTester->execute(['currencies' => ['XYZ']]);
        $display = (string)\preg_replace('/\s+/', '', $commandTester->getDisplay());

        static::assertSame(ExchangeRateRefreshCommand::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('unknowncurrency`XYZ`', $display);
        static::assertStringContainsString('{"currency":"XYZ"}', $display);
    }

    /* a Symfony command cannot be a partial mock — its constructor already calls methods on the mock — so the command is
       built by hand on the repository from `getMockDto()` and the provider registered at run time */
    private function getCommandTester(): CommandTester
    {
        $this->registerMockDto(new MockDto(ExchangeRateProvider::class));

        return new CommandTester(new ExchangeRateRefreshCommand(
            $this->get(ProductRepository::class),
            $this->get(ExchangeRateProvider::class),
        ));
    }
}
