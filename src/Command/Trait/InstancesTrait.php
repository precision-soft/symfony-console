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
        $maxInstancesOption = true === $this->input->hasOption(static::MAX_INSTANCES) ? $this->input->getOption(static::MAX_INSTANCES) : null;
        $instanceIndexOption = true === $this->input->hasOption(static::INSTANCE_INDEX) ? $this->input->getOption(static::INSTANCE_INDEX) : null;

        if (null === $maxInstancesOption || null === $instanceIndexOption || '' === $maxInstancesOption || '' === $instanceIndexOption) {
            throw new InvalidConfigurationException('max-instances and instance-index options are required');
        }

        if (false === \is_numeric($maxInstancesOption) || false === \is_numeric($instanceIndexOption)) {
            throw new InvalidConfigurationException('max-instances and instance-index options must be numeric');
        }

        if ((string)(int)$maxInstancesOption !== (string)$maxInstancesOption || (string)(int)$instanceIndexOption !== (string)$instanceIndexOption) {
            throw new InvalidConfigurationException('max-instances and instance-index options must be integer values');
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
        $this->addOption(static::MAX_INSTANCES, null, InputOption::VALUE_OPTIONAL, 'the number of instances of this command', 1)
            ->addOption(static::INSTANCE_INDEX, null, InputOption::VALUE_OPTIONAL, 'the index of the current command instance up to the max instances', 1);
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function formatMessageWithInstances(string $message): string
    {
        [$maxInstances, $instanceIndex] = $this->computeInstances();

        return \sprintf('[%s/%s] %s', $instanceIndex, $maxInstances, $message);
    }
}
