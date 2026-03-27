<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Dto;

use PHPUnit\Framework\TestCase;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;

/**
 * @internal
 */
final class ConfFilesDtoTest extends TestCase
{
    public function testConstructorInitializesEmptyFiles(): void
    {
        $confFilesDto = new ConfFilesDto();

        static::assertSame([], $confFilesDto->getFiles());
    }

    public function testAddFileAddsFileSuccessfully(): void
    {
        $confFilesDto = new ConfFilesDto();

        $result = $confFilesDto->addFile('/path/to/file.conf', 'file content');

        static::assertSame($confFilesDto, $result);
        static::assertCount(1, $confFilesDto->getFiles());
        static::assertSame('file content', $confFilesDto->getFiles()['/path/to/file.conf']);
    }

    public function testAddMultipleFiles(): void
    {
        $confFilesDto = new ConfFilesDto();

        $confFilesDto->addFile('/path/to/file1.conf', 'content 1');
        $confFilesDto->addFile('/path/to/file2.conf', 'content 2');

        static::assertCount(2, $confFilesDto->getFiles());
    }

    public function testAddFileToDuplicatePathThrowsException(): void
    {
        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile('/path/to/file.conf', 'content 1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('the file path is in use `/path/to/file.conf`');

        $confFilesDto->addFile('/path/to/file.conf', 'content 2');
    }

    public function testAddFileReturnsSelfForChaining(): void
    {
        $confFilesDto = new ConfFilesDto();

        $result = $confFilesDto->addFile('/path/one.conf', 'one')
            ->addFile('/path/two.conf', 'two')
            ->addFile('/path/three.conf', 'three');

        static::assertSame($confFilesDto, $result);
        static::assertCount(3, $confFilesDto->getFiles());
    }

    public function testGetFilesReturnsAllFiles(): void
    {
        $confFilesDto = new ConfFilesDto();
        $confFilesDto->addFile('/a', 'a content');
        $confFilesDto->addFile('/b', 'b content');

        $files = $confFilesDto->getFiles();

        static::assertArrayHasKey('/a', $files);
        static::assertArrayHasKey('/b', $files);
        static::assertSame('a content', $files['/a']);
        static::assertSame('b content', $files['/b']);
    }
}
