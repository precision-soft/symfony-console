<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command\Trait;

use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Input\InputOption;

trait InstancesTrait
{
    protected const MAX_INSTANCES = 'max-instances';
    protected const INSTANCE_INDEX = 'instance-index';

    /**
     * @return array{int, int}
     *
     * @throws InvalidConfigurationException
     */
    protected function computeInstances(): array
    {
        $maxInstancesOption = $this->input->getOption(self::MAX_INSTANCES);
        $instanceIndexOption = $this->input->getOption(self::INSTANCE_INDEX);

        if (null === $maxInstancesOption || null === $instanceIndexOption || '' === $maxInstancesOption || '' === $instanceIndexOption) {
            throw new InvalidConfigurationException('max-instances and instance-index options are required');
        }

        if (false === \is_numeric($maxInstancesOption) || false === \is_numeric($instanceIndexOption)) {
            throw new InvalidConfigurationException('max-instances and instance-index options must be numeric');
        }

        $maxInstances = (int)$maxInstancesOption;
        $instanceIndex = (int)$instanceIndexOption;

        if (1 > $maxInstances || 1 > $instanceIndex || $maxInstances < $instanceIndex) {
            throw new InvalidConfigurationException('invalid instances and instance index provided');
        }

        return [$maxInstances, $instanceIndex];
    }

    protected function configureInstances(): void
    {
        $this->addOption(self::MAX_INSTANCES, null, InputOption::VALUE_OPTIONAL, 'the number of instances of this command', 1)
            ->addOption(self::INSTANCE_INDEX, null, InputOption::VALUE_OPTIONAL, 'the index of the current command instance up to the max instances', 1);
    }

    /** @throws InvalidConfigurationException */
    protected function formatMessageWithInstances(string $message): string
    {
        [$maxInstances, $instanceIndex] = $this->computeInstances();

        return \sprintf('[%s/%s] %s', $instanceIndex, $maxInstances, $message);
    }
}
