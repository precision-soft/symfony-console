<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;

trait WorkerDestinationPathTrait
{
    use DestinationPathTrait;

    protected function buildWorkerPath(
        ConfigDto $configDto,
        CommandDto $commandDto,
        string $fileName,
        string $extension,
    ): string {
        $destinationSubDir = $commandDto->getDestinationSubDir() ?? $configDto->getSettings()->getDestinationSubDir();
        $destinationSuffix = $commandDto->getDestinationSuffix() ?? $configDto->getSettings()->getDestinationSuffix();

        $pathParts = [
            \rtrim($configDto->getConfFilesDir(), '/'),
            ...$this->splitDestinationPath($destinationSubDir),
        ];

        $fileNameParts = [$fileName];

        if (null !== $destinationSuffix && '' !== \trim($destinationSuffix, '.')) {
            $fileNameParts[] = \trim($destinationSuffix, '.');
        }

        $fileNameParts[] = $extension;

        $pathParts[] = \implode('.', $fileNameParts);

        return \implode('/', $pathParts);
    }
}
