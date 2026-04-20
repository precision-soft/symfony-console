<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Dto;

use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;

class ConfFilesDto
{
    /** @var array<string, string> */
    protected array $files;

    public function __construct()
    {
        $this->files = [];
    }

    /** @return array<string, string> */
    public function getFiles(): array
    {
        return $this->files;
    }

    /** @throws InvalidValueException */
    public function addFile(string $path, string $content): self
    {
        if (true === \array_key_exists($path, $this->files)) {
            throw new InvalidValueException(
                \sprintf('the file path is in use `%s`', $path),
            );
        }

        $this->files[$path] = $content;

        return $this;
    }
}
