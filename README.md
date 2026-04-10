# Symfony Console

[![PHP >= 8.2](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](https://www.php.net/)
[![PHPStan Level 8](https://img.shields.io/badge/phpstan-level%208-brightgreen)](https://phpstan.org/)
[![Code Style PER-CS2.0](https://img.shields.io/badge/code%20style-PER--CS2.0-blue)](https://www.php-fig.org/per/coding-style/)
[![License MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A Symfony bundle for generating configuration files for cron jobs and workers. It supports multiple output templates including crontab, Supervisor, and Kubernetes (CronJob and Worker) formats.

**You may fork and modify it as you wish**.

Any suggestions are welcomed.

## Features

- Generate crontab configuration files from Symfony bundle config
- Generate Supervisor worker configuration files
- Generate Kubernetes CronJob and Worker manifests
- Automatic heartbeat command injection for cron jobs
- Memory and time limit traits for long-running commands
- Instance-aware commands for parallel execution

## Requirements

- PHP 8.2+
- Symfony 7

## Installation

```shell
composer require precision-soft/symfony-console
```

## Commands

| Command                                         | Description                                                       |
|-------------------------------------------------|-------------------------------------------------------------------|
| `precision-soft:symfony:console:cronjob-create` | Generates cron job configuration files based on the bundle config |
| `precision-soft:symfony:console:worker-create`  | Generates worker configuration files based on the bundle config   |

## Configuration

### Cron job configuration

**precision_soft_symfony_console.yaml**

```yaml
precision_soft_symfony_console:
    cronjob:
        config:
            template_class: PrecisionSoft\Symfony\Console\Template\CrontabTemplate
            conf_files_dir: '%kernel.project_dir%/generated_conf/cron'
            logs_dir: '%kernel.logs_dir%/cron'
            settings:
                log: true
                destination_file: 'crontab'
                heartbeat: true
        commands:
            list:
                command: '%kernel.project_dir%/bin/console list'
                user: 'www-data'
                log_file_name: 'list.log'
                destination_file: 'custom-crontab'
                schedule:
                    minute: '*'
                    hour: '*'
                    day_of_month: '*'
                    month: '*'
                    day_of_week: '*'
                settings:
                    log: false
```

If **precision_soft_symfony_console.cronjob.config.settings.heartbeat** is set to `true`, a heartbeat command will automatically be added to each generated crontab file. The auto-generated heartbeat command runs `/bin/touch <logs_dir>/heartbeat.<destination_file>` every minute. You may override the heartbeat by defining a command named `heartbeat` in the commands list.

The **user** setting at config level prepends the user to each crontab command line. It can be overridden per command via the command-level `user` option. Each command also supports `log_file_name` (custom log file name, defaults to `<command-name>.log`) and `destination_file` (override the config-level destination file to generate separate crontab files per command).

### Worker configuration (Supervisor)

```yaml
precision_soft_symfony_console:
    worker:
        config:
            template_class: PrecisionSoft\Symfony\Console\Template\SupervisorTemplate
            conf_files_dir: '%kernel.project_dir%/generated_conf/worker'
            logs_dir: '%kernel.logs_dir%/worker'
            settings:
                number_of_processes: 1
                auto_start: true
                auto_restart: true
                prefix: 'app-name'
                user: 'root'
        commands:
            messenger-consume:
                command: '%kernel.project_dir%/bin/console messenger:consume async'
                settings:
                    number_of_processes: 2
```

Each command generates a separate `.conf` file for Supervisor. The `prefix`, `user`, `auto_start`, `auto_restart`, `log_file`, and `number_of_processes` are available settings with defaults (can be set at the config level and overridden per command). If `log_file` is not specified, it defaults to `<logs_dir>/<command-name>.log`.

### Kubernetes CronJob template

```yaml
precision_soft_symfony_console:
    cronjob:
        config:
            template_class: PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate
            conf_files_dir: '%kernel.project_dir%/generated_conf/k8s-cron'
            logs_dir: '%kernel.logs_dir%/cron'
            settings:
                destination_file: 'cronjobs.yaml'
        commands:
            cleanup:
                command: '%kernel.project_dir%/bin/console app:cleanup'
                schedule:
                    minute: '0'
                    hour: '3'
                    day_of_month: '*'
                    month: '*'
                    day_of_week: '*'
```

### Kubernetes Worker template

```yaml
precision_soft_symfony_console:
    worker:
        config:
            template_class: PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate
            conf_files_dir: '%kernel.project_dir%/generated_conf/k8s-worker'
            logs_dir: '%kernel.logs_dir%/worker'
            settings:
                destination_file: 'workers.yaml'
                number_of_processes: 1
        commands:
            messenger-consume:
                command: '%kernel.project_dir%/bin/console messenger:consume async'
                settings:
                    number_of_processes: 3
```

The `destination_file` setting is mandatory for both Kubernetes templates. The Kubernetes Worker template has no default. The Kubernetes CronJob template defaults to `crontab` from the cronjob config settings if not overridden per command.

## Available templates

| Template class              | Output format                              |
|-----------------------------|--------------------------------------------|
| `CrontabTemplate`           | Standard crontab file                      |
| `SupervisorTemplate`        | Supervisor `.conf` files (one per command) |
| `KubernetesCronjobTemplate` | Kubernetes CronJob manifest                |
| `KubernetesWorkerTemplate`  | Kubernetes Worker manifest                 |

## Command traits

The bundle provides traits for long-running Symfony commands.

### MemoryLimitTrait

Adds a `--memory-limit` option and monitors memory usage during execution.

```php
use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\MemoryLimitTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends AbstractCommand
{
    use MemoryLimitTrait;

    protected function configure(): void
    {
        $this->configureMemoryLimit('512M');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->initializeMemoryLimit();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->getItems() as $item) {
            $this->processItem($item);

            if (true === $this->getMemoryLimitReached()) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
```

### TimeLimitTrait

Adds a `--time-limit` option (seconds) to stop after a given runtime.

```php
use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\TimeLimitTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends AbstractCommand
{
    use TimeLimitTrait;

    protected function configure(): void
    {
        $this->configureTimeLimit(600);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->initializeTimeLimit();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->getItems() as $item) {
            $this->processItem($item);

            if (true === $this->getTimeLimitReached()) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
```

### MemoryAndTimeLimitsTrait

Combines both limits into one trait. Calls `stopScriptIfLimitsReached()` which throws `LimitExceededException` when either limit is exceeded — catch it to perform cleanup before exiting.

```php
use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\MemoryAndTimeLimitsTrait;
use PrecisionSoft\Symfony\Console\Exception\LimitExceededException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends AbstractCommand
{
    use MemoryAndTimeLimitsTrait;

    protected function configure(): void
    {
        $this->configureMemoryAndTimeLimits('512M', 600);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->initializeMemoryAndTimeLimits();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            foreach ($this->getItems() as $item) {
                $this->stopScriptIfLimitsReached();
                $this->processItem($item);
            }
        } catch (LimitExceededException $limitExceededException) {
            $this->warning($limitExceededException->getMessage());
        }

        return self::SUCCESS;
    }
}
```

### InstancesTrait

Adds `--max-instances` and `--instance-index` options for parallel execution of the same command.

```php
use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\InstancesTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends AbstractCommand
{
    use InstancesTrait;

    protected function configure(): void
    {
        $this->configureInstances();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$maxInstances, $instanceIndex] = $this->computeInstances();

        $this->writeln($this->formatMessageWithInstances('processing'));
        return self::SUCCESS;
    }
}
```

## Contracts

The bundle defines the following interfaces in the `PrecisionSoft\Symfony\Console\Contract` namespace:

| Interface           | Purpose                                                                         |
|---------------------|---------------------------------------------------------------------------------|
| `TemplateInterface` | Implemented by all templates — `generate(ConfigInterface, array): ConfFilesDto` |
| `ConfigInterface`   | Provides template class, logs dir, conf files dir, and settings                 |
| `SettingsInterface` | Provides access to the settings object via `getSettings(): SettingInterface`    |
| `SettingInterface`  | Retrieves a single setting value via `getSetting(string): ?string`              |

## Services

### MemoryService

Static utility for memory operations (`PrecisionSoft\Symfony\Console\Service\MemoryService`):

| Method                                              | Description                                                                |
|-----------------------------------------------------|----------------------------------------------------------------------------|
| `setMemoryLimitIfNotHigher(string $newLimit): void` | Raises `memory_limit` only if the new limit is higher than the current one |
| `getMemoryUsage(): string`                          | Returns current memory usage in human-readable format                      |
| `convertBytesToHumanReadable(int $bytes): string`   | Converts bytes to human-readable string (e.g. `128 MB`)                    |
| `returnBytes(string $value): int`                   | Parses a memory string (`512M`, `1G`) into bytes                           |

### AttributeService

Static utility for command metadata (`PrecisionSoft\Symfony\Console\Service\AttributeService`):

| Method                                         | Description                                                                 |
|------------------------------------------------|-----------------------------------------------------------------------------|
| `getCommandName(string $commandClass): string` | Extracts the command name from the `AsCommand` attribute of a command class |

## Exceptions

All exceptions extend `PrecisionSoft\Symfony\Console\Exception\Exception`:

| Exception                       | Thrown when                                                   |
|---------------------------------|---------------------------------------------------------------|
| `ConfGenerateException`         | Configuration file generation or write fails                  |
| `InvalidConfigurationException` | Required configuration is missing or invalid                  |
| `InvalidValueException`         | A value (e.g. memory limit) cannot be parsed                  |
| `LimitExceededException`        | Memory or time limit is exceeded (`MemoryAndTimeLimitsTrait`) |
| `SettingNotFoundException`      | A requested setting does not exist on the DTO                 |

## AbstractCommand

`PrecisionSoft\Symfony\Console\Command\AbstractCommand` extends Symfony's `Command` and provides:

- Automatic `$this->input`, `$this->output`, and `$this->style` (`SymfonyStyle`) initialization in `initialize()`
- Output helper methods via `SymfonyStyleTrait`: `writeln()`, `error()`, `info()`, `warning()`, `success()`

## For custom templates

Create a template service implementing `TemplateInterface` (`PrecisionSoft\Symfony\Console\Contract\TemplateInterface`) and add to your **services.yaml**:

```yaml
services:
    _instanceof:
        PrecisionSoft\Symfony\Console\Contract\TemplateInterface:
            tags: [ 'precision-soft.symfony.console.template' ]
```

## Troubleshooting

### Memory limit trait reports incorrect usage

`MemoryLimitTrait` reads `memory_limit` from `php.ini` via `\ini_get()`. If your environment sets `-1` (unlimited), the trait returns `false` for `getMemoryLimitReached()` — this is intentional. To enforce a limit, always pass an explicit value to `configureMemoryLimit()`.

### Generated config files have wrong permissions

`ConfFileWriter` creates files with the permissions of the running PHP process. If the generated crontab or Supervisor config needs specific ownership (e.g. `root`), adjust permissions after generation or run the command as the target user.

### Kubernetes template throws InvalidConfigurationException

Both `KubernetesCronjobTemplate` and `KubernetesWorkerTemplate` require the `destination_file` setting. Unlike `CrontabTemplate` (which defaults to `crontab`), Kubernetes templates have no default — set it explicitly in your config.

### Command traits conflict with existing setUp/tearDown

The command traits (`MemoryLimitTrait`, `TimeLimitTrait`) use `initialize()` hooks, not `setUp()`/`tearDown()`. They are safe to combine with any test base class. Call `initializeMemoryLimit()` or `initializeTimeLimit()` in your command's `initialize()` method.

## Security Considerations

### Heartbeat files

When `heartbeat` is enabled, the crontab generator adds a `/bin/touch <logs_dir>/heartbeat.<destination_file>` command that runs every minute. Ensure:

- **`logs_dir` is not web-accessible** — heartbeat files should not be reachable via HTTP
- **Directory permissions are restricted** — only the cron user and monitoring tools should have read/write access
- **Monitor heartbeat staleness** — the purpose of heartbeat files is to detect when cron stops running; alert if the file modification time exceeds your threshold (e.g. 5 minutes)

### Path traversal protection

`ConfFileWriter` validates that all generated file paths stay within the configured `conf_files_dir`. Paths containing `..` or resolving outside the destination directory are rejected with `ConfGenerateException`. Do not bypass this by symlinking the destination to a sensitive location.

### Configuration values in generated files

Command strings and settings are written as-is into generated config files (crontab, Supervisor `.conf`, Kubernetes YAML). Shell-sensitive characters in crontab are escaped via `\escapeshellarg()`, and YAML special characters are escaped in Kubernetes templates. Avoid passing untrusted user input as command strings or settings.

## Dev

The development environment uses Docker. The `./dc` script is a Docker Compose wrapper located in `.dev/`.

```shell
git clone git@github.com:precision-soft/symfony-console.git
cd symfony-console

./dc build && ./dc up -d
```
