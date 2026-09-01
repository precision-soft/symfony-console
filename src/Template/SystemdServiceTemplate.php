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
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerDestinationPathTrait;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerNumberOfProcessesTrait;
use PrecisionSoft\Symfony\Console\Template\Trait\WorkerSettingsTrait;

class SystemdServiceTemplate implements TemplateInterface
{
    use WorkerDestinationPathTrait;
    use WorkerNumberOfProcessesTrait;
    use WorkerSettingsTrait;

    public const RESTART_POLICIES = ['no', 'on-success', 'on-failure', 'on-abnormal', 'on-watchdog', 'on-abort', 'always'];

    public const DEFAULT_RESTART_POLICY = 'always';

    protected const EXTENSION = 'service';

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
            $numberOfProcesses = $this->getNumberOfProcesses($configInterface, $commandDto);

            for ($instance = 1; $instance <= $numberOfProcesses; ++$instance) {
                $confFilesDto->addFile(
                    $this->getPath($configInterface, $commandDto, 1 === $numberOfProcesses ? null : $instance),
                    $this->buildService($configInterface, $commandDto, $instance),
                );
            }
        }

        return $confFilesDto;
    }

    /** @throws InvalidConfigurationException */
    protected function getPath(
        ConfigDto $configDto,
        CommandDto $commandDto,
        ?int $instance,
    ): string {
        $serviceName = $this->getServiceName($configDto, $commandDto);

        return $this->buildWorkerPath(
            $configDto,
            $commandDto,
            null === $instance ? $serviceName : $serviceName . '-' . $instance,
            static::EXTENSION,
        );
    }

    /** @throws InvalidConfigurationException */
    protected function buildService(
        ConfigDto $configDto,
        CommandDto $commandDto,
        int $instance,
    ): string {
        $serviceName = $this->getServiceName($configDto, $commandDto);
        $logFile = $this->getLogFile($configDto, $commandDto);
        $environmentFile = $commandDto->getSettings()->getEnvironmentFile() ?? $configDto->getSettings()->getEnvironmentFile();

        $lines = [
            '[Unit]',
            \sprintf('Description=%s instance %s', $this->escapeSpecifiers($serviceName), $instance),
            'After=network.target',
            '',
            '[Service]',
            'Type=simple',
            'User=' . $this->escapeSpecifiers($this->getUser($configDto, $commandDto)),
            'WorkingDirectory=' . $this->escapeSpecifiers($this->getWorkingDirectory($configDto, $commandDto)),
            'ExecStart=' . $this->escapeSpecifiers($this->getExecStart($commandDto)),
            'Restart=' . $this->getRestartPolicy($configDto, $commandDto),
            'StandardOutput=' . $this->escapeSpecifiers($commandDto->getSettings()->getStandardOutput() ?? $configDto->getSettings()->getStandardOutput() ?? 'append:' . $logFile),
            'StandardError=' . $this->escapeSpecifiers($commandDto->getSettings()->getStandardError() ?? $configDto->getSettings()->getStandardError() ?? 'append:' . $logFile),
        ];

        if (null !== $environmentFile && '' !== $environmentFile) {
            $lines[] = 'EnvironmentFile=' . $this->escapeSpecifiers($environmentFile);
        }

        $lines[] = '';
        $lines[] = '[Install]';
        $lines[] = 'WantedBy=multi-user.target';

        return \implode(\PHP_EOL, $lines);
    }

    /** @throws InvalidConfigurationException */
    protected function getServiceName(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $prefix = $commandDto->getSettings()->getPrefix() ?? $configDto->getSettings()->getPrefix();

        /* `@` would make systemd read the unit as a template, and `..` is what the writer rejects as traversal */
        $serviceName = \trim(
            (string)\preg_replace(
                ['/[^A-Za-z0-9_.-]+/', '/\.{2,}/'],
                ['-', '.'],
                \implode('-', \array_filter([$prefix, $commandDto->getName()])),
            ),
            '-.',
        );

        if ('' === $serviceName) {
            throw new InvalidConfigurationException('the systemd service name is empty');
        }

        return $serviceName;
    }

    /** @throws InvalidConfigurationException */
    protected function getWorkingDirectory(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $workingDirectory = $commandDto->getSettings()->getWorkingDirectory() ?? $configDto->getSettings()->getWorkingDirectory();

        if (null === $workingDirectory || '' === $workingDirectory) {
            throw new InvalidConfigurationException('the `working directory` is mandatory');
        }

        return $workingDirectory;
    }

    /** @throws InvalidConfigurationException */
    protected function getRestartPolicy(
        ConfigDto $configDto,
        CommandDto $commandDto,
    ): string {
        $restartPolicy = $commandDto->getSettings()->getRestartPolicy()
            ?? $configDto->getSettings()->getRestartPolicy()
            ?? static::DEFAULT_RESTART_POLICY;

        if (false === \in_array($restartPolicy, static::RESTART_POLICIES, true)) {
            throw new InvalidConfigurationException(\sprintf('invalid systemd restart policy `%s`', $restartPolicy));
        }

        return $restartPolicy;
    }

    /** @throws InvalidConfigurationException */
    protected function getExecStart(CommandDto $commandDto): string
    {
        $command = $commandDto->getCommand();
        $executable = $command[0] ?? '';

        if (false === \str_starts_with($executable, '/')) {
            throw new InvalidConfigurationException(
                \sprintf('the systemd `exec start` needs an absolute executable path, got `%s`', $executable),
            );
        }

        return \implode(' ', \array_map(
            fn(string $commandPart): string => $this->quoteCommandPart($commandPart),
            $command,
        ));
    }

    /* systemd splits `ExecStart` on whitespace, so an argument carrying any would silently become several */
    protected function quoteCommandPart(string $commandPart): string
    {
        if ('' !== $commandPart && 0 === \preg_match('/[\s"\'\\\\]/', $commandPart)) {
            return $commandPart;
        }

        return '"' . \str_replace(['\\', '"'], ['\\\\', '\\"'], $commandPart) . '"';
    }

    /* systemd expands specifiers in every value it reads, and an unknown `%x` makes the unit refuse to start */
    protected function escapeSpecifiers(string $value): string
    {
        return \str_replace('%', '%%', $value);
    }
}
