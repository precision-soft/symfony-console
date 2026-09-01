<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Service\ConfGenerate;

use Mockery;
use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileStatus;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileDiffRenderer;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfFileWriter;
use PrecisionSoft\Symfony\Console\Service\ConfGenerate\ConfGenerateService;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ConfGenerateServicePreviewTest extends AbstractTestCase
{
    private Filesystem $filesystem;
    private string $confFilesDirectory;
    private string $logsDirectory;

    public static function getMockDto(): MockDto
    {
        return new MockDto(
            ConfGenerateService::class,
            [[], new ConfFileWriter(new Filesystem())],
            true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->confFilesDirectory = \sys_get_temp_dir() . '/preview_conf_' . \uniqid('', true);
        $this->logsDirectory = \sys_get_temp_dir() . '/preview_logs_' . \uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove([$this->confFilesDirectory, $this->logsDirectory]);

        parent::tearDown();
    }

    public function testPreviewReportsTheRenderedFilesWithoutWritingThem(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile($this->confFilesDirectory . '/test.conf', 'test content');
        $templateInterfaceMock = $this->getTemplateInterfaceMock($confFilesDto);

        $changes = (new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter($this->filesystem)))
            ->preview($this->getConfigInterfaceMock($templateInterfaceMock::class), [])
            ->getChanges();

        static::assertSame(
            ConfFileStatus::Added,
            $changes[$this->confFilesDirectory . '/test.conf']->getStatus(),
        );
        static::assertDirectoryDoesNotExist($this->confFilesDirectory);
    }

    /* generation creates the logs directory as a side effect, and a read only mode must not */
    public function testPreviewDoesNotCreateTheLogsDirectory(): void
    {
        $confFilesDto = (new ConfFilesDto())->addFile($this->confFilesDirectory . '/test.conf', 'test content');
        $templateInterfaceMock = $this->getTemplateInterfaceMock($confFilesDto);

        (new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter($this->filesystem)))
            ->preview($this->getConfigInterfaceMock($templateInterfaceMock::class), []);

        static::assertDirectoryDoesNotExist($this->logsDirectory);
    }

    public function testPreviewReportsAnIdenticalFileAsUnchanged(): void
    {
        $this->filesystem->dumpFile($this->confFilesDirectory . '/test.conf', 'test content');

        $confFilesDto = (new ConfFilesDto())->addFile($this->confFilesDirectory . '/test.conf', 'test content');
        $templateInterfaceMock = $this->getTemplateInterfaceMock($confFilesDto);

        static::assertSame(
            [],
            (new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter($this->filesystem)))
                ->preview($this->getConfigInterfaceMock($templateInterfaceMock::class), [])
                ->getPendingChanges(),
        );
    }

    public function testPreviewWrapsTemplateExceptionsInConfGenerateException(): void
    {
        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')
            ->once()
            ->andThrow(new InvalidValueException('the file path is in use `worker.blue.conf`'));

        $confGenerateService = new ConfGenerateService([$templateInterfaceMock], new ConfFileWriter($this->filesystem));

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('the file path is in use `worker.blue.conf`');

        $confGenerateService->preview($this->getConfigInterfaceMock($templateInterfaceMock::class), []);
    }

    public function testPreviewThrowsWhenTheTemplateDoesNotExist(): void
    {
        $confGenerateService = new ConfGenerateService([], new ConfFileWriter($this->filesystem));

        $this->expectException(ConfGenerateException::class);
        $this->expectExceptionMessage('the template `NonExistentTemplate` does not exist');

        $confGenerateService->preview($this->getConfigInterfaceMock('NonExistentTemplate'), []);
    }

    public function testRenderChangeDiffDelegatesToTheRenderer(): void
    {
        $confFileDiffRendererMock = Mockery::mock(ConfFileDiffRenderer::class);
        $confFileDiffRendererMock->shouldReceive('render')
            ->once()
            ->andReturn(['@@ -1,1 +1,1 @@']);

        $confGenerateService = new ConfGenerateService([], new ConfFileWriter($this->filesystem), $confFileDiffRendererMock);

        static::assertSame(
            ['@@ -1,1 +1,1 @@'],
            $confGenerateService->renderChangeDiff(
                new ConfFileChangeDto('/units/worker.service', ConfFileStatus::Changed, 'after', 'before'),
            ),
        );
    }

    private function getTemplateInterfaceMock(ConfFilesDto $confFilesDto): TemplateInterface
    {
        $templateInterfaceMock = Mockery::mock(TemplateInterface::class);
        $templateInterfaceMock->shouldReceive('generate')
            ->once()
            ->andReturn($confFilesDto);

        return $templateInterfaceMock;
    }

    private function getConfigInterfaceMock(string $templateClass): ConfigInterface
    {
        $configInterfaceMock = Mockery::mock(ConfigInterface::class);
        $configInterfaceMock->shouldReceive('getTemplateClass')->andReturn($templateClass);
        $configInterfaceMock->shouldReceive('getLogsDir')->andReturn($this->logsDirectory);
        $configInterfaceMock->shouldReceive('getConfFilesDir')->andReturn($this->confFilesDirectory);

        return $configInterfaceMock;
    }
}
