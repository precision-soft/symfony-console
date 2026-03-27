<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use PrecisionSoft\Symfony\Console\Dto\Worker\WorkerDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use PrecisionSoft\Symfony\Console\Service\ConfGenerateService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCreateCommand extends AbstractCommand
{
    public const NAME = 'precision-soft:symfony:console:worker-create';

    private readonly ?WorkerDto $workerDto;

    public function __construct(
        private readonly ConfGenerateService $confGenerateService,
        ?array $config,
    ) {
        $this->workerDto = null === $config ? null : new WorkerDto($config);

        parent::__construct(static::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->workerDto) {
            $this->warning('no configuration is set');

            return static::SUCCESS;
        }

        try {
            $configurationFiles = $this->confGenerateService->generate(
                $this->workerDto->getConfig(),
                $this->workerDto->getCommands(),
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
        } catch (Exception $exception) {
            $this->error($exception->getMessage(), $exception);

            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
