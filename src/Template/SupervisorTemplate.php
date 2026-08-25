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
use PrecisionSoft\Symfony\Console\Template\Trait\DestinationPathTrait;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;

class SupervisorTemplate implements TemplateInterface
{
    use DestinationPathTrait;
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

        $confFilesDto = new ConfFilesDto();

        foreach ($commands as $commandDto) {
            $worker = $this->buildCommand($commandDto, $configInterface);

            $confPath = $this->getPath($configInterface, $commandDto);

            $confFilesDto->addFile($confPath, $worker);
        }

        return $confFilesDto;
    }

    protected function getPath(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $destinationSubDir = $commandDto->getDestinationSubDir() ?? $configDto->getSettings()->getDestinationSubDir();
        $destinationSuffix = $commandDto->getDestinationSuffix() ?? $configDto->getSettings()->getDestinationSuffix();

        $pathParts = [\rtrim($configDto->getConfFilesDir(), '/')];

        $pathParts = \array_merge($pathParts, $this->splitDestinationPath($destinationSubDir));

        $fileNameParts = [$commandDto->getName()];

        if (null !== $destinationSuffix && '' !== \trim($destinationSuffix, '.')) {
            $fileNameParts[] = \trim($destinationSuffix, '.');
        }

        $fileNameParts[] = 'conf';

        $pathParts[] = \implode('.', $fileNameParts);

        return \implode('/', $pathParts);
    }

    /** @throws InvalidConfigurationException */
    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): string {
        $configurationParameters = [
            '%programGroupName%' => \implode('-', [$this->getPrefix($configDto, $commandDto), $commandDto->getName()]),
            '%command%' => \implode(' ', $commandDto->getCommand()),
            '%user%' => $this->getUser($configDto, $commandDto),
            '%numberOfProcesses%' => (string)$this->getNumberOfProcesses($configDto, $commandDto),
            '%autoStart%' => true === $this->getAutoStart($configDto, $commandDto) ? 'true' : 'false',
            '%autoRestart%' => true === $this->getAutoRestart($configDto, $commandDto) ? 'true' : 'false',
            '%logFile%' => $commandDto->getSettings()->getLogFile() ?? \sprintf('%s/%s.log', \rtrim($configDto->getLogsDir(), '/'), $commandDto->getName()),
        ];

        return \str_replace(
            \array_keys($configurationParameters),
            \array_values($configurationParameters),
            $this->getTemplate(),
        );
    }

    /** @throws InvalidConfigurationException */
    protected function getAutoStart(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): bool {
        $autoStart = $commandDto->getSettings()->getAutoStart() ?? $configDto->getSettings()->getAutoStart();

        if (null === $autoStart) {
            throw new InvalidConfigurationException('the `auto start` is mandatory');
        }

        return $autoStart;
    }

    /** @throws InvalidConfigurationException */
    protected function getAutoRestart(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): bool {
        $autoRestart = $commandDto->getSettings()->getAutoRestart() ?? $configDto->getSettings()->getAutoRestart();

        if (null === $autoRestart) {
            throw new InvalidConfigurationException('the `auto restart` is mandatory');
        }

        return $autoRestart;
    }

    /** @throws InvalidConfigurationException */
    protected function getPrefix(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $prefix = $commandDto->getSettings()->getPrefix() ?? $configDto->getSettings()->getPrefix();

        if (null === $prefix || '' === $prefix) {
            throw new InvalidConfigurationException('the `prefix` is mandatory');
        }

        return $prefix;
    }

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

    protected function getTemplate(): string
    {
        return '[program:%programGroupName%]
command = %command%
process_name = %(program_name)s_%(process_num)s
numprocs = %numberOfProcesses%
autostart = %autoStart%
autorestart = %autoRestart%
stdout_logfile = %logFile%
stderr_logfile = %logFile%
user = %user%
stopwaitsecs = 30
stdout_logfile_maxbytes = 0
stderr_logfile_maxbytes = 0
stdout_logfile_backups = 0
stderr_logfile_backups = 0
startsecs = 0';
    }
}
