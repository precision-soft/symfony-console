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

    /** @param ConfigDto $configDto */
    public function generate(
        ConfigInterface $configDto,
        array $commands,
    ): ConfFilesDto {
        $confFilesDto = new ConfFilesDto();

        /** @var CommandDto $commandDto */
        foreach ($commands as $commandDto) {
            $worker = $this->buildCommand($commandDto, $configDto);

            $confPath = $this->getPath($configDto, $commandDto);

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
            '%logsDir%' => $configDto->getLogsDir(),
            '%programGroupName%' => \implode('-', [$this->getPrefix($configDto, $commandDto), $commandDto->getName()]),
            '%programName%' => $commandDto->getName(),
            '%command%' => \implode(' ', $commandDto->getCommand()),
            '%user%' => $this->getUser($configDto, $commandDto),
            '%numberOfProcesses%' => $this->getNumberOfProcesses($configDto, $commandDto),
            '%autoStart%' => true === $this->getAutoStart($configDto, $commandDto) ? 'true' : 'false',
            '%autoRestart%' => true === $this->getAutoRestart($configDto, $commandDto) ? 'true' : 'false',
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

        if (true === empty($prefix)) {
            throw new Exception('the `prefix` is mandatory');
        }

        return $prefix;
    }

    protected function getUser(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $user = $commandDto->getSettings()->getUser() ?? $configDto->getSettings()->getUser();

        if (true === empty($user)) {
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
stdout_logfile = %logsDir%/%programName%.log
stderr_logfile = %logsDir%/%programName%.log
user = %user%
stopwaitsecs = 30
stdout_logfile_maxbytes = 0
stderr_logfile_maxbytes = 0
stdout_logfile_backups = 0
stderr_logfile_backups = 0
startsecs = 0';
    }
}
