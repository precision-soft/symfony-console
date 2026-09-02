<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Example\Command;

use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\InstancesTrait;
use PrecisionSoft\Symfony\Console\Command\Trait\MemoryAndTimeLimitsTrait;
use PrecisionSoft\Symfony\Console\Example\Catalogue\ProductRepository;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/* a worker: every instance imports its own shard of the catalogue and stops cleanly at the memory or time limit */

class PriceListImportCommand extends AbstractCommand
{
    use InstancesTrait;
    use MemoryAndTimeLimitsTrait;

    public const NAME = 'catalogue:price-list-import';

    public function __construct(protected readonly ProductRepository $productRepository)
    {
        parent::__construct(static::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('imports the price list, one shard of the catalogue per instance');
        $this->configureMemoryAndTimeLimits('256M', 300);
        $this->configureInstances();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->initializeMemoryAndTimeLimits();
            [$maxInstances, $instanceIndex] = $this->computeInstances();
        } catch (InvalidConfigurationException|InvalidValueException $exception) {
            $this->error($exception->getMessage(), $exception);

            return static::FAILURE;
        }

        $importedCount = 0;

        try {
            foreach ($this->productRepository->findAll() as $position => $product) {
                if ($instanceIndex - 1 !== $position % $maxInstances) {
                    continue;
                }

                $this->stopScriptIfLimitsReached();

                $this->writeln($this->formatMessageWithInstances(\sprintf(
                    'imported `%s` at %.2f %s',
                    $product->getName(),
                    $product->getPriceInCents() / 100,
                    $product->getCurrency(),
                )));

                ++$importedCount;
            }
        } catch (LimitExceededException $limitExceededException) {
            $this->warning(\sprintf('%s, `%d` products imported before stopping', $limitExceededException->getMessage(), $importedCount));

            return static::SUCCESS;
        }

        $this->success(\sprintf('imported `%d` products', $importedCount));

        return static::SUCCESS;
    }
}
