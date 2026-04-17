<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class CrontabTemplate implements TemplateInterface
{
    /**
     * @param CommandDto[] $commands
     *
     * @throws InvalidConfigurationException
     * @throws InvalidValueException
     */
    public function generate(
        ConfigInterface $configInterface,
        array $commands,
    ): ConfFilesDto {
        if (false === ($configInterface instanceof ConfigDto)) {
            throw new InvalidConfigurationException(
                \sprintf('expected %s, got %s', ConfigDto::class, $configInterface::class),
            );
        }

        $cronjobs = [];

        $defaultDestinationFile = $configInterface->getSettings()->getDestinationFile();

        $heartbeat = null;

        foreach ($commands as $commandKey => $commandDto) {
            if (Configuration::HEARTBEAT === $commandKey) {
                $heartbeat = $commandDto;
                continue;
            }

            $destinationFile = $commandDto->getDestinationFile() ?? $defaultDestinationFile;
            $cronjobs[$destinationFile] ??= [];

            $cronjobs[$destinationFile][] = $this->buildCommand($commandDto, $configInterface);
        }

        if (0 === \count($cronjobs) && true === $configInterface->getSettings()->getHeartbeat()) {
            $cronjobs[$defaultDestinationFile] = [];
        }

        $confFilesDto = new ConfFilesDto();

        foreach ($cronjobs as $destinationFile => $cronjobCommands) {
            if (true === $configInterface->getSettings()->getHeartbeat()) {
                $cronjobCommands[] = $this->buildCommand(
                    $heartbeat ?? $this->getHeartbeatCommand($configInterface, $destinationFile),
                    $configInterface,
                );
            }

            $content = \str_replace(
                [
                    '%commands%',
                ],
                [
                    \implode(\PHP_EOL . \PHP_EOL, $cronjobCommands),
                ],
                $this->getTemplate(),
            );

            $content .= \PHP_EOL;

            $crontabPath = \rtrim($configInterface->getConfFilesDir(), '/') . '/' . $destinationFile;

            $confFilesDto->addFile($crontabPath, $content);
        }

        return $confFilesDto;
    }

    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): string {
        $commandParts = [
            $commandDto->getSchedule()->toCronExpression(),
        ];

        $user = $commandDto->getUser() ?? $configDto->getSettings()->getUser();
        if (null !== $user) {
            $commandParts[] = $user;
        }

        $commandParts = \array_merge($commandParts, $commandDto->getCommand());

        $logPart = $this->buildLog($commandDto, $configDto);
        if (null !== $logPart) {
            $commandParts[] = $logPart;
        }

        return \implode(' ', $commandParts);
    }

    protected function buildLog(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): ?string {
        $logEnabled = $commandDto->getSettings()->getLog() ?? $configDto->getSettings()->getLog();

        if (true !== $logEnabled) {
            return null;
        }

        $logFileName = $commandDto->getLogFileName() ?? \sprintf('%s.log', $commandDto->getName());

        return \sprintf('>> %s 2>&1', \escapeshellarg(\sprintf('%s/%s', \rtrim($configDto->getLogsDir(), '/'), $logFileName)));
    }

    protected function getHeartbeatCommand(
        ConfigDto $configDto,
        string $destinationFile,
    ): CommandDto {
        return new CommandDto(
            Configuration::HEARTBEAT,
            [
                Configuration::COMMAND => ['/bin/touch', \rtrim($configDto->getLogsDir(), '/') . '/heartbeat.' . \basename($destinationFile)],
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '*',
                    Configuration::HOUR => '*',
                    Configuration::DAY_OF_MONTH => '*',
                    Configuration::MONTH => '*',
                    Configuration::DAY_OF_WEEK => '*',
                ],
                Configuration::SETTINGS => [
                    Configuration::LOG => false,
                ],
            ],
        );
    }

    protected function getTemplate(): string
    {
        return '#############################################################################
#
# GENERATED FILE
# DO NOT EDIT LOCALLY
#
#############################################################################
# Example of job definition:
# .---------------- minute (0 - 59)
# |  .------------- hour (0 - 23)
# |  |  .---------- day of month (1 - 31)
# |  |  |  .------- month (1 - 12) OR jan,feb,mar,apr ...
# |  |  |  |  .---- day of week (0 - 6) (Sunday=0 or 7) OR sun,mon,tue,wed,thu,fri,sat
# |  |  |  |  |
# *  *  *  *  * user-name command to be executed
#############################################################################
%commands%
#############################################################################';
    }
}
