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
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;

class SupervisorTemplate implements TemplateInterface
{
    use WorkerNumberOfProcessesTrait;

    /**
     * @param ConfigDto $configInterface
     * @param CommandDto[] $commands
     */
    public function generate(
        ConfigInterface $configInterface,
        array $commands,
    ): ConfFilesDto {
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
        return \sprintf('%s/%s.conf', $configDto->getConfFilesDir(), $commandDto->getName());
    }

    protected function buildCommand(
        CommandDto $commandDto,
        ConfigDto $configDto,
    ): string {
        $configurationParams = [
            '%programGroupName%' => \implode('-', [$this->getPrefix($configDto, $commandDto), $commandDto->getName()]),
            '%command%' => \implode(' ', $commandDto->getCommand()),
            '%user%' => $this->getUser($configDto, $commandDto),
            '%numberOfProcesses%' => (string)$this->getNumberOfProcesses($configDto, $commandDto),
            '%autoStart%' => true === $this->getAutoStart($configDto, $commandDto) ? 'true' : 'false',
            '%autoRestart%' => true === $this->getAutoRestart($configDto, $commandDto) ? 'true' : 'false',
            '%logFile%' => $commandDto->getSettings()->getLogFile() ?? \sprintf('%s/%s.log', $configDto->getLogsDir(), $commandDto->getName()),
        ];

        return \str_replace(
            \array_keys($configurationParams),
            \array_values($configurationParams),
            $this->getTemplate(),
        );
    }

    protected function getAutoStart(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): bool {
        $autoStart = $commandDto->getSettings()->getAutoStart() ?? $configDto->getSettings()->getAutoStart();

        if (null === $autoStart) {
            throw new Exception('the `auto start` is mandatory');
        }

        return $autoStart;
    }

    protected function getAutoRestart(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): bool {
        $autoRestart = $commandDto->getSettings()->getAutoRestart() ?? $configDto->getSettings()->getAutoRestart();

        if (null === $autoRestart) {
            throw new Exception('the `auto restart` is mandatory');
        }

        return $autoRestart;
    }

    protected function getPrefix(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $prefix = $commandDto->getSettings()->getPrefix() ?? $configDto->getSettings()->getPrefix();

        if (null === $prefix || '' === $prefix) {
            throw new Exception('the `prefix` is mandatory');
        }

        return $prefix;
    }

    protected function getUser(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $user = $commandDto->getSettings()->getUser() ?? $configDto->getSettings()->getUser();

        if (null === $user || '' === $user) {
            throw new Exception('the `user` is mandatory');
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
