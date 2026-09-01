<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto;

class ConfFileChangeDto
{
    public function __construct(
        protected readonly string $path,
        protected readonly ConfFileStatus $status,
        protected readonly ?string $expectedContent,
        protected readonly ?string $currentContent,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getStatus(): ConfFileStatus
    {
        return $this->status;
    }

    public function getExpectedContent(): ?string
    {
        return $this->expectedContent;
    }

    public function getCurrentContent(): ?string
    {
        return $this->currentContent;
    }
}
