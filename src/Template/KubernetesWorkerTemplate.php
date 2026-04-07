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
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Template\Trait\KubernetesJobTrait;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;

class KubernetesWorkerTemplate implements TemplateInterface
{
    use KubernetesJobTrait;
    use WorkerNumberOfProcessesTrait;

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

        if (null === $destinationFile || '' === $destinationFile) {
            throw new InvalidConfigurationException('the `destination file` is mandatory for kubernetes worker template');
        }

        $workers = [];

        foreach ($commands as $commandDto) {
            $workers[] = $this->buildCommand($commandDto, $configInterface);
        }

        $content = $this->convertArrayToString(
            [
                'Jobs' => [
                    'workers' => $workers,
                ],
            ],
        );

        $content .= \PHP_EOL;

        $workerConfigPath = $configInterface->getConfFilesDir() . '/' . $destinationFile;

        $confFilesDto = new ConfFilesDto();

        if (0 < \count($workers)) {
            $confFilesDto->addFile($workerConfigPath, $content);
        }

        return $confFilesDto;
    }

    /**
     * @return array<string, string|int>
     */
    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): array {
        $name = $this->sanitize($commandDto->getName());

        return [
            'name' => $name,
            'command' => '"' . \implode(' ', \array_map('\escapeshellarg', $commandDto->getCommand())) . '"',
            'parallelism' => $this->getNumberOfProcesses($configDto, $commandDto),
        ];
    }
}
