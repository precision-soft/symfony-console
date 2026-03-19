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

    public function __construct(iterable $templates)
    {
        $this->templates = [];
        foreach ($templates as $template) {
            $this->templates[$template::class] = $template;
        }
    }

    public function generate(
        ConfigInterface $config,
        array           $commands,
    ): array {
        $this->initLogsDir($config);

        $template = $this->getTemplate($config);

        $configurationFilesDto = $template->generate($config, $commands);

        return $this->save($configurationFilesDto, $config->getConfFilesDir());
    }

    private function save(ConfFilesDto $configurationFilesDto, string $destinationDir): array
    {
        $filesystem = new Filesystem();
        $tempDir = \sys_get_temp_dir() . '/' . \uniqid('conf_', true);

        $filesystem->mkdir($tempDir, 0755);

        try {
            $configurationFiles = [];

            foreach ($configurationFilesDto->getFiles() as $path => $content) {
                $relativePath = \ltrim(\substr($path, \strlen($destinationDir)), '/');
                $tempPath = $tempDir . '/' . $relativePath;

                $filesystem->appendToFile($tempPath, $content);

                $configurationFiles[] = $path;
            }

            if ($filesystem->exists($destinationDir)) {
                $filesystem->remove($destinationDir);
            }

            $filesystem->rename($tempDir, $destinationDir);
        } catch (Throwable $e) {
            if ($filesystem->exists($tempDir)) {
                $filesystem->remove($tempDir);
            }

            throw $e;
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
        $filesystem = new Filesystem();

        $filesystem->mkdir($config->getLogsDir(), 0755);
    }
}
