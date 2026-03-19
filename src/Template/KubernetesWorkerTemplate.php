<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;

class KubernetesWorkerTemplate implements TemplateInterface
{
    use KubernetesJobTrait;
    use WorkerNumberOfProcessesTrait;

    /**
     * @param ConfigDto $configDto
     * @param CommandDto[] $commands
     */
    public function generate(
        ConfigInterface $configDto,
        array $commands,
    ): ConfFilesDto {
        $workers = [];
        $index = 0;

        foreach ($commands as $commandDto) {
            $workers['"' . ($index++) . '"'] = $this->buildCommand($commandDto, $configDto);
        }

        $content = $this->convertArrayToString(
            [
                'Jobs' => [
                    'workers' => $workers,
                ],
            ],
        );

        /* crontab files need to end with an empty line */
        $content .= \PHP_EOL;

        $crontabPath = $configDto->getConfFilesDir() . '/' . $configDto->getSettings()->getDestinationFile();

        $confFilesDto = new ConfFilesDto();

        if (\count($workers) > 0) {
            $confFilesDto->addFile($crontabPath, $content);
        }

        return $confFilesDto;
    }

    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): array {
        $name = $this->sanitize($commandDto->getName());

        return [
            'name' => $name,
            'command' => '"' . \implode(' ', $commandDto->getCommand()) . '"',
            'parallelism' => $this->getNumberOfProcesses($configDto, $commandDto),
        ];
    }
}
