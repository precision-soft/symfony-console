<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;

trait WorkerSettingsTrait
{
    /** @throws InvalidConfigurationException */
    protected function getUser(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $user = $commandDto->getSettings()->getUser() ?? $configDto->getSettings()->getUser();

        if (null === $user || '' === $user) {
            throw new InvalidConfigurationException('the `user` is mandatory');
        }

        return $user;
    }

    protected function getLogFile(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        return $commandDto->getSettings()->getLogFile()
            ?? $configDto->getSettings()->getLogFile()
            ?? \sprintf('%s/%s.log', \rtrim($configDto->getLogsDir(), '/'), $commandDto->getName());
    }
}
