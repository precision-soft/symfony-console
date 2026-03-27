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
        ConfigInterface $config,
        array           $commands,
    ): array {
        $this->initLogsDir($config);

        $template = $this->getTemplate($config);

        $confFilesDto = $template->generate($config, $commands);

        return $this->save($confFilesDto, $config->getConfFilesDir());
    }

    private function save(ConfFilesDto $confFilesDto, string $destinationDir): array
    {
        $tempDir = \sys_get_temp_dir() . '/' . \uniqid('conf_', true);

        $this->filesystem->mkdir($tempDir, 0755);

        try {
            $configurationFiles = [];

            foreach ($confFilesDto->getFiles() as $path => $content) {
                $relativePath = \ltrim(\substr($path, \strlen($destinationDir)), '/');
                $tempPath = $tempDir . '/' . $relativePath;

                $this->filesystem->dumpFile($tempPath, $content);

                $configurationFiles[] = $path;
            }

            $backupDir = null;
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

            throw $exception;
        } catch (Throwable $throwable) {
            if (true === $this->filesystem->exists($tempDir)) {
                $this->filesystem->remove($tempDir);
            }

            throw new Exception($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
        }

        return $configurationFiles;
    }

    private function getTemplate(ConfigInterface $config): TemplateInterface
    {
        $templateClass = $config->getTemplateClass();

        if (false === isset($this->templates[$templateClass])) {
            throw new Exception(\sprintf('the template `%s` does not exist', $templateClass));
        }

        return $this->templates[$templateClass];
    }

    private function initLogsDir(ConfigInterface $config): void
    {
        $this->filesystem->mkdir($config->getLogsDir(), 0755);
    }
}
