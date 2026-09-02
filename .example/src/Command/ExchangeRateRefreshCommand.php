<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Command;

use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\TimeLimitTrait;
use PrecisionSoft\Symfony\Console\Example\Catalogue\ExchangeRateProvider;
use PrecisionSoft\Symfony\Console\Example\Catalogue\ProductRepository;
use PrecisionSoft\Symfony\Console\Example\Exception\UnknownCurrencyException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/* a cron job: refreshes the euro rate of every currency the catalogue prices in, within its time limit */
class ExchangeRateRefreshCommand extends AbstractCommand
{
    use TimeLimitTrait;

    public const NAME = 'catalogue:exchange-rate-refresh';

    protected const CURRENCIES = 'currencies';

    public function __construct(
        protected readonly ProductRepository $productRepository,
        protected readonly ExchangeRateProvider $exchangeRateProvider,
    ) {
        parent::__construct(static::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('refreshes the euro exchange rate of every currency the catalogue prices in');
        $this->addArgument(
            static::CURRENCIES,
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'the currencies to refresh; every currency of the catalogue when omitted',
        );
        $this->configureTimeLimit(60);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->initializeTimeLimit();
        } catch (InvalidValueException $invalidValueException) {
            $this->error($invalidValueException->getMessage(), $invalidValueException);

            return static::FAILURE;
        }

        /** @var array<int, string> $currencies */
        $currencies = $input->getArgument(static::CURRENCIES);

        if ([] === $currencies) {
            $currencies = $this->productRepository->findCurrencies();
        }

        try {
            foreach ($currencies as $currency) {
                if (true === $this->getTimeLimitReached()) {
                    return static::SUCCESS;
                }

                $this->info(\sprintf('%s to EUR: %.4f', $currency, $this->exchangeRateProvider->getRateToEuro($currency)));
            }
        } catch (UnknownCurrencyException $unknownCurrencyException) {
            $this->error($unknownCurrencyException->getMessage(), $unknownCurrencyException);

            return static::FAILURE;
        }

        $this->success(\sprintf('refreshed `%d` currencies', \count($currencies)));

        return static::SUCCESS;
    }
}
