# Symfony Console

[![ci](https://github.com/precision-soft/symfony-console/actions/workflows/ci.yml/badge.svg)](https://github.com/precision-soft/symfony-console/actions/workflows/ci.yml)
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
- Generate systemd service units, one per worker instance
- Generate Kubernetes CronJob and Worker manifests
- Automatic heartbeat command injection for cron jobs
- Memory and time limit traits for long-running commands
- Instance-aware commands for parallel execution

## Requirements

- PHP 8.2+
- Symfony 7 or 8 (Symfony 8 itself requires PHP 8.4+, so a PHP 8.2 or 8.3 install resolves Symfony 7)

## Installation

```shell
composer require precision-soft/symfony-console
```

## Commands

| Command                                          | Description                                                       |
|--------------------------------------------------|-------------------------------------------------------------------|
| `precision-soft:symfony:console:cronjob-create`  | Generates cron job configuration files based on the bundle config |
| `precision-soft:symfony:console:worker-create`   | Generates worker configuration files based on the bundle config   |
| `precision-soft:symfony:console:logs-dir-create` | Creates the configured logs directories, idempotently             |

Both generation commands accept three read-only modes, none of which writes anything:

| Option      | Behaviour                                                                              |
|-------------|----------------------------------------------------------------------------------------|
| `--dry-run` | Lists the destination paths that would be added, changed or removed, with their status |
| `--diff`    | The same list, each pending path followed by a unified diff of its content             |
| `--check`   | The same list, exiting with failure when anything is pending — a CI drift check        |

A path is `added` when it does not exist yet, `changed` when its content differs, and `removed` when it sits in `conf_files_dir` and no command declares it any more. Paths whose content already matches are not reported. `--check` and `--diff` combine, so a failing drift check can show what drifted.

A configuration that declares no file at all reports nothing: generation returns early on an empty set and never clears a destination directory, so reporting its content as `removed` would be drift no run could ever fix.

## Configuration

### Logs directories

`cronjob-create` and `worker-create` create their own logs directory, but only as a side effect of generating conf files, so a deployment that never runs them is left without the directories. `logs-dir-create` creates them on its own, and can be run at any point of a deployment.

The directories it creates are derived from **precision_soft_symfony_console.cronjob.config.logs_dir** and **precision_soft_symfony_console.worker.config.logs_dir**, so overriding either of those also moves the directory that gets created. Use the root-level **logs_dirs** node to add directories that no cron job or worker owns:

```yaml
precision_soft_symfony_console:
    logs_dirs:
        - '%kernel.logs_dir%/command'
```

Both `cronjob.config.logs_dir` and `worker.config.logs_dir` have defaults, so `%kernel.logs_dir%/cron` and `%kernel.logs_dir%/worker` are created even by an application that declares no cron job and no worker.

The resulting list is deduplicated and exposed as the `precision_soft_symfony_console.logs_dirs` container parameter. Deduplication compares the configured values before the container expands them, so `logs_dirs: ['var/log/cron']` is kept alongside `%kernel.logs_dir%/cron` even when both resolve to the same path — harmless, since the directories are created idempotently.

Entries must be strings, and this is enforced at container build time: a bare `logs_dirs: [123]` is rejected with the node named, rather than reaching the filesystem as a directory name.

`logs_dirs` does not deep-merge. When the node is declared in more than one configuration file — a bundle default and an environment override, say — the last declaration **replaces** the list instead of appending to it, so an environment can drop directories as well as add them. The two derived directories (`cronjob.config.logs_dir` and `worker.config.logs_dir`) are unaffected and always present.

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
                destination_files: ['crontab.m3']
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

If **precision_soft_symfony_console.cronjob.config.settings.heartbeat** is set to `true`, a heartbeat command will automatically be added to each generated crontab file. The auto-generated heartbeat command runs `/bin/touch <logs_dir>/heartbeat.<destination_file>` every minute, where `<destination_file>` is the destination path with its `/` separators replaced by `.`, so two files with the same base name in different sub directories keep distinct heartbeat files. You may override the heartbeat by defining a command named `heartbeat` in the commands list.

An overridden heartbeat is emitted into every generated crontab file, and the same command string reaches all of them. Where the heartbeat has to know *which* file it is proving alive — a heartbeat that records the file in a shared database rather than touching a local one — put the `{destination_file}` placeholder (`CrontabTemplate::DESTINATION_FILE_PLACEHOLDER`) in the command or in `log_file_name`; it is replaced with the path of the file being generated relative to `conf_files_dir`, flattened by replacing `/` with `.` (`machine-a/crontab` becomes `machine-a.crontab`), so the value is unique per file and safe to use as a log file name or as an argument. The placeholder is substituted in the heartbeat command only, and an override that does not contain it is emitted byte for byte as before:

```yaml
precision_soft_symfony_console:
    cronjob:
        commands:
            heartbeat:
                command: '%kernel.project_dir%/bin/console app:heartbeat {destination_file}'
                log_file_name: 'heartbeat.{destination_file}.log'
                schedule:
                    minute: '*'
```

**destination_files** lists crontab files that must be generated even when no command targets them. A file only comes into existence because some command names it in `destination_file`, so a machine that runs nothing but the heartbeat has no crontab at all — this node is how it gets one. Declared files are added to the ones the commands already produce, never instead of them, and the default `destination_file` is still materialised for a configuration whose only command is the heartbeat. They are generated unconditionally, so with `heartbeat` off a declared file that no command targets is written with no cron rows in it.

A command named `heartbeat` is always taken as the heartbeat override rather than as an ordinary cron row. When `settings.heartbeat` is `false` there is no heartbeat to override, so that command is not emitted into any file — turning the heartbeat off removes the override along with it.

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

#### Splitting worker files into sub directories

By default every command lands directly in `conf_files_dir` as `<command-name>.conf`. Use `destination_sub_dir` and `destination_suffix` to spread the generated files across sub directories or to disambiguate their file names — useful when a single application config describes workers for several machines, and each machine's Supervisor only includes its own sub directory:

```yaml
precision_soft_symfony_console:
    worker:
        config:
            conf_files_dir: '/etc/supervisor/conf.d'
            settings:
                prefix: 'app-name'
                user: 'root'
                destination_sub_dir: 'machine-a'
        commands:
            inherits:
                command: '%kernel.project_dir%/bin/console app:one'
            overrides:
                command: '%kernel.project_dir%/bin/console app:two'
                destination_sub_dir: 'machine-b/eu-west'
            opts_out:
                command: '%kernel.project_dir%/bin/console app:three'
                destination_sub_dir: ''
            suffixed:
                command: '%kernel.project_dir%/bin/console app:four'
                destination_suffix: 'blue'
```

Generates:

```
/etc/supervisor/conf.d/machine-a/inherits.conf
/etc/supervisor/conf.d/machine-b/eu-west/overrides.conf
/etc/supervisor/conf.d/opts_out.conf
/etc/supervisor/conf.d/machine-a/suffixed.blue.conf
```

Both options are resolved per command first and fall back to the config-level `settings` value. Setting either to an empty string at command level opts that command out of the config-level value. `destination_sub_dir` is always relative to `conf_files_dir` and is collapsed to its meaningful segments, so leading, trailing and repeated `/` as well as `.` segments are dropped; `destination_suffix` is inserted before the `.conf` extension with surrounding dots stripped. Values containing `..` or a backslash are rejected at container build time, and `destination_suffix` additionally rejects `/`.

That validation runs on the configuration as written, so a value supplied through a container parameter (`destination_sub_dir: '%app.machine%'`) is still a literal placeholder at that point and cannot be checked. `ConfFileWriter` remains the backstop — it rejects any generated path that escapes `conf_files_dir`, whatever produced it.

Both options are honoured only by `SupervisorTemplate`. `KubernetesWorkerTemplate` writes a single manifest whose name comes from `destination_file`, and ignores them.

### Worker configuration (systemd)

```yaml
precision_soft_symfony_console:
    worker:
        config:
            template_class: PrecisionSoft\Symfony\Console\Template\SystemdServiceTemplate
            conf_files_dir: '%kernel.project_dir%/generated_conf/systemd'
            logs_dir: '%kernel.logs_dir%/worker'
            settings:
                number_of_processes: 2
                prefix: 'app-name'
                user: 'www-data'
                working_directory: '%kernel.project_dir%'
                environment_file: '%kernel.project_dir%/.env.local'
                restart_policy: 'always'
        commands:
            messenger-consume:
                command: [ '/usr/bin/php', '%kernel.project_dir%/bin/console', 'messenger:consume', 'async' ]
                settings:
                    number_of_processes: 4
                    restart_policy: 'on-failure'
```

Generates one concrete unit per instance, so nothing has to be templated at install time:

```
generated_conf/systemd/app-name-messenger_consume-1.service
generated_conf/systemd/app-name-messenger_consume-2.service
generated_conf/systemd/app-name-messenger_consume-3.service
generated_conf/systemd/app-name-messenger_consume-4.service
```

A `number_of_processes` of `1` drops the numeric suffix and writes `app-name-messenger_consume.service`. The `-` of the command key became `_`: Symfony normalizes the keys of a configuration array, so a command declared as `messenger-consume` reaches every template as `messenger_consume`.

Settings, each resolved per command first and falling back to the config level:

| Setting             | Default                         | Emitted as                           |
|---------------------|---------------------------------|--------------------------------------|
| `user`              | none, mandatory                 | `User=`                              |
| `working_directory` | `%kernel.project_dir%`          | `WorkingDirectory=`                  |
| `restart_policy`    | `always`                        | `Restart=`                           |
| `environment_file`  | none, line omitted when unset   | `EnvironmentFile=`                   |
| `standard_output`   | `append:<log_file>`             | `StandardOutput=`                    |
| `standard_error`    | `append:<log_file>`             | `StandardError=`                     |
| `log_file`          | `<logs_dir>/<command-name>.log` | the `append:` target of both streams |

`restart_policy` accepts the systemd values `no`, `on-success`, `on-failure`, `on-abnormal`, `on-watchdog`, `on-abort` and `always`, validated at container build time. `prefix` is optional here, unlike for Supervisor, and the unit name is `<prefix>-<command-name>` sanitized down to what systemd accepts: everything outside `A-Za-z0-9_.-` collapses to `-`, `..` collapses to `.`, and surrounding `-` and `.` are stripped. `@` is deliberately not preserved, because a unit named `foo@.service` is a template systemd refuses to start without an instance name. A name that sanitizes down to nothing is rejected, and so are two commands whose names sanitize to the same unit.

`command` must start with an absolute executable path — systemd rejects a unit whose `ExecStart` is not absolute, so generation fails loudly instead of writing a unit that cannot start. Use `/usr/bin/php` rather than `php`.

Two systemd syntax rules are handled for you, because both fail silently or fatally at `systemctl start` rather than at generation time:

- **Command arguments are quoted when they need it.** systemd splits `ExecStart` on whitespace, so `--file=/tmp/my file.csv` would arrive as two arguments. Any part carrying whitespace, a quote or a backslash is emitted double-quoted with the inner characters escaped; ordinary parts stay bare.
- **A literal `%` is emitted as `%%`.** systemd expands specifiers in every value it reads, and an unknown one (`--format=%Q`) is a fatal error that keeps the unit from starting, while a known one (`--format=%s`) is silently substituted. `working_directory`, `environment_file`, `standard_output`, `standard_error` and the command parts are all escaped. The consequence is that systemd's own specifiers cannot be passed through — write the resolved value instead.

`standard_output` and `standard_error` are emitted verbatim, so they must be a form systemd accepts: `inherit`, `null`, `tty`, `journal`, `kmsg`, `socket`, or a `file:`, `append:`, `truncate:` or `fd:` prefix. They are not validated at container build time, since the accepted set grows with systemd versions.

`destination_sub_dir` and `destination_suffix` work exactly as they do for Supervisor, the suffix landing before the `.service` extension.

> **Do not point `conf_files_dir` at `/etc/systemd/system`.** Generation is atomic at directory level: the whole destination directory is replaced, so every unrelated unit in it would be deleted. Generate into a directory this bundle owns, then copy or symlink the units from there and run `systemctl daemon-reload`.

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

Both templates write a values file whose collection is a **YAML sequence**:

```yaml
CronJobs:
    jobs:
        -
            name: cleanup
            command: '/app/bin/console app:cleanup'
            schedule: '0 3 * * *'
```

Up to and including v4.4.0 the collection was emitted as a mapping keyed `0:`, `1:` instead. Both forms parse into the same structure in most consumers, but they do not merge the same way — Helm merges two values files key by key for a mapping and replaces the whole value for a sequence. A chart that indexes an entry directly (`.Values.CronJobs.jobs.0`) must be updated to use `index` or a `range`.

## Available templates

| Template class              | Output format                               |
|-----------------------------|---------------------------------------------|
| `CrontabTemplate`           | Standard crontab file                       |
| `SupervisorTemplate`        | Supervisor `.conf` files (one per command)  |
| `SystemdServiceTemplate`    | systemd `.service` units (one per instance) |
| `KubernetesCronjobTemplate` | Kubernetes CronJob manifest                 |
| `KubernetesWorkerTemplate`  | Kubernetes Worker manifest                  |

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

| Interface            | Purpose                                                                                                                                                                                             |
|----------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TemplateInterface`  | Implemented by all templates — `generate(ConfigInterface, array): ConfFilesDto`                                                                                                                     |
| `ConfigInterface`    | Provides template class, logs dir, conf files dir, and settings                                                                                                                                     |
| `SettingsInterface`  | Provides access to the settings object via `getSettings(): SettingInterface`                                                                                                                        |
| `SettingInterface`   | Retrieves a single setting value via `getSetting(string): ?string` — boolean values are returned as the literal strings `'true'` / `'false'`, `null` stays `null`, other scalars are cast to string |
| `ExceptionInterface` | Implemented by every exception in the bundle — `getContext(): array` and `setContext(?array): static`                                                                                               |

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

### Exception context

Every exception carries a structured `context` array next to its message, so the facts describing a failure do not have to be parsed back out of a string:

```php
try {
    $confGenerateService->generate($configDto, $commands);
} catch (ConfGenerateException $confGenerateException) {
    $logger->error($confGenerateException->getMessage(), $confGenerateException->getContext());
}
```

`getContext()` returns `[]` when nothing was attached. Context values are expected to be scalars — `SymfonyStyleTrait::formatThrowable()` renders them as JSON. The bundle attaches `templateClass` and `confFilesDir` when a template fails, `logsDir` when a logs directory cannot be created, and `destinationDir`, `backupDirectory` and `backupRestored` when an activation failure leaves a backup behind — that last one being the case where an operator most needs to know where the previous configuration went.

`ConfGenerateException::from($throwable, $context)` re-wraps a throwable while keeping it as the previous one. It is declared on `ConfGenerateException` rather than on the shared base class because `SettingNotFoundException` takes a different constructor signature, which the `new static()` inside `from()` could not honour.

`Error` and its subclasses are deliberately never wrapped: a `TypeError` raised inside a custom template is a bug in that template, and turning it into a `ConfGenerateException` would send the operator looking for a bad configuration value that does not exist. Exceptions thrown by a custom template *are* wrapped, so the `@throws ConfGenerateException` on `generate()` still holds for anything a caller is expected to handle.

When a command prints a throwable it prints the whole `previous` chain, each link as `class::file::line` plus its context, joined by ` <- `:

```
[ERROR] generate failed / ConfGenerateException::…/ConfFileWriter.php:66::{"destinationDir":"/etc/supervisor/conf.d"} <- TypeError::…/MyTemplate.php:97
```

## AbstractCommand

`PrecisionSoft\Symfony\Console\Command\AbstractCommand` extends Symfony's `Command` and provides:

- Automatic `$this->input`, `$this->output`, and `$this->style` (`SymfonyStyle`) initialization in `initialize()`
- Output helper methods via `SymfonyStyleTrait`: `writeln()`, `error()`, `info()`, `warning()`, `success()`
- The decorated title block is emitted only when the output is decorated (TTY) and verbosity is above quiet — piped / redirected / `-q` invocations stay clean for machine consumers

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

`destination_file`, `destination_files`, `destination_sub_dir` and `destination_suffix` are the four configuration values appended to `conf_files_dir` to form a generated path, and all four reject `..` and backslashes at container build time, naming the offending node. `destination_file` and `destination_files` additionally reject a value built only from `.` and `/` segments, which would otherwise normalize away to nothing and resolve to `conf_files_dir` itself. That check runs on the configuration as written, so a value supplied through a container parameter is still a literal placeholder at that point and cannot be checked — which is why the writer remains the backstop.

`conf_files_dir` itself must be a real directory. Activation renames the destination path, so a symlink there would be replaced by a real directory rather than written through — silently destroying the link. Both generation and the read-only preview modes refuse it with `ConfGenerateException`, so `--check` cannot pass what a write would break.

`ConfFileWriter` validates that all generated file paths stay within the configured `conf_files_dir`. Paths containing `..` or resolving outside the destination directory are rejected with `ConfGenerateException`. Each written file is additionally canonicalized via `realpath` and re-checked against the (also canonicalized) temporary directory, blocking symlink-based escapes that pass textual checks; the temp directory itself is verified to be a real directory (not a pre-existing symlink) to close a TOCTOU window after `mkdir`. Do not bypass these checks by symlinking the destination to a sensitive location.

### Configuration values in generated files

Command parts (the `command` array) are rendered verbatim into generated config files (crontab, Supervisor `.conf`, Kubernetes YAML). The templates do not shell-escape command parts, so sanitizing command input (shell metacharacters, newlines) is the caller's responsibility. Kubernetes manifests are written by `Symfony\Component\Yaml\Yaml::dump()`, which quotes whatever needs quoting, and log file paths in crontab are escaped via `\escapeshellarg()`. Do not pass untrusted user input as command strings or settings.

**A `;` in a Supervisor command is a known limitation.** `SupervisorTemplate` writes `command = <parts>` verbatim, and `;` opens an inline comment in the INI dialect — so a command such as `sh -c 'a; b'` produces a file whose `command` directive reads back as `sh -c a`, silently dropping the rest. The bundle neither quotes nor rejects it; keep `;` out of the command parts, or wrap the composite command in a script and point the worker at that instead.

## Dev

The development environment uses Docker. The `./dc` script is a Docker Compose wrapper located in `.dev/`.

```shell
git clone git@github.com:precision-soft/symfony-console.git
cd symfony-console

./dc build && ./dc up -d
```

Run the full gate the way the pre-commit hook runs it - the CI workflow in
`.github/workflows/ci.yml` calls the same composer scripts, so the two cannot drift:

```shell
.dev/validate/all.sh
.dev/validate/all.sh --audit    # also audits the locked dependencies ( needs the network )
.dev/validate/all.sh --staged   # what the pre-commit hook runs: nothing unless the index carries php
```

Mutation testing is opt-in for the same reason, plus cost - it runs the suite once per mutant:

```shell
.dev/validate/all.sh --mutation
```

Infection is a pinned phar in the image, not a composer dependency, and `infection.json5` carries a
`minMsi` floor equal to the last measured score, so the section fails when a change makes the suite weaker rather than only reporting a number. Raise the floor when the score improves.

Build against another PHP version with the `PHP_VERSION` build argument - each version is tagged as its own image, so switching back and forth costs nothing:

```shell
PHP_VERSION=8.4 ./dc build && PHP_VERSION=8.4 ./dc up -d
```

Coverage is available through pcov, which is installed but disabled by default:

```shell
./dc exec dev php -d pcov.enabled=1 vendor/bin/simple-phpunit --coverage-text
```

After editing a file, `./dc restart dev` (a few seconds) is enough to be sure the container is not serving a stale copy - the bind mount can keep the old inode after an atomic rewrite.
