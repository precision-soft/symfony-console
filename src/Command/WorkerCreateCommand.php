<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use PrecisionSoft\Symfony\Console\Dto\Worker\WorkerDto;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;

class WorkerCreateCommand extends AbstractCreateConfigCommand
{
    public const NAME = 'precision-soft:symfony:console:worker-create';

    /**
     * @param array<string, mixed>|null $workerConfiguration
     */
    public function __construct(
        ConfGenerateService $confGenerateService,
        ?array $workerConfiguration,
    ) {
        $workerDto = null === $workerConfiguration ? null : new WorkerDto($workerConfiguration);

        parent::__construct(
            $confGenerateService,
            $workerDto?->getConfig(),
            $workerDto?->getCommands() ?? [],
            self::NAME,
        );
    }
}
