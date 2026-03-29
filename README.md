# Symfony Console

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
            template_class: \PrecisionSoft\Symfony\Console\Template\CrontabTemplate
            conf_files_dir: '%kernel.project_dir%/generated_conf/cron'
            logs_dir: '%kernel.logs_dir%/cron'
            settings:
                log: true
                destination_file: 'crontab'
                heartbeat: true
        commands:
            list:
                command: '%kernel.project_dir%/bin/console list'
                schedule:
                    minute: '*'
                    hour: '*'
                    day_of_month: '*'
                    month: '*'
                    day_of_week: '*'
                settings:
                    log: false
```

If **precision_soft_symfony_console.cronjob.config.settings.heartbeat** is set to `true`, a heartbeat command will automatically be added to each generated crontab file. You may override the heartbeat by defining a command named `heartbeat` in the commands list.

### Worker configuration (Supervisor)

```yaml
precision_soft_symfony_console:
    worker:
        config:
            template_class: \PrecisionSoft\Symfony\Console\Template\SupervisorTemplate
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

Each command generates a separate `.conf` file for Supervisor. The `prefix`, `user`, `auto_start`, `auto_restart`, and `log_file` are available settings with defaults (can be set at the config level and overridden per command).

### Kubernetes CronJob template

```yaml
precision_soft_symfony_console:
    cronjob:
        config:
            template_class: \PrecisionSoft\Symfony\Console\Template\KubernetesCronjobTemplate
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
            template_class: \PrecisionSoft\Symfony\Console\Template\KubernetesWorkerTemplate
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

The `destination_file` setting is mandatory for the Kubernetes Worker template.

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

            if (true === $this->isMemoryLimitReached()) {
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

            if (true === $this->isTimeLimitReached()) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
```

### MemoryAndTimeLimitsTrait

Combines both limits into one trait. Throws `LimitExceededException` when either limit is exceeded.

```php
use PrecisionSoft\Symfony\Console\Command\AbstractCommand;
use PrecisionSoft\Symfony\Console\Command\Trait\MemoryAndTimeLimitsTrait;
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
        foreach ($this->getItems() as $item) {
            $this->stopScriptIfLimitsReached();
            $this->processItem($item);
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

## For custom templates

Create a template service implementing `TemplateInterface` (`PrecisionSoft\Symfony\Console\Contract\TemplateInterface`) and add to your **services.yaml**:

```yaml
services:
    _instanceof:
        PrecisionSoft\Symfony\Console\Contract\TemplateInterface:
            tags: [ 'precision-soft.symfony.console.template' ]
```

## Dev

The development environment uses Docker. The `./dc` script is a Docker Compose wrapper located in `.dev/`.

```shell
git clone git@github.com:precision-soft/symfony-console.git
cd symfony-console

./dc build && ./dc up -d
```
