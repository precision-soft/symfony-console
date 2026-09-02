<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Catalogue;

readonly class Product
{
    public function __construct(
        protected string $name,
        protected int $priceInCents,
        protected string $currency,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriceInCents(): int
    {
        return $this->priceInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
