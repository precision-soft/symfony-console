# Symfony Console — example

The operations of a product catalogue — the cron jobs that keep its prices current, the worker that imports its price list, and the crontab, Supervisor, systemd and Kubernetes configuration those need on every platform — whose test suite shows every public capability of `precision-soft/symfony-console` on code that does something real. It is the minimum of code that demonstrates the maximum of the library: two commands built on `AbstractCommand` and its traits, one bundle configuration, and the generated files asserted the way a deployment pipeline would read them.

Paths in this file are relative to `.example/`.

## What it represents

- `src/CatalogueKernel.php` — a micro-kernel registering `PrecisionSoftSymfonyConsoleBundle`; the environment selects the templates (`test` = crontab + Supervisor, `systemd` = crontab + systemd units, `kubernetes` = the two values files) by importing `config/<environment>/`.
- `config/precision_soft_symfony_console.yaml` — the operations themselves: the logs directories, the two scheduled commands with their user, log and heartbeat, the declared `catalogue.reports` file, and the import worker with two processes.
- `src/Command/PriceListImportCommand.php` — the worker: `InstancesTrait` gives every instance its own shard of the catalogue, `MemoryAndTimeLimitsTrait` stops it cleanly at `--memory-limit` / `--time-limit`.
- `src/Command/ExchangeRateRefreshCommand.php` — the cron job: `TimeLimitTrait`, an optional argument list, and the project's own exception (`Exception\UnknownCurrencyException` extends the bundle's, so it carries a context) reported through `error()`.
- `src/Catalogue/` — the nomenclator: a `Product`, the `ProductRepository` and the `ExchangeRateProvider` the commands work on.

## What each test shows

| Test file                                          | Library capability demonstrated                                                                                                                                                                                                                                 |
|----------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `tests/Functional/CronjobConfigurationTest.php`    | `logs-dir-create` creating the logs directories idempotently; `cronjob-create` writing the crontab rows with the user, the log redirection (`log_file_name`), the heartbeat, and a declared file that holds only the heartbeat                                  |
| `tests/Functional/SupervisorConfigurationTest.php` | `worker-create` writing a Supervisor program with the prefix, the user, `numprocs` and the log file resolved from the config level                                                                                                                              |
| `tests/Functional/SystemdConfigurationTest.php`    | one systemd unit per instance with `WorkingDirectory`, an optional `EnvironmentFile`, the restart policy and the appended log; the units run through `systemd-analyze verify` wherever it is on the `PATH`, and are exported through `SYSTEMD_UNITS_EXPORT_DIR` |
| `tests/Functional/KubernetesConfigurationTest.php` | the CronJob and worker values files, parsed back as YAML sequences, with the schedule and the parallelism                                                                                                                                                       |
| `tests/Functional/PreviewModesTest.php`            | `--check` before and after a generation, `--dry-run` writing nothing, `--diff` showing a manual edit and the content of a file no command declares any more                                                                                                     |
| `tests/Command/PriceListImportCommandTest.php`     | `InstancesTrait` sharding by `--max-instances` / `--instance-index` and rejecting an invalid pair, `MemoryAndTimeLimitsTrait` stopping at the memory limit and rejecting a non-positive time limit; the repository mocked through `AbstractTestCase`            |
| `tests/Command/ExchangeRateRefreshCommandTest.php` | the repository from `getMockDto()` and the provider registered at run time with `registerMockDto()`; the exception context printed after the exception chain by `error()`                                                                                       |

Three things worth knowing before writing a scenario of your own: the kernel boots without debug and with its cache removed before every test, because a debug kernel dumps `config/reference.php` next to the tracked configuration while a cached one would ignore an edited configuration; Symfony normalizes the keys of the `commands` map, so `price-list-import` reaches the crontab, Supervisor and systemd templates as `price_list_import` (its log file, its program and its unit are named after that) while the Kubernetes templates sanitize the name back to `price-list-import`; and the systemd executable is `/usr/bin/env`, because `systemd-analyze verify` checks that the executable exists on the machine that verifies, and the machine that has systemd is not always the one that has php.

## How to run

The example installs the library from the working tree through a path repository, so it always tests the code as it stands. Its `composer.lock` is not committed: a fresh install resolves the dependencies at that moment, and the root's `composer.lock` stays the reproducible set.

```shell
cd .example
composer install
composer check    # phpstan (with the house rules), then the suite
```

From the repository root the same runs as one section of the gate, inside the dev container:

```shell
.dev/validate/all.sh --example
```

To verify the systemd units on a host that has systemd but no php:

```shell
SYSTEMD_UNITS_EXPORT_DIR=/var/www/html/.dev-data/units ./dc exec -T dev bash -c 'cd .example && composer test'
systemd-analyze verify --man=no --generators=no .dev-data/units/*.service
```

Code style is governed by the root's `.php-cs-fixer.dist.php`, which includes this directory, so `composer cs-check` at the root covers the example as well. The directory is `export-ignore`d and never reaches a consumer's `vendor/`.
