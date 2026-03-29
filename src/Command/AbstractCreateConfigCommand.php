<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCreateConfigCommand extends AbstractCommand
{
    /**
     * @param array<string, mixed> $commands
     */
    public function __construct(
        private readonly ConfGenerateService $confGenerateService,
        private readonly ?ConfigInterface $configInterface,
        private readonly array $commands,
        string $name,
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->configInterface) {
            $this->warning('no configuration is set');

            return static::SUCCESS;
        }

        try {
            $configurationFiles = $this->confGenerateService->generate(
                $this->configInterface,
                $this->commands,
            );

            $configurationFilesCount = \count($configurationFiles);

            if (0 === $configurationFilesCount) {
                $this->warning('no conf files were generated');
            } else {
                $this->success(\sprintf('generated `%s` conf files', $configurationFilesCount));

                foreach ($configurationFiles as $configurationFile) {
                    $this->writeln($configurationFile);
                }
            }
        } catch (ConfGenerateException $exception) {
            $this->error($exception->getMessage(), $exception);

            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
