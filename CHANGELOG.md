# Changelog

All notable changes to `precision-soft/symfony-console` will be documented in this file.

## [v3.0.1] - 2026-03-30

### Fixed

- `MemoryLimitTrait::getMemoryLimitReached()` — returns `false` when memory limit is `-1` (unlimited) instead of comparing usage against `-1` bytes
- `KubernetesCronjobTemplate::generate()` — added null check on `destinationFile` consistent with `KubernetesWorkerTemplate`
- `phpstan-baseline.neon` — added entry for defensive null check on `KubernetesCronjobTemplate` (`identical.alwaysFalse`)

## [v3.0.0] - 2026-03-30

### Breaking Changes

- **`ConfigInterface` now extends `SettingsInterface`** — all implementations must add a `getSettings(): SettingInterface` method
- **`MemoryAndTimeLimitsTrait::stopScriptIfLimitsReached()` now throws `LimitExceededException`** instead of calling `exit(Command::INVALID)` — callers must catch this exception
- **`MemoryAndTimeLimitsTrait::didScriptReachedLimits()` renamed to `getScriptReachedLimits()`** — naming convention compliance, update all call sites
- **`MemoryLimitTrait::isMemoryLimitReached()` renamed to `getMemoryLimitReached()`** — naming convention compliance
- **`TimeLimitTrait::isTimeLimitReached()` renamed to `getTimeLimitReached()`** — naming convention compliance
- **`CronjobCreateCommand` and `WorkerCreateCommand` now extend `AbstractCreateConfigCommand`** instead of `AbstractCommand` — constructor signatures changed; `execute()` logic moved to parent
- **`CronjobCreateCommand` catches `ConfGenerateException` only** instead of generic `Throwable` — unexpected exceptions now propagate
- **`WorkerCreateCommand` catches `ConfGenerateException` only** instead of generic `Throwable` — unexpected exceptions now propagate
- **Removed `version` field from `composer.json`** — version is now derived from git tags only
- **Replaced `squizlabs/php_codesniffer` with `phpstan/phpstan`** in dev dependencies
- **Upgraded `precision-soft/symfony-phpunit` from `1.*` to `^2.0`**
- **Symfony dependency constraints changed from `7.*` to `^7.0`** (stricter semver)
- **Renamed `phpunit.xml` to `phpunit.xml.dist`** — local overrides via `phpunit.xml` are now gitignored

### Added

- `AbstractCreateConfigCommand` base class — extracts shared `execute()` logic from `CronjobCreateCommand` and `WorkerCreateCommand`
- `ConfGenerateException` — dedicated exception for config generation failures
- `LimitExceededException` — dedicated exception for memory/time limit violations
- `SettingsInterface` contract with `getSettings(): SettingInterface`
- PHPStan level 8 static analysis with baseline (`phpstan.neon`, `phpstan-baseline.neon`)
- Comprehensive test suite (225 tests, 503 assertions) covering all DTOs, services, templates, traits, commands, and exceptions
- `ConfFileWriter::save()` — atomic file replacement with temp dir, backup, and restore on failure; path traversal protection
- `ConfFilesDto` — validates path uniqueness on `addFile()`
- `SettingsTrait::getSetting()` — validates property existence via `\property_exists()` before access
- `Cronjob\CommandDto` — validates that the `schedule` key is present
- `MemoryService::returnBytes()` — rejects zero and negative values (except `-1` for unlimited)
- `@throws` annotations on `AttributeService::getCommandName()`, `ConfFileWriter::save()`, `ConfGenerateService::generate()`, and `MemoryService::returnBytes()`
- Pre-commit hook now runs php-cs-fixer, PHP lint, PHPStan, and PHPUnit (all exit on failure)

### Changed

- Code style reformatted to PER-CS2.0 (replaced `phpcs.xml` with `.php-cs-fixer` configuration)
- `TimeLimitTrait::$startTime` is no longer `readonly` (allows re-initialization)
- `Configuration` DI tree builder — hardened with explicit type checks and non-nullable defaults
- `PrecisionSoftSymfonyConsoleExtension` — uses explicit comparison for empty config checks
- Dev infrastructure reorganized: Docker setup uses `entrypoint.sh` instead of `setup.sh`
- Composer hook script properly quotes `$COMPOSER_DEV_MODE` variable
- `composer.json` description and keywords expanded for Packagist discoverability
- README `MemoryAndTimeLimitsTrait` example now shows `LimitExceededException` try-catch pattern
- README — documented heartbeat touch file path, `number_of_processes` and `log_file` defaults for Supervisor, and `destination_file` requirements for Kubernetes templates
- Error path tests use `Configuration::*` constants instead of string literals
- `KubernetesJobTraitTest` and `WorkerNumberOfProcessesTraitTest` switched from `AbstractTestCase` to `TestCase` + `MockeryPHPUnitIntegration` (no mock DTOs needed)
- `ConfGenerateServiceTest` temp directory cleanup wrapped in `try/finally` for reliability

### Fixed

- `CrontabTemplate` — heartbeat logic no longer adds duplicate commands when heartbeat setting is disabled
- `CrontabTemplate` — command-level `user` now correctly overrides config-level user
- `MemoryService::returnBytes()` — handles plain integer strings and `-1` (unlimited) correctly
- `MemoryService::convertBytesToHumanReadable()` — clamps unit index to prevent out-of-bounds array access
- `KubernetesWorkerTemplate` — validates `destinationFile` is not null before generating
- Trailing space removed from bytes unit in `MemoryService::convertBytesToHumanReadable()`
- **`services.php` DI argument name mismatch** — `$config` did not match constructor parameters `$cronjobConfiguration`/`$workerConfiguration`, commands never received configuration via the service container
- **`ConfFileWriter` false failure on backup cleanup** — a failed backup removal after successful deploy no longer masks the success as a failure

## [v2.3.7] - 2026-03-21

### Fixed

- Trailing space removed from bytes unit in `MemoryService::convertBytesToHumanReadable()`

## [v2.3.6] - 2026-03-20

### Fixed

- Corrected README clone URL

## [v2.3.5] - 2026-03-19

### Fixed

- `MemoryService::convertBytesToHumanReadable()` clamps unit index to prevent overflow

## [v2.3.4] - 2026-03-19

### Changed

- `ConfFileWriter::save()` rewritten with atomic file generation (temp dir, backup, restore)
- Replaced `empty()` with explicit comparisons throughout codebase

## [v2.3.3] - 2026-03-19

### Changed

- Moved dev scripts to `.dev/` directory
- Updated dev dependencies

## [v2.3.2] - 2026-03-19

### Fixed

- Type correctness and code style alignment across codebase

## [v2.3.1] - 2026-03-13

### Changed

- Normalized source code style

## [v2.3.0] - 2025-11-03

### Added

- `MemoryLimitTrait` for memory limit enforcement in long-running commands
- `TimeLimitTrait` for time limit enforcement in long-running commands
- `MemoryAndTimeLimitsTrait` combining both limits

## [v2.2.1] - 2025-10-25

### Fixed

- Null deprecation warnings

## [v2.2.0] - 2025-01-06

### Added

- `SupervisorSettingsTrait::getLogFile()` for custom log file paths in Supervisor config

## [v2.1.0] - 2024-12-13

### Added

- `CrontabTemplate::getHeartbeatCommand()` — allows overriding the default heartbeat command

## [v2.0.1] - 2024-12-13

### Fixed

- Heartbeat command now added to all generated crontab files (not just the first)

## [v2.0.0] - 2024-12-11

### Added

- Multiple cronjob file generation (one command can target a specific `destination_file`)

### Changed

- **Breaking:** Modified visibility of several template methods from `private` to `protected`

## [v1.2.1] - 2024-12-09

### Fixed

- `InstancesTrait` validation for max instances and instance index

## [v1.2.0] - 2024-11-05

### Added

- `InstancesTrait::formatMessageWithInstances()` for prefixing output with `[index/max]`

## [v1.1.0] - 2024-10-04

### Added

- `user` and `log_file_name` options for cronjob commands
- `AttributeService` for extracting Symfony command names from `#[AsCommand]` attributes

## [v1.0.0] - 2024-09-19

### Added

- Initial release
- `CronjobCreateCommand` and `WorkerCreateCommand` Symfony console commands
- `CrontabTemplate` for generating standard crontab files
- `SupervisorTemplate` for generating Supervisor `.conf` files
- `KubernetesCronjobTemplate` for Kubernetes CronJob manifests
- `KubernetesWorkerTemplate` for Kubernetes Worker manifests
- Symfony DI configuration with `precision_soft_symfony_console` config tree
- `TemplateInterface` contract for custom template implementations
- `ConfGenerateService` for orchestrating template generation and file output
- `MemoryService` for memory usage monitoring and byte conversion
- `SymfonyStyle` wrapper with timestamp and memory usage formatting
- `InstancesTrait` for parallel execution with `--max-instances` and `--instance-index` options

[v3.0.1]: https://github.com/precision-soft/symfony-console/compare/v3.0.0...v3.0.1

[v3.0.0]: https://github.com/precision-soft/symfony-console/compare/v2.3.7...v3.0.0

[v2.3.7]: https://github.com/precision-soft/symfony-console/compare/v2.3.6...v2.3.7

[v2.3.6]: https://github.com/precision-soft/symfony-console/compare/v2.3.5...v2.3.6

[v2.3.5]: https://github.com/precision-soft/symfony-console/compare/v2.3.4...v2.3.5

[v2.3.4]: https://github.com/precision-soft/symfony-console/compare/v2.3.3...v2.3.4

[v2.3.3]: https://github.com/precision-soft/symfony-console/compare/v2.3.2...v2.3.3

[v2.3.2]: https://github.com/precision-soft/symfony-console/compare/v2.3.1...v2.3.2

[v2.3.1]: https://github.com/precision-soft/symfony-console/compare/v2.3.0...v2.3.1

[v2.3.0]: https://github.com/precision-soft/symfony-console/compare/v2.2.1...v2.3.0

[v2.2.1]: https://github.com/precision-soft/symfony-console/compare/v2.2.0...v2.2.1

[v2.2.0]: https://github.com/precision-soft/symfony-console/compare/v2.1.0...v2.2.0

[v2.1.0]: https://github.com/precision-soft/symfony-console/compare/v2.0.1...v2.1.0

[v2.0.1]: https://github.com/precision-soft/symfony-console/compare/v2.0.0...v2.0.1

[v2.0.0]: https://github.com/precision-soft/symfony-console/compare/v1.2.1...v2.0.0

[v1.2.1]: https://github.com/precision-soft/symfony-console/compare/v1.2.0...v1.2.1

[v1.2.0]: https://github.com/precision-soft/symfony-console/compare/v1.1.0...v1.2.0

[v1.1.0]: https://github.com/precision-soft/symfony-console/compare/v1.0.0...v1.1.0

[v1.0.0]: https://github.com/precision-soft/symfony-console/releases/tag/v1.0.0
