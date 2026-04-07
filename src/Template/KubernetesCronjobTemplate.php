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
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;

class KubernetesCronjobTemplate implements TemplateInterface
{
    use KubernetesJobTrait;

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

        $destinationFile = $configInterface->getSettings()->getDestinationFile();

        if ('' === $destinationFile) {
            throw new InvalidConfigurationException('the `destination file` is mandatory for kubernetes cronjob template');
        }

        $cronjobs = [];

        foreach ($commands as $commandDto) {
            $cronjobs[] = $this->buildCommand($commandDto, $configInterface);
        }

        $content = $this->convertArrayToString(
            [
                'CronJobs' => [
                    'jobs' => $cronjobs,
                ],
            ],
        );

        $content .= \PHP_EOL;

        $crontabPath = $configInterface->getConfFilesDir() . '/' . $destinationFile;

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
            'schedule' => '"' . $commandDto->getSchedule()->toCronExpression() . '"',
        ];
    }

}
