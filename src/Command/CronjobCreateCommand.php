<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Command;

use PrecisionSoft\Symfony\Console\Dto\Cronjob\CronjobDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;

class CronjobCreateCommand extends AbstractCreateConfigCommand
{
    public const NAME = 'precision-soft:symfony:console:cronjob-create';

    /**
     * @param array<string, mixed>|null $cronjobConfiguration
     * @throws InvalidConfigurationException
     * @throws InvalidValueException
     */
    public function __construct(
        ConfGenerateService $confGenerateService,
        ?array $cronjobConfiguration,
    ) {
        $cronjobDto = null === $cronjobConfiguration ? null : new CronjobDto($cronjobConfiguration);

        parent::__construct(
            $confGenerateService,
            $cronjobDto?->getConfig(),
            $cronjobDto?->getCommands() ?? [],
            self::NAME,
        );
    }
}
