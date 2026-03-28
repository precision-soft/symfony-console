<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\Exception;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class ConfGenerateService
{
    /** @var TemplateInterface[] */
    private array $templates;

    private readonly Filesystem $filesystem;

    public function __construct(iterable $templates)
    {
        $this->templates = [];
        foreach ($templates as $template) {
            $this->templates[$template::class] = $template;
        }

        $this->filesystem = new Filesystem();
    }

    public function generate(
        ConfigInterface $configInterface,
        array $commands,
    ): array {
        try {
            $this->initLogsDir($configInterface);
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }

        $templateInterface = $this->getTemplate($configInterface);

        $confFilesDto = $templateInterface->generate($configInterface, $commands);

        return $this->save($confFilesDto, $configInterface->getConfFilesDir());
    }

    private function save(ConfFilesDto $confFilesDto, string $destinationDir): array
    {
        $tempDir = \sys_get_temp_dir() . '/' . \uniqid('conf_', true);

        $this->filesystem->mkdir($tempDir, 0755);

        $backupDir = null;

        try {
            $configurationFiles = [];

            foreach ($confFilesDto->getFiles() as $path => $content) {
                $relativePath = \ltrim(\substr($path, \strlen($destinationDir)), '/');
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

                throw new Exception($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
            }

            if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                $this->filesystem->remove($backupDir);
            }
        } catch (Exception $exception) {
            if (true === $this->filesystem->exists($tempDir)) {
                $this->filesystem->remove($tempDir);
            }

            if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                $this->filesystem->remove($backupDir);
            }

            throw $exception;
        } catch (Throwable $throwable) {
            if (true === $this->filesystem->exists($tempDir)) {
                $this->filesystem->remove($tempDir);
            }

            if (null !== $backupDir && true === $this->filesystem->exists($backupDir)) {
                $this->filesystem->remove($backupDir);
            }

            throw new Exception($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }

        return $configurationFiles;
    }

    private function getTemplate(ConfigInterface $configInterface): TemplateInterface
    {
        $templateClass = $configInterface->getTemplateClass();

        if (false === isset($this->templates[$templateClass])) {
            throw new Exception(\sprintf('the template `%s` does not exist', $templateClass));
        }

        return $this->templates[$templateClass];
    }

    private function initLogsDir(ConfigInterface $configInterface): void
    {
        $this->filesystem->mkdir($configInterface->getLogsDir(), 0755);
    }
}
