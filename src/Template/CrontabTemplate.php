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
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ScheduleDto;

class CrontabTemplate implements TemplateInterface
{
    /**
     * @param ConfigDto $configDto
     * @param CommandDto[] $commands
     */
    public function generate(
        ConfigInterface $configDto,
        array $commands,
    ): ConfFilesDto {
        $cronjobs = [];

        $defaultDestinationFile = $configDto->getSettings()->getDestinationFile();

        $heartbeat = null;
        if (true === isset($commands[Configuration::HEARTBEAT])) {
            $heartbeat = $commands[Configuration::HEARTBEAT];
            unset($commands[Configuration::HEARTBEAT]);
        }

        foreach ($commands as $commandDto) {
            $destinationFile = $commandDto->getDestinationFile() ?? $defaultDestinationFile;
            $cronjobs[$destinationFile] ??= [];

            $cronjobs[$destinationFile][] = $this->buildCommand($commandDto, $configDto);
        }

        $confFilesDto = new ConfFilesDto();

        foreach ($cronjobs as $destinationFile => $commands) {
            if (null !== $heartbeat) {
                $commands[] = $this->buildCommand($heartbeat, $configDto);
            }

            $content = \str_replace(
                [
                    '%commands%',
                ],
                [
                    \implode(\PHP_EOL . \PHP_EOL, $commands),
                ],
                $this->getTemplate(),
            );

            /* crontab files need to end with an empty line */
            $content .= \PHP_EOL;

            $crontabPath = $configDto->getConfFilesDir() . '/' . $destinationFile;

            $confFilesDto->addFile($crontabPath, $content);
        }

        return $confFilesDto;
    }

    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): string {
        $commandParts = [
            $this->buildSchedule($commandDto->getSchedule()),
        ];

        $user = $configDto->getSettings()->getUser() ?? $commandDto->getUser();
        if (null !== $user) {
            $commandParts[] = $user;
        }

        $commandParts = array_merge($commandParts, $commandDto->getCommand());

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
        if (($commandDto->getSettings()->getLog() ?? $configDto->getSettings()->getLog()) !== true) {
            return null;
        }

        $logFileName = $commandDto->getLogFileName() ?? sprintf('%s.log', $commandDto->getName());

        return \sprintf('>> %s/%s 2>&1', $configDto->getLogsDir(), $logFileName);
    }

    protected function buildSchedule(ScheduleDto $schedule): string
    {
        return \implode(
            ' ',
            [
                $schedule->getMinute(),
                $schedule->getHour(),
                $schedule->getDayOfMonth(),
                $schedule->getMonth(),
                $schedule->getDayOfWeek(),
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
