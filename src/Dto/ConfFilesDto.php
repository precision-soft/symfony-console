<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto;

use PrecisionSoft\Symfony\Console\Exception\Exception;

class ConfFilesDto
{
    /** @var array<string, string> */
    private array $files;

    public function __construct()
    {
        $this->files = [];
    }

    /** @return array<string, string> */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function addFile(string $path, string $content): self
    {
        if (true === \array_key_exists($path, $this->files)) {
            throw new Exception(
                \sprintf('the file path is in use `%s`', $path),
            );
        }

        $this->files[$path] = $content;

        return $this;
    }
}
