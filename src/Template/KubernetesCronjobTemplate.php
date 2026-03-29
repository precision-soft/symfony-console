<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ScheduleDto;
use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;

class KubernetesCronjobTemplate implements TemplateInterface
{
    use KubernetesJobTrait;

    /**
     * @param ConfigDto $configInterface
     * @param CommandDto[] $commands
     */
    public function generate(
        ConfigInterface $configInterface,
        array $commands,
    ): ConfFilesDto {
        $cronjobs = [];
        $index = 0;

        foreach ($commands as $commandDto) {
            $cronjobs['"' . ($index++) . '"'] = $this->buildCommand($commandDto, $configInterface);
        }

        $content = $this->convertArrayToString(
            [
                'CronJobs' => [
                    'jobs' => $cronjobs,
                ],
            ],
        );

        $content .= \PHP_EOL;

        $crontabPath = $configInterface->getConfFilesDir() . '/' . $configInterface->getSettings()->getDestinationFile();

        $confFilesDto = new ConfFilesDto();

        if (0 < \count($cronjobs)) {
            $confFilesDto->addFile($crontabPath, $content);
        }

        return $confFilesDto;
    }

    /**
     * @return array<string, string>
     */
    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): array {
        $name = $this->sanitize($commandDto->getName());

        return [
            'name' => $name,
            'command' => \implode(' ', \array_map('\escapeshellarg', $commandDto->getCommand())),
            'schedule' => '"' . $this->buildSchedule($commandDto->getSchedule()) . '"',
        ];
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
}
