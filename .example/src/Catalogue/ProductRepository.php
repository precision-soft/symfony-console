<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Catalogue;

class ProductRepository
{
    /** @return array<int, Product> */
    public function findAll(): array
    {
        return [
            new Product('Espresso Machine', 24900, 'EUR'),
            new Product('Coffee Grinder', 8900, 'EUR'),
            new Product('Milk Frother', 3900, 'USD'),
            new Product('Filter Papers', 500, 'RON'),
            new Product('Kettle', 4500, 'USD'),
            new Product('Scale', 2900, 'RON'),
        ];
    }

    /** @return array<int, string> */
    public function findCurrencies(): array
    {
        return \array_values(\array_unique(\array_map(
            static fn(Product $product): string => $product->getCurrency(),
            $this->findAll(),
        )));
    }
}
