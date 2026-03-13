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
        array $commands,
    ): array {
        $this->initConfFilesDir($config);

        $this->initLogsDir($config);

        $template = $this->getTemplate($config);

        $configurationFilesDto = $template->generate($config, $commands);

        return $this->save($configurationFilesDto);
    }

    private function save(ConfFilesDto $configurationFilesDto): array
    {
        $filesystem = new Filesystem();
        $configurationFiles = [];

        foreach ($configurationFilesDto->getFiles() as $path => $content) {
            $filesystem->appendToFile($path, $content);

            $configurationFiles[] = $path;
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

    private function initConfFilesDir(ConfigInterface $config): void
    {
        $filesystem = new Filesystem();

        $filesystem->remove($config->getConfFilesDir());

        $filesystem->mkdir($config->getConfFilesDir(), 0755);
    }
}
