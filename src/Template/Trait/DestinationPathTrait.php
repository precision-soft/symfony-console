<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Template\Trait;

trait DestinationPathTrait
{
    /** @return array<int, string> */
    protected function splitDestinationPath(?string $destinationPath): array
    {
        return \array_values(
            \array_filter(
                \explode('/', $destinationPath ?? ''),
                static fn(string $destinationPathSegment): bool => '' !== $destinationPathSegment && '.' !== $destinationPathSegment,
            ),
        );
    }

    protected function normalizeDestinationPath(?string $destinationPath): string
    {
        return \implode('/', $this->splitDestinationPath($destinationPath));
    }

    /* a single flat token, so two files with the same base name in different sub directories stay distinguishable */
    protected function buildDestinationPathLabel(?string $destinationPath): string
    {
        return \implode('.', $this->splitDestinationPath($destinationPath));
    }
}
