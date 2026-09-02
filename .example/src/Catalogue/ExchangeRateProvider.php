<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Catalogue;

use PrecisionSoft\Symfony\Console\Example\Exception\UnknownCurrencyException;

class ExchangeRateProvider
{
    protected const RATES_TO_EURO = ['EUR' => 1.0, 'USD' => 0.92, 'RON' => 0.2];

    /** @throws UnknownCurrencyException */
    public function getRateToEuro(string $currency): float
    {
        if (false === \array_key_exists($currency, static::RATES_TO_EURO)) {
            throw (new UnknownCurrencyException(\sprintf('unknown currency `%s`', $currency)))
                ->setContext(['currency' => $currency]);
        }

        return static::RATES_TO_EURO[$currency];
    }
}
