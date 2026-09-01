<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCreateConfigCommand extends AbstractCommand
{
    /**
     * @param array<string, mixed> $commands
     */
    public function __construct(
        protected readonly ConfGenerateService $confGenerateService,
        protected readonly ?ConfigInterface $configInterface,
        protected readonly array $commands,
        string $name,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'preview generated file changes without writing');
        $this->addOption('diff', null, InputOption::VALUE_NONE, 'preview generated file changes with a unified diff of their content');
        $this->addOption('check', null, InputOption::VALUE_NONE, 'exit with failure when generated files are not current');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->configInterface) {
            $this->warning('no configuration is set');

            return static::SUCCESS;
        }

        try {
            if (true === $this->isPreviewRequested($input)) {
                return $this->executePreview($input, $this->configInterface);
            }

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

    protected function isPreviewRequested(InputInterface $input): bool
    {
        return true === $input->getOption('dry-run')
            || true === $input->getOption('diff')
            || true === $input->getOption('check');
    }

    protected function executePreview(InputInterface $input, ConfigInterface $configInterface): int
    {
        $pendingChanges = $this->confGenerateService->preview($configInterface, $this->commands)->getPendingChanges();

        if ([] === $pendingChanges) {
            $this->success('generated conf files are current');

            return static::SUCCESS;
        }

        $diffRequested = true === $input->getOption('diff');

        foreach ($pendingChanges as $confFileChangeDto) {
            $this->writeln(\sprintf('[%s] %s', $confFileChangeDto->getStatus()->value, $confFileChangeDto->getPath()));

            if (false === $diffRequested) {
                continue;
            }

            foreach ($this->confGenerateService->renderChangeDiff($confFileChangeDto) as $diffLine) {
                $this->writelnUnformatted(OutputFormatter::escape($diffLine));
            }
        }

        $this->warning(\sprintf('`%s` generated conf file changes detected', \count($pendingChanges)));

        return true === $input->getOption('check') ? static::FAILURE : static::SUCCESS;
    }
}
