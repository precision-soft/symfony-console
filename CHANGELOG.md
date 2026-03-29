# Changelog

All notable changes to `precision-soft/symfony-console` will be documented in this file.

## [v3.0.0] - 2026-03-28

### Added
- PHPStan level 8 static analysis with baseline
- Comprehensive test suite
- Tests for all DTOs, services, templates, traits, and exceptions
- `ConfigInterface` now extends `SettingsInterface`

### Changed
- Upgraded to Symfony 7 and PHP >=8.2
- `MemoryAndTimeLimitsTrait::stopScriptIfLimitsReached()` now throws `LimitExceededException` instead of calling `exit()`
- `ConfGenerateService::save()` uses atomic file replacement with backup/restore on failure
- `TimeLimitTrait::$startTime` is no longer `readonly`
- Pre-commit hook now runs php-cs-fixer, lint, phpstan, and phpunit (all exit on failure)
- Code style reformatted to PER-CS2.0

### Fixed
- Heartbeat logic no longer adds duplicate commands when heartbeat setting is disabled
- `MemoryService::returnBytes()` handles plain integer strings and `-1` (unlimited) correctly
- `MemoryService::convertBytesToHumanReadable()` clamps unit index to prevent out-of-bounds
- `KubernetesWorkerTemplate` validates `destinationFile` is not null before generating
- `CrontabTemplate` user priority: command-level user now correctly overrides config-level
- Trailing space removed from bytes unit in `MemoryService`

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
- `ConfGenerateService::save()` rewritten with atomic file generation (temp dir, backup, restore)
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
