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
use PrecisionSoft\Symfony\Console\Template\Trait\DestinationPathTrait;

class CrontabTemplate implements TemplateInterface
{
    use DestinationPathTrait;

    public const DESTINATION_FILE_PLACEHOLDER = '{destination_file}';

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

        $defaultDestinationFile = $this->resolveDestinationFile($configInterface->getSettings()->getDestinationFile());
        $heartbeatEnabled = true === $configInterface->getSettings()->getHeartbeat();

        $heartbeat = null;

        foreach ($commands as $commandKey => $commandDto) {
            if (Configuration::HEARTBEAT === $commandKey) {
                $heartbeat = $commandDto;
                continue;
            }

            $destinationFile = $this->resolveDestinationFile($commandDto->getDestinationFile() ?? $defaultDestinationFile);
            $cronjobs[$destinationFile] ??= [];

            $cronjobs[$destinationFile][] = $this->buildCommand($commandDto, $configInterface);
        }

        if (0 === \count($cronjobs) && true === $heartbeatEnabled) {
            $cronjobs[$defaultDestinationFile] = [];
        }

        foreach ($configInterface->getSettings()->getDestinationFiles() as $declaredDestinationFile) {
            $cronjobs[$this->resolveDestinationFile($declaredDestinationFile)] ??= [];
        }

        $confFilesDto = new ConfFilesDto();

        foreach ($cronjobs as $cronjobDestinationFile => $cronjobCommands) {
            $destinationFile = (string)$cronjobDestinationFile;

            if (true === $heartbeatEnabled) {
                $destinationFileLabel = $this->buildDestinationPathLabel($destinationFile);

                $cronjobCommands[] = $this->buildCommand(
                    $this->resolveHeartbeat(
                        $heartbeat ?? $this->getHeartbeatCommand($configInterface, $destinationFileLabel),
                        $destinationFileLabel,
                    ),
                    $configInterface,
                );
            }

            $content = \str_replace(
                '%commands%',
                \implode(\PHP_EOL . \PHP_EOL, $cronjobCommands),
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

    /** @throws InvalidConfigurationException */
    protected function resolveDestinationFile(string $destinationFile): string
    {
        $normalizedDestinationFile = $this->normalizeDestinationPath($destinationFile);

        if ('' === $normalizedDestinationFile) {
            throw new InvalidConfigurationException(
                \sprintf('the `destination file` `%s` resolves to an empty path', $destinationFile),
            );
        }

        return $normalizedDestinationFile;
    }

    /**
     * @throws InvalidConfigurationException
     * @throws InvalidValueException
     */
    protected function resolveHeartbeat(
        CommandDto $commandDto,
        string $destinationFileLabel,
    ): CommandDto {
        $command = $commandDto->getCommand();
        $logFileName = $commandDto->getLogFileName();

        $resolvedCommand = \str_replace(static::DESTINATION_FILE_PLACEHOLDER, $destinationFileLabel, $command);
        $resolvedLogFileName = null === $logFileName
            ? null
            : \str_replace(static::DESTINATION_FILE_PLACEHOLDER, $destinationFileLabel, $logFileName);

        if ($resolvedCommand === $command && $resolvedLogFileName === $logFileName) {
            return $commandDto;
        }

        $scheduleDto = $commandDto->getSchedule();

        $parameters = [
            Configuration::COMMAND => $resolvedCommand,
            Configuration::LOG_FILE_NAME => $resolvedLogFileName,
            Configuration::USER => $commandDto->getUser(),
            Configuration::DESTINATION_FILE => $commandDto->getDestinationFile(),
            Configuration::SCHEDULE => [
                Configuration::MINUTE => $scheduleDto->getMinute(),
                Configuration::HOUR => $scheduleDto->getHour(),
                Configuration::DAY_OF_MONTH => $scheduleDto->getDayOfMonth(),
                Configuration::MONTH => $scheduleDto->getMonth(),
                Configuration::DAY_OF_WEEK => $scheduleDto->getDayOfWeek(),
            ],
            Configuration::SETTINGS => $commandDto->getSettings()->toArray(),
        ];

        return new CommandDto($commandDto->getName(), $parameters);
    }

    protected function getHeartbeatCommand(
        ConfigDto $configDto,
        string $destinationFileLabel,
    ): CommandDto {
        return new CommandDto(
            Configuration::HEARTBEAT,
            [
                Configuration::COMMAND => ['/bin/touch', \rtrim($configDto->getLogsDir(), '/') . '/heartbeat.' . $destinationFileLabel],
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
