<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Symfony\Console\Test\Template;

use PrecisionSoft\Symfony\Console\DependencyInjection\Configuration;
use PrecisionSoft\Symfony\Console\Dto\Cronjob\ConfigDto as CronjobConfigDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\CommandDto;
use PrecisionSoft\Symfony\Console\Dto\Worker\ConfigDto;
use PrecisionSoft\Symfony\Console\Exception\InvalidConfigurationException;
use PrecisionSoft\Symfony\Console\Exception\InvalidValueException;
use PrecisionSoft\Symfony\Console\Template\SystemdServiceTemplate;
use PrecisionSoft\Symfony\Phpunit\MockDto;
use PrecisionSoft\Symfony\Phpunit\TestCase\AbstractTestCase;

/**
 * @internal
 */
final class SystemdServiceTemplateTest extends AbstractTestCase
{
    private SystemdServiceTemplate $systemdServiceTemplate;

    public static function getMockDto(): MockDto
    {
        return new MockDto(SystemdServiceTemplate::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemdServiceTemplate = new SystemdServiceTemplate();
    }

    public function testGenerateCreatesOneConcreteUnitPerInstance(): void
    {
        $configDto = $this->getConfigDto([
            Configuration::NUMBER_OF_PROCESSES => 2,
            Configuration::PREFIX => 'acme',
            Configuration::USER => 'www-data',
            Configuration::WORKING_DIRECTORY => '/srv/app',
            Configuration::ENVIRONMENT_FILE => '/srv/app/.env',
            Configuration::RESTART_POLICY => 'on-failure',
            Configuration::STANDARD_OUTPUT => 'journal',
            Configuration::STANDARD_ERROR => 'journal',
        ]);
        $commandDto = $this->getCommandDto('orders');

        $files = $this->systemdServiceTemplate->generate($configDto, ['orders' => $commandDto])->getFiles();

        static::assertSame([
            '/units/acme-orders-1.service',
            '/units/acme-orders-2.service',
        ], \array_keys($files));

        foreach ($files as $content) {
            static::assertStringContainsString('User=www-data', $content);
            static::assertStringContainsString('WorkingDirectory=/srv/app', $content);
            static::assertStringContainsString('EnvironmentFile=/srv/app/.env', $content);
            static::assertStringContainsString('ExecStart=/usr/bin/php bin/console app:consume', $content);
            static::assertStringContainsString('Restart=on-failure', $content);
            static::assertStringContainsString('StandardOutput=journal', $content);
            static::assertStringContainsString('StandardError=journal', $content);
            static::assertStringContainsString('WantedBy=multi-user.target', $content);
        }

        static::assertStringContainsString('Description=acme-orders instance 1', $files['/units/acme-orders-1.service']);
        static::assertStringContainsString('Description=acme-orders instance 2', $files['/units/acme-orders-2.service']);
    }

    public function testGenerateOmitsTheInstanceSuffixForASingleProcess(): void
    {
        $files = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $this->getCommandDto('mail')],
        )->getFiles();

        static::assertSame(['/units/mail.service'], \array_keys($files));
    }

    public function testCommandSettingsOverrideTheConfigLevel(): void
    {
        $configDto = $this->getConfigDto([
            Configuration::USER => 'default',
            Configuration::WORKING_DIRECTORY => '/default',
            Configuration::RESTART_POLICY => 'no',
        ]);
        $commandDto = $this->getCommandDto('mail', [
            Configuration::USER => 'mailer',
            Configuration::WORKING_DIRECTORY => '/mail',
            Configuration::RESTART_POLICY => 'always',
            Configuration::ENVIRONMENT_FILE => '/mail/.env',
            Configuration::STANDARD_OUTPUT => 'null',
            Configuration::STANDARD_ERROR => 'inherit',
        ]);

        $content = $this->systemdServiceTemplate->generate($configDto, ['mail' => $commandDto])
            ->getFiles()['/units/mail.service'];

        static::assertStringContainsString('User=mailer', $content);
        static::assertStringContainsString('WorkingDirectory=/mail', $content);
        static::assertStringContainsString('Restart=always', $content);
        static::assertStringContainsString('EnvironmentFile=/mail/.env', $content);
        static::assertStringContainsString('StandardOutput=null', $content);
        static::assertStringContainsString('StandardError=inherit', $content);
    }

    public function testStandardStreamsFallBackToTheDerivedLogFile(): void
    {
        $content = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $this->getCommandDto('mail')],
        )->getFiles()['/units/mail.service'];

        static::assertStringContainsString('StandardOutput=append:/logs/mail.log', $content);
        static::assertStringContainsString('StandardError=append:/logs/mail.log', $content);
    }

    public function testStandardStreamsFallBackToTheConfigLevelLogFile(): void
    {
        $configDto = $this->getConfigDto([
            Configuration::USER => 'worker',
            Configuration::WORKING_DIRECTORY => '/app',
            Configuration::LOG_FILE => '/custom/config.log',
        ]);

        $content = $this->systemdServiceTemplate->generate($configDto, ['mail' => $this->getCommandDto('mail')])
            ->getFiles()['/units/mail.service'];

        static::assertStringContainsString('StandardOutput=append:/custom/config.log', $content);
    }

    public function testStandardStreamsFallBackToTheCommandLevelLogFile(): void
    {
        $configDto = $this->getConfigDto([
            Configuration::USER => 'worker',
            Configuration::WORKING_DIRECTORY => '/app',
            Configuration::LOG_FILE => '/custom/config.log',
        ]);
        $commandDto = $this->getCommandDto('mail', [Configuration::LOG_FILE => '/custom/command.log']);

        $content = $this->systemdServiceTemplate->generate($configDto, ['mail' => $commandDto])
            ->getFiles()['/units/mail.service'];

        static::assertStringContainsString('StandardOutput=append:/custom/command.log', $content);
    }

    public function testGenerateOmitsTheEnvironmentFileLineWhenNoneIsConfigured(): void
    {
        $content = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $this->getCommandDto('mail')],
        )->getFiles()['/units/mail.service'];

        static::assertStringNotContainsString('EnvironmentFile', $content);
    }

    public function testGenerateHonoursTheDestinationSubDirAndSuffix(): void
    {
        $commandDto = new CommandDto('mail', [
            Configuration::COMMAND => ['/usr/bin/php', 'bin/console', 'app:consume'],
            Configuration::DESTINATION_SUB_DIR => 'machine-a/eu-west',
            Configuration::DESTINATION_SUFFIX => 'blue',
            Configuration::SETTINGS => [],
        ]);

        $files = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $commandDto],
        )->getFiles();

        static::assertSame(['/units/machine-a/eu-west/mail.blue.service'], \array_keys($files));
    }

    public function testGenerateRejectsAForeignConfigDto(): void
    {
        $cronjobConfigDto = new CronjobConfigDto([
            Configuration::TEMPLATE_CLASS => SystemdServiceTemplate::class,
            Configuration::CONF_FILES_DIR => '/units',
            Configuration::LOGS_DIR => '/logs',
            Configuration::SETTINGS => [],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expected ' . ConfigDto::class);

        $this->systemdServiceTemplate->generate($cronjobConfigDto, []);
    }

    public function testGenerateRejectsAMissingUser(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `user` is mandatory');

        $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $this->getCommandDto('mail')],
        );
    }

    public function testGenerateRejectsAMissingWorkingDirectory(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the `working directory` is mandatory');

        $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker']),
            ['mail' => $this->getCommandDto('mail')],
        );
    }

    public function testGenerateRejectsAnInvalidRestartPolicy(): void
    {
        $configDto = $this->getConfigDto([
            Configuration::USER => 'worker',
            Configuration::WORKING_DIRECTORY => '/app',
            Configuration::RESTART_POLICY => 'sometimes',
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('invalid systemd restart policy `sometimes`');

        $this->systemdServiceTemplate->generate($configDto, ['mail' => $this->getCommandDto('mail')]);
    }

    public function testGenerateFallsBackToTheDefaultRestartPolicy(): void
    {
        $content = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $this->getCommandDto('mail')],
        )->getFiles()['/units/mail.service'];

        static::assertStringContainsString('Restart=' . SystemdServiceTemplate::DEFAULT_RESTART_POLICY, $content);
    }

    /* systemd refuses a unit whose ExecStart is not an absolute path, so generation must not produce one */
    public function testGenerateRejectsARelativeExecutable(): void
    {
        $commandDto = new CommandDto('mail', [
            Configuration::COMMAND => ['php', 'bin/console', 'app:consume'],
            Configuration::SETTINGS => [],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the systemd `exec start` needs an absolute executable path, got `php`');

        $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $commandDto],
        );
    }

    public function testGenerateRejectsAnEmptyCommand(): void
    {
        $commandDto = new CommandDto('mail', [
            Configuration::COMMAND => [],
            Configuration::SETTINGS => [],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the systemd `exec start` needs an absolute executable path');

        $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $commandDto],
        );
    }

    public function testGenerateSanitizesTheUnitName(): void
    {
        $files = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['a b@c' => $this->getCommandDto('a b@c')],
        )->getFiles();

        static::assertSame(['/units/a-b-c.service'], \array_keys($files));
    }

    /* `@` alone would make systemd read the unit as a template that cannot start without an instance name */
    public function testGenerateStripsTheTemplateUnitMarker(): void
    {
        $files = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['@mail' => $this->getCommandDto('@mail')],
        )->getFiles();

        static::assertSame(['/units/mail.service'], \array_keys($files));
    }

    public function testGenerateCollapsesTraversalSequencesInTheUnitName(): void
    {
        $files = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['../escape' => $this->getCommandDto('../escape')],
        )->getFiles();

        static::assertSame(['/units/escape.service'], \array_keys($files));
    }

    public function testGenerateRejectsAUnitNameThatSanitizesToNothing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the systemd service name is empty');

        $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['+++' => $this->getCommandDto('+++')],
        );
    }

    public function testGenerateRejectsTwoCommandsSanitizingToTheSameUnitName(): void
    {
        $configDto = $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('the file path is in use `/units/a-b.service`');

        $this->systemdServiceTemplate->generate($configDto, [
            'a b' => $this->getCommandDto('a b'),
            'a@b' => $this->getCommandDto('a@b'),
        ]);
    }

    /* systemd expands specifiers in every value it reads, and an unknown `%x` makes the unit refuse to start */
    public function testGenerateEscapesPercentSignsInEveryEmittedValue(): void
    {
        $configDto = new ConfigDto([
            Configuration::TEMPLATE_CLASS => SystemdServiceTemplate::class,
            Configuration::CONF_FILES_DIR => '/units',
            Configuration::LOGS_DIR => '/logs/100%done',
            Configuration::SETTINGS => [
                Configuration::NUMBER_OF_PROCESSES => 1,
                Configuration::USER => 'worker',
                Configuration::WORKING_DIRECTORY => '/srv/100%done',
                Configuration::ENVIRONMENT_FILE => '/srv/100%done/.env',
            ],
        ]);
        $commandDto = new CommandDto('mail', [
            Configuration::COMMAND => ['/usr/bin/php', 'bin/console', 'app:report', '--format=%s'],
            Configuration::SETTINGS => [],
        ]);

        $content = $this->systemdServiceTemplate->generate($configDto, ['mail' => $commandDto])
            ->getFiles()['/units/mail.service'];

        static::assertStringContainsString('WorkingDirectory=/srv/100%%done', $content);
        static::assertStringContainsString('EnvironmentFile=/srv/100%%done/.env', $content);
        static::assertStringContainsString('ExecStart=/usr/bin/php bin/console app:report --format=%%s', $content);
        static::assertStringContainsString('StandardOutput=append:/logs/100%%done/mail.log', $content);
        static::assertStringNotContainsString('100%d', $content);
    }

    /* systemd splits `ExecStart` on whitespace, so an argument carrying any would silently become several */
    public function testGenerateQuotesCommandArgumentsThatCarryWhitespace(): void
    {
        $commandDto = new CommandDto('mail', [
            Configuration::COMMAND => ['/usr/bin/php', 'bin/console', 'app:import', '--file=/tmp/my file.csv'],
            Configuration::SETTINGS => [],
        ]);

        $content = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $commandDto],
        )->getFiles()['/units/mail.service'];

        static::assertStringContainsString('ExecStart=/usr/bin/php bin/console app:import "--file=/tmp/my file.csv"', $content);
    }

    public function testGenerateEscapesQuotesAndBackslashesInsideAQuotedArgument(): void
    {
        $commandDto = new CommandDto('mail', [
            Configuration::COMMAND => ['/usr/bin/php', 'quo"te', 'back\\slash', ''],
            Configuration::SETTINGS => [],
        ]);

        $content = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $commandDto],
        )->getFiles()['/units/mail.service'];

        static::assertStringContainsString('ExecStart=/usr/bin/php "quo\\"te" "back\\\\slash" ""', $content);
    }

    public function testGenerateLeavesOrdinaryCommandArgumentsUnquoted(): void
    {
        $content = $this->systemdServiceTemplate->generate(
            $this->getConfigDto([Configuration::USER => 'worker', Configuration::WORKING_DIRECTORY => '/app']),
            ['mail' => $this->getCommandDto('mail')],
        )->getFiles()['/units/mail.service'];

        static::assertStringContainsString('ExecStart=/usr/bin/php bin/console app:consume' . \PHP_EOL, $content);
    }

    /** @param array<string, mixed> $settings */
    private function getConfigDto(array $settings): ConfigDto
    {
        return new ConfigDto([
            Configuration::TEMPLATE_CLASS => SystemdServiceTemplate::class,
            Configuration::CONF_FILES_DIR => '/units',
            Configuration::LOGS_DIR => '/logs',
            Configuration::SETTINGS => [Configuration::NUMBER_OF_PROCESSES => 1, ...$settings],
        ]);
    }

    /** @param array<string, mixed> $settings */
    private function getCommandDto(string $name, array $settings = []): CommandDto
    {
        return new CommandDto($name, [
            Configuration::COMMAND => ['/usr/bin/php', 'bin/console', 'app:consume'],
            Configuration::SETTINGS => $settings,
        ]);
    }
}
