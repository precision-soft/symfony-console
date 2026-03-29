<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class ConfGenerateService
{
    /** @var TemplateInterface[] */
    private array $templates;

    /** @param iterable<TemplateInterface> $templates */
    public function __construct(
        iterable $templates,
        private readonly Filesystem $filesystem,
    ) {
        $this->templates = [];
        foreach ($templates as $templateInterface) {
            $this->templates[$templateInterface::class] = $templateInterface;
        }
    }

    /**
     * @param array<string, mixed> $commands
     * @return array<int, string>
     */
    public function generate(
        ConfigInterface $configInterface,
        array $commands,
    ): array {
        $this->initLogsDir($configInterface);

        $templateInterface = $this->getTemplate($configInterface);

        $confFilesDto = $templateInterface->generate($configInterface, $commands);

        return $this->save($confFilesDto, $configInterface->getConfFilesDir());
    }

    /** @return array<int, string> */
    private function save(ConfFilesDto $confFilesDto, string $destinationDir): array
    {
        $tempDir = \sys_get_temp_dir() . '/' . \uniqid('conf_', true);

        $this->filesystem->mkdir($tempDir, 0755);

        $backupDir = null;

        try {
            $configurationFiles = [];

            foreach ($confFilesDto->getFiles() as $path => $content) {
                if (false === \str_starts_with($path, $destinationDir)) {
                    throw new ConfGenerateException(\sprintf('path `%s` is outside destination directory `%s`', $path, $destinationDir));
                }

                $relativePath = \ltrim(\substr($path, \strlen($destinationDir)), '/');

                if (false !== \strpos($relativePath, '..')) {
                    throw new ConfGenerateException(\sprintf('path traversal detected in `%s`', $path));
                }

                $tempPath = $tempDir . '/' . $relativePath;

                $this->filesystem->dumpFile($tempPath, $content);

                $configurationFiles[] = $path;
            }

            if (true === $this->filesystem->exists($destinationDir)) {
                $backupDir = $destinationDir . '.bak_' . \uniqid('', true);
                $this->filesystem->rename($destinationDir, $backupDir);
            }

            try {
                $this->filesystem->rename($tempDir, $destinationDir);
            } catch (Throwable $throwable) {
                if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                    $this->filesystem->rename($backupDir, $destinationDir);
                }

                throw new ConfGenerateException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
            }

            if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                $this->filesystem->remove($backupDir);
            }
        } catch (Throwable $throwable) {
            if (true === $this->filesystem->exists($tempDir)) {
                $this->filesystem->remove($tempDir);
            }

            if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                $this->filesystem->remove($backupDir);
            }

            throw $throwable instanceof ConfGenerateException
                ? $throwable
                : new ConfGenerateException($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }

        return $configurationFiles;
    }

    private function getTemplate(ConfigInterface $configInterface): TemplateInterface
    {
        $templateClass = $configInterface->getTemplateClass();

        if (false === \array_key_exists($templateClass, $this->templates)) {
            throw new ConfGenerateException(\sprintf('the template `%s` does not exist', $templateClass));
        }

        return $this->templates[$templateClass];
    }

    private function initLogsDir(ConfigInterface $configInterface): void
    {
        $this->filesystem->mkdir($configInterface->getLogsDir(), 0755);
    }
}
