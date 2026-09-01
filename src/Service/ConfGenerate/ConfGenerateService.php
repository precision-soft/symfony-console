<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Service\ConfGenerate;

use Exception;
use PrecisionSoft\Symfony\Console\Contract\ConfigInterface;
use PrecisionSoft\Symfony\Console\Contract\TemplateInterface;
use PrecisionSoft\Symfony\Console\Dto\ConfFileChangeDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFileChangesDto;
use PrecisionSoft\Symfony\Console\Dto\ConfFilesDto;
use PrecisionSoft\Symfony\Console\Exception\ConfGenerateException;

class ConfGenerateService
{
    /** @var array<class-string<TemplateInterface>, TemplateInterface> */
    protected array $templates;

    /** @param iterable<TemplateInterface> $templates */
    public function __construct(
        iterable $templates,
        protected readonly ConfFileWriter $confFileWriter,
        protected readonly ConfFileDiffRenderer $confFileDiffRenderer = new ConfFileDiffRenderer(),
    ) {
        $this->templates = [];
        foreach ($templates as $templateInterface) {
            $this->templates[$templateInterface::class] = $templateInterface;
        }
    }

    /**
     * @param array<string, mixed> $commands
     * @return array<int, string>
     * @throws ConfGenerateException
     */
    public function generate(
        ConfigInterface $configInterface,
        array $commands,
    ): array {
        $this->confFileWriter->initLogsDir($configInterface->getLogsDir());

        return $this->confFileWriter->save(
            $this->render($configInterface, $commands),
            $configInterface->getConfFilesDir(),
        );
    }

    /**
     * @param array<string, mixed> $commands
     * @throws ConfGenerateException
     */
    public function preview(ConfigInterface $configInterface, array $commands): ConfFileChangesDto
    {
        return $this->confFileWriter->diff(
            $this->render($configInterface, $commands),
            $configInterface->getConfFilesDir(),
        );
    }

    /** @return array<int, string> */
    public function renderChangeDiff(ConfFileChangeDto $confFileChangeDto): array
    {
        return $this->confFileDiffRenderer->render($confFileChangeDto);
    }

    /**
     * @param array<string, mixed> $commands
     * @throws ConfGenerateException
     */
    protected function render(ConfigInterface $configInterface, array $commands): ConfFilesDto
    {
        $templateInterface = $this->getTemplate($configInterface);

        try {
            return $templateInterface->generate($configInterface, $commands);
        } catch (Exception $exception) {
            throw true === $exception instanceof ConfGenerateException
                ? $exception
                : ConfGenerateException::from(
                    $exception,
                    [
                        'templateClass' => $templateInterface::class,
                        'confFilesDir' => $configInterface->getConfFilesDir(),
                    ],
                );
        }
    }

    /** @throws ConfGenerateException */
    protected function getTemplate(ConfigInterface $configInterface): TemplateInterface
    {
        $templateClass = $configInterface->getTemplateClass();

        if (false === \array_key_exists($templateClass, $this->templates)) {
            throw new ConfGenerateException(\sprintf('the template `%s` does not exist', $templateClass));
        }

        return $this->templates[$templateClass];
    }
}
