<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service\ConfGenerate;

use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;

class ConfGenerateService
{
    /** @var TemplateInterface[] */
    private array $templates;

    /** @param iterable<TemplateInterface> $templates */
    public function __construct(
        iterable $templates,
        private readonly ConfFileWriter $confFileWriter,
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
        $this->confFileWriter->initLogsDir($configInterface->getLogsDir());

        $templateInterface = $this->getTemplate($configInterface);

        $confFilesDto = $templateInterface->generate($configInterface, $commands);

        return $this->confFileWriter->save($confFilesDto, $configInterface->getConfFilesDir());
    }

    private function getTemplate(ConfigInterface $configInterface): TemplateInterface
    {
        $templateClass = $configInterface->getTemplateClass();

        if (false === \array_key_exists($templateClass, $this->templates)) {
            throw new ConfGenerateException(\sprintf('the template `%s` does not exist', $templateClass));
        }

        return $this->templates[$templateClass];
    }
}
