<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Exception\Exception;
use Symfony\Component\Console\Input\InputOption;

trait InstancesTrait
{
    protected const MAX_INSTANCES = 'max-instances';
    protected const INSTANCE_INDEX = 'instance-index';

    protected function computeInstances(): array
    {
        $maxInstances = (int)$this->input->getOption(self::MAX_INSTANCES);
        $instanceIndex = (int)$this->input->getOption(self::INSTANCE_INDEX);

        if (1 > $maxInstances || 1 > $instanceIndex || $instanceIndex > $maxInstances) {
            throw new Exception('invalid instances and instance index provided');
        }

        return [$maxInstances, $instanceIndex];
    }

    protected function configureInstances(): void
    {
        $this->addOption(self::MAX_INSTANCES, null, InputOption::VALUE_OPTIONAL, 'the number of instances of this command', 1)
            ->addOption(self::INSTANCE_INDEX, null, InputOption::VALUE_OPTIONAL, 'the index of the current command instance up to the max instances', 1);
    }

    protected function formatMessageWithInstances(string $message): string
    {
        [$maxInstances, $instanceIndex] = $this->computeInstances();

        return \sprintf('[%s/%s] %s', $instanceIndex, $maxInstances, $message);
    }
}
