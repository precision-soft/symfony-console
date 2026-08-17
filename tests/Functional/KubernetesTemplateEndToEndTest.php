<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Functional;

use PHPUnit\Framework\Attributes\Group;
use PrecisionSoft\Symfony\Console\Command\CronjobCreateCommand;
use PrecisionSoft\Symfony\Console\Command\WorkerCreateCommand;
use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\DependencyInjection\PrecisionSoftSymfonyConsoleExtension;
use PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate;
use PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/** @internal */
#[Group('integration')]
final class KubernetesTemplateEndToEndTest extends AbstractTestCase
{
    private string $baseDir;

    public static function getMockDto(): MockDto
    {
        return new MockDto(CronjobCreateCommand::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = \sys_get_temp_dir() . '/kubernetes_template_e2e_' . \bin2hex(\random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);

        parent::tearDown();
    }

    public function testCompiledCronjobCommandWritesAParsableManifest(): void
    {
        $commandTester = $this->runCronjobCreate($this->getCronjobCommands());

        static::assertSame(CronjobCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $jobs = $this->parseCronjobManifest();

        static::assertCount(2, $jobs);
        static::assertSame(
            [
                'name' => 'cleanup',
                Configuration::COMMAND => 'bin/console app:cleanup --force',
                Configuration::SCHEDULE => '0 3 * * *',
            ],
            $jobs[0],
        );
        static::assertSame(
            [
                'name' => 'report',
                Configuration::COMMAND => 'bin/console app:report',
                Configuration::SCHEDULE => '*/5 * * * *',
            ],
            $jobs[1],
        );
    }

    public function testGeneratedCronjobManifestKeepsScheduleAndCommandAsStrings(): void
    {
        $this->runCronjobCreate($this->getCronjobCommands());

        $jobs = $this->parseCronjobManifest();

        static::assertIsString($jobs[0][Configuration::SCHEDULE]);
        static::assertIsString($jobs[0][Configuration::COMMAND]);
    }

    public function testGeneratedCronjobManifestDeclaresJobsAsAYamlSequence(): void
    {
        $this->runCronjobCreate($this->getCronjobCommands());

        /* only PARSE_OBJECT_FOR_MAP separates a sequence from a mapping keyed `0:`, `1:` */
        $manifest = Yaml::parseFile($this->getCronjobManifestPath(), Yaml::PARSE_OBJECT_FOR_MAP);

        static::assertInstanceOf(stdClass::class, $manifest);

        $cronJobs = $manifest->CronJobs;

        static::assertInstanceOf(stdClass::class, $cronJobs);
        static::assertIsArray($cronJobs->jobs);
    }

    public function testCompiledWorkerCommandWritesAParsableManifest(): void
    {
        $commandTester = $this->runWorkerCreate([
            'messenger-consume' => [
                Configuration::COMMAND => 'bin/console messenger:consume async',
                Configuration::SETTINGS => [
                    Configuration::NUMBER_OF_PROCESSES => 3,
                ],
            ],
            'default-parallelism' => [
                Configuration::COMMAND => 'bin/console app:work',
            ],
        ]);

        static::assertSame(WorkerCreateCommand::SUCCESS, $commandTester->getStatusCode());

        $workers = $this->parseWorkerManifest();

        static::assertCount(2, $workers);
        static::assertSame(
            [
                'name' => 'messenger-consume',
                Configuration::COMMAND => 'bin/console messenger:consume async',
                'parallelism' => 3,
            ],
            $workers[0],
        );
        static::assertSame(1, $workers[1]['parallelism']);
    }

    public function testGeneratedWorkerManifestDeclaresWorkersAsAYamlSequence(): void
    {
        $this->runWorkerCreate([
            'messenger-consume' => [
                Configuration::COMMAND => 'bin/console messenger:consume async',
            ],
        ]);

        /* only PARSE_OBJECT_FOR_MAP separates a sequence from a mapping keyed `0:`, `1:` */
        $manifest = Yaml::parseFile($this->getWorkerManifestPath(), Yaml::PARSE_OBJECT_FOR_MAP);

        static::assertInstanceOf(stdClass::class, $manifest);

        $jobs = $manifest->Jobs;

        static::assertInstanceOf(stdClass::class, $jobs);
        static::assertIsArray($jobs->workers);
    }

    public function testAManifestOfPlainValuesParsesIdentically(): void
    {
        $this->runCronjobCreate([
            'plain' => [
                Configuration::COMMAND => 'binconsole appplain',
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '0',
                    Configuration::HOUR => '3',
                    Configuration::DAY_OF_MONTH => '1',
                    Configuration::MONTH => '1',
                    Configuration::DAY_OF_WEEK => '1',
                ],
            ],
        ]);

        $jobs = $this->parseCronjobManifest();

        static::assertSame(
            [
                'name' => 'plain',
                Configuration::COMMAND => 'binconsole appplain',
                Configuration::SCHEDULE => '0 3 1 1 1',
            ],
            $jobs[0],
        );
    }

    /** @return array<string, mixed> */
    private function getCronjobCommands(): array
    {
        return [
            'cleanup' => [
                Configuration::COMMAND => 'bin/console app:cleanup --force',
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '0',
                    Configuration::HOUR => '3',
                ],
            ],
            'report' => [
                Configuration::COMMAND => 'bin/console app:report',
                Configuration::SCHEDULE => [
                    Configuration::MINUTE => '*/5',
                ],
            ],
        ];
    }

    private function getCronjobManifestPath(): string
    {
        return $this->baseDir . '/generated_conf/k8s-cron/cronjobs.yaml';
    }

    private function getWorkerManifestPath(): string
    {
        return $this->baseDir . '/generated_conf/k8s-worker/workers.yaml';
    }

    /** @return array<int, mixed> */
    private function parseCronjobManifest(): array
    {
        $manifest = Yaml::parseFile($this->getCronjobManifestPath());

        static::assertIsArray($manifest);
        static::assertArrayHasKey('CronJobs', $manifest);
        static::assertIsArray($manifest['CronJobs']);
        static::assertIsArray($manifest['CronJobs']['jobs']);

        return \array_values($manifest['CronJobs']['jobs']);
    }

    /** @return array<int, mixed> */
    private function parseWorkerManifest(): array
    {
        $manifest = Yaml::parseFile($this->getWorkerManifestPath());

        static::assertIsArray($manifest);
        static::assertArrayHasKey('Jobs', $manifest);
        static::assertIsArray($manifest['Jobs']);
        static::assertIsArray($manifest['Jobs']['workers']);

        return \array_values($manifest['Jobs']['workers']);
    }

    /** @param array<string, mixed> $commands */
    private function runCronjobCreate(array $commands): CommandTester
    {
        $containerBuilder = $this->buildContainerBuilder();

        (new PrecisionSoftSymfonyConsoleExtension())->load([
            [
                Configuration::CRONJOB => [
                    Configuration::CONFIG => [
                        Configuration::TEMPLATE_CLASS => KubernetesCronjobTemplate::class,
                        Configuration::CONF_FILES_DIR => '%kernel.project_dir%/generated_conf/k8s-cron',
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => 'cronjobs.yaml',
                        ],
                    ],
                    Configuration::COMMANDS => $commands,
                ],
            ],
        ], $containerBuilder);

        return $this->executeCommand($containerBuilder, CronjobCreateCommand::class);
    }

    /** @param array<string, mixed> $commands */
    private function runWorkerCreate(array $commands): CommandTester
    {
        $containerBuilder = $this->buildContainerBuilder();

        (new PrecisionSoftSymfonyConsoleExtension())->load([
            [
                Configuration::WORKER => [
                    Configuration::CONFIG => [
                        Configuration::TEMPLATE_CLASS => KubernetesWorkerTemplate::class,
                        Configuration::CONF_FILES_DIR => '%kernel.project_dir%/generated_conf/k8s-worker',
                        Configuration::SETTINGS => [
                            Configuration::DESTINATION_FILE => 'workers.yaml',
                        ],
                    ],
                    Configuration::COMMANDS => $commands,
                ],
            ],
        ], $containerBuilder);

        return $this->executeCommand($containerBuilder, WorkerCreateCommand::class);
    }

    private function buildContainerBuilder(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter('kernel.logs_dir', $this->baseDir);
        $containerBuilder->setParameter('kernel.project_dir', $this->baseDir);

        $containerBuilder->register(Filesystem::class, Filesystem::class);

        return $containerBuilder;
    }

    /** @param class-string<Command> $commandClass */
    private function executeCommand(ContainerBuilder $containerBuilder, string $commandClass): CommandTester
    {
        /* nothing references the command, so RemoveUnusedDefinitionsPass would drop the private definition */
        $containerBuilder->getDefinition($commandClass)->setPublic(true);

        $containerBuilder->compile();

        $command = $containerBuilder->get($commandClass);

        static::assertInstanceOf($commandClass, $command);
        static::assertInstanceOf(Command::class, $command);

        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        return $commandTester;
    }
}
