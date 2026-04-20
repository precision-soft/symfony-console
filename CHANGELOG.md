# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v4.2.5] - 2026-04-20 - Late static binding, naming consistency, extensibility pass, and template polish

### Changed

- `CronjobCreateCommand::__construct()` — pass `static::NAME` to `parent::__construct()` instead of `self::NAME`, so subclasses that override the `NAME` constant register under their own command name rather than silently inheriting `precision-soft:symfony:console:cronjob-create`
- `WorkerCreateCommand::__construct()` — same `self::NAME` → `static::NAME` switch; subclass-declared `NAME` constants now propagate through the parent constructor
- `AbstractCreateConfigCommand::__construct()` — promoted properties `$confGenerateService`, `$configInterface`, `$commands` widened from `private readonly` to `protected readonly`, continuing the v4.2.0 library-extensibility pass so subclasses can override `execute()` without losing access to the injected collaborators
- `ConfGenerateService::$templates` — visibility widened from `private` to `protected`, consistent with the v4.2.0 library-extensibility pass (`ConfFileWriter`, `Configuration`, `SettingsTrait`, etc.); subclasses can now introspect or replace the tagged-template registry without re-declaring the property
- `ConfGenerateService::$templates` — PHPDoc tightened from `TemplateInterface[]` to `array<class-string<TemplateInterface>, TemplateInterface>` so static analysis knows the array is class-keyed (the constructor uses `$templateInterface::class` as the key)
- `ConfGenerateService::$confFileWriter` — `private readonly` → `protected readonly` so subclasses can reach the writer directly instead of only through `generate()`
- `ConfFileWriter::$filesystem` — `private readonly` → `protected readonly` so subclasses overriding `writeTemporaryFiles()`, `activateDirectory()`, `tryRestoreBackup()`, or `silentRemove()` retain access to the `Filesystem` collaborator those hooks already use
- `CronjobDto::$config` / `$commands` — `private` → `protected` so subclasses can inspect or augment the parsed configuration and command map without re-parsing the raw array
- `WorkerDto::$config` / `$commands` — same `private` → `protected` widening, matching the Cronjob sibling
- `CronjobDto::getCommands()` / `WorkerDto::getCommands()` — PHPDoc return type tightened from `CommandDto[]` to `array<string, CommandDto>`, matching the already-declared `@var array<string, CommandDto>` on the backing property (keyed by command name)
- `Cronjob\ConfigDto::$settings` / `Worker\ConfigDto::$settings` — `private` → `protected`
- `ConfigTrait` — backing properties `$templateClass`, `$confFilesDir`, `$logsDir` widened from `private` to `protected` so DTO subclasses using the trait can override or introspect the parsed values
- `SettingsTrait::$settings` — `private stdClass` → `protected stdClass`, giving subclasses and companion traits direct read access to the loose-scalar bucket
- `SupervisorSettingsTrait` — all backing properties (`$numberOfProcesses`, `$autoStart`, `$autoRestart`, `$prefix`, `$user`, `$logFile`) widened from `private` to `protected` for consistency with the rest of the library-extensibility pass
- `ConfFilesDto::$files` — `private array` → `protected array`
- `Cronjob\ScheduleDto` — constant `CRON_FIELD_PATTERN` and the `$minute`, `$hour`, `$dayOfMonth`, `$month`, `$dayOfWeek` backing fields widened from `private`/`private readonly` to `protected`/`protected readonly`; subclasses can implement custom cron dialects or reuse the validation regex without duplicating it
- `Cronjob\CommandDto` — promoted constructor property `$name` and all backing fields (`$logFileName`, `$user`, `$destinationFile`, `$command`, `$schedule`, `$settings`) widened `private readonly` → `protected readonly`
- `Worker\CommandDto` — same widening on `$name`, `$command`, `$settings`
- `Cronjob\CommandSettingsDto::$log` — `private` → `protected`
- `Cronjob\ConfigSettingsDto` — `$log`, `$destinationFile`, `$heartbeat`, `$user` widened `private` → `protected`
- `Worker\ConfigSettingsDto::$destinationFile` — `private` → `protected`
- `ConfigTrait::setConfigs()` — renamed to `setConfig()` (singular) to match the fact that it populates one DTO's config fields from a single config array; parameter also renamed `$config` → `$configuration` to follow the no-abbreviation rule in trait code
- `CronjobDto::__construct()` — parameter renamed `$cron` → `$cronjob` to drop the abbreviation and match the sibling `WorkerDto::__construct(array $worker)` naming
- `Cronjob\CommandDto::$scheduleDto` — property renamed to `$schedule` so the internal name matches its getter (`getSchedule()`) and the sibling `$settings` property; no public API change
- `CrontabTemplate::generate()` — cache `$configInterface->getSettings()->getHeartbeat()` once as `$heartbeatEnabled` instead of calling it twice per generation pass; minor readability win, no behavior change
- `CrontabTemplate::generate()` — simplified the `str_replace` call from single-element array-of-needle / array-of-replacement form to the equivalent scalar form; functionally identical, less noise
- `KubernetesCronjobTemplate::generate()` — renamed the local `$crontabPath` variable to `$cronjobPath`; the path points to a Kubernetes cronjob YAML, not a crontab, so the old name read as a copy-paste leak from `CrontabTemplate`
- `InstancesTrait`, `MemoryLimitTrait`, `TimeLimitTrait` — option-name constant references (`self::MAX_INSTANCES`, `self::INSTANCE_INDEX`, `self::MEMORY_LIMIT`, `self::TIME_LIMIT`) switched to `static::` for late static binding; a subclass that redefines one of these option-name constants now only needs to override the constant itself — the `configureX()` / `initializeX()` / `getXReached()` methods pick up the new value via LSB instead of being locked to the trait-defined value
- `Cronjob\ScheduleDto::validateField()` — internal `self::CRON_FIELD_PATTERN` reference switched to `static::CRON_FIELD_PATTERN` so a subclass that overrides the (now-`protected`) regex constant to support a custom cron dialect has its override picked up by the validator via LSB
- `SymfonyStyleTrait::$cachedPrefixSecond` / `$cachedPrefix` — widened from `private` to `protected` so subclasses overriding `format()` can reset or inspect the per-second prefix cache (e.g., to force a refresh or bypass caching in tests)

## [v4.2.4] - 2026-04-20 - Clarify template escaping boundary

### Changed

- `CrontabTemplate`, `SupervisorTemplate`, `KubernetesCronjobTemplate`, `KubernetesWorkerTemplate` — class-level PHPDoc documents that command parts are rendered verbatim into the generated config file and that sanitizing command input (shell metacharacters, newlines) is the caller's responsibility
- `README.md` — clarified the "Configuration values in generated files" section so the escaping contract is unambiguous: command parts pass through verbatim, YAML specials are escaped via `escapeYamlValue()` in the Kubernetes templates, and only the crontab log path is wrapped in `escapeshellarg()`
- `tests/Template/SupervisorTemplateTest.php` — renamed `testCommandIsEscaped()` → `testCommandPassesThroughVerbatim()` so the test name matches the documented behavior

## [v4.2.3] - 2026-04-17 - Revert template command quoting for string-form commands

### Fixed

- `CrontabTemplate::buildCommand()` — stop running `escapeshellarg` on each element of `CommandDto::getCommand()`; when users configured `command: 'php bin/console cmd:name'` as a YAML string, the config normalizer wrapped it into a single-element array containing the whole command, and per-element escaping quoted the entire string as one shell argument (`'php bin/console cmd:name'`), producing crontab lines cron tried to execute as a single nonexistent program
- `SupervisorTemplate::buildCommand()` — same revert; per-element `escapeshellarg` broke string-form commands
- `KubernetesCronjobTemplate::buildCommand()` — same revert; per-element `escapeshellarg` broke string-form commands
- `KubernetesWorkerTemplate::buildCommand()` — same revert; per-element `escapeshellarg` broke string-form commands

### Changed

- Template command output is now emitted as-is via `implode(' ', $commandDto->getCommand())`, matching the pre-v4.2.x behavior; log-file redirection in `CrontabTemplate::buildLog()` remains `escapeshellarg`-protected since it is built from code-controlled paths

## [v4.2.2] - 2026-04-16 - Harden TOCTOU guards, output suppression, and settings bool mapping

### Fixed

- `ConfFileWriter::save()` — guard a TOCTOU race where a symlink pre-exists at the chosen temp path: `Filesystem::mkdir` is a no-op over an existing link, so the path is now verified via `is_link`/`is_dir` after creation
- `ConfFileWriter::writeTemporaryFiles()` — canonicalize each written file via `realpath` and verify it stays within the (also canonicalized) temporary directory, blocking symlink-based escapes that pass textual checks
- `ConfFileWriter::writeTemporaryFiles()` — append a trailing separator to the destination prefix before `str_starts_with`, so `/tmp/conf` no longer matches `/tmp/confAAAA/...` via prefix alone
- `AbstractCommand::initialize()` — skip the decorated title block when stdout cannot render it (non-decorated / piped / redirected) or when verbosity is quiet, avoiding title pollution in machine-readable output
- `InstancesTrait::computeInstances()` — guard `getOption()` calls with `hasOption()`, consistent with `MemoryLimitTrait` and `TimeLimitTrait`
- `ScheduleDto::validateField()` — reject ranges with fewer than two parts (`5-`, `-5`) and ranges whose bounds are not numeric before comparison
- `SettingsTrait::getSetting()` — map `true` → `'true'` and `false` → `'false'` instead of falling through to `(string)` cast (where `false` becomes `''`, ambiguous with `null`)
- `SettingsTrait::loadProperties()` — wrap `TypeError` from typed property assignment into `InvalidValueException`; reject non-scalar setting values up-front
- `SymfonyStyleTrait::format()` — cache the `[HH:MM:SS][memory]` prefix per second (kept per-instance so concurrent commands and tests do not share state); removes the per-call `DateTimeImmutable` allocation
- `MemoryLimitTrait::getMemoryLimitReached()` — cache the parsed byte value of `--memory-limit` on first call instead of re-parsing on every iteration
- `MemoryService::setMemoryLimitIfNotHigher()` — bail out when `ini_get('memory_limit')` returns `false` on unusual PHP builds, instead of propagating a `TypeError` from `returnBytes(false)`
- `KubernetesCronjobTemplate::buildCommand()` — stop pre-wrapping the `schedule` value in quotes; quoting is the YAML layer's responsibility (`escapeYamlValue()`), which already quotes reserved glob chars such as `*`
- `KubernetesWorkerTemplate::buildCommand()` — stop pre-wrapping the `command` value in quotes, for the same reason
- `KubernetesCronjobTemplate::generate()` — simplify the destinationFile guard to `'' === $destinationFile` (the DTO's `getDestinationFile(): string` is non-nullable)
- `Configuration::buildCronjob()` / `buildWorker()` — replace `@var NodeBuilder` type-narrowing comment with a runtime `assert(instanceof ArrayNodeDefinition)` plus a scoped `@phpstan-ignore`
- `AttributeService::getCommandName()` — remove defensive null check on `$asCommand->name` (Symfony 7 types it as non-nullable)
- `CrontabTemplate`, `SupervisorTemplate`, `KubernetesCronjobTemplate`, and `KubernetesWorkerTemplate` — use `rtrim($dir, '/')` before path concatenation, preventing double slashes when a configured directory has a trailing slash
- `phpstan-baseline.neon` — remove 9 now-invalid entries and adjust Mockery-related counts after source fixes

### Changed

- `AbstractCommand::$input` / `$output` — removed `readonly` to allow subclasses that initialize through non-standard paths
- `services.php` — exclude `Template/Trait/` from service registration so traits are not mis-wired as services

### Added

- `@throws` annotations on `CronjobCreateCommand::execute()` and `WorkerCreateCommand::execute()`
- Tests: `AbstractCommandTest` (title suppression for non-decorated / quiet output); new `SettingsTraitTest` cases for bool mapping, `TypeError` wrapping, and non-scalar rejection; `ConfFileWriterTest::testSaveThrowsWhenTemporaryDirectoryIsSymlink` (TOCTOU symlink guard); extra `InstancesTrait` cases covering the unregistered-option `hasOption` path

## [v4.2.1] - 2026-04-13 - Guard invalid cron range and prevent static-only instantiation

### Fixed

- `ScheduleDto::validateField()` — guard ranges with fewer than two parts (e.g. `5-`) and throw `InvalidValueException` before attempting bound comparison
- `AbstractCommand::initialize()` — handle nullable `getName()` return value when rendering the title

### Changed

- `AttributeService` — added `private` constructor to prevent instantiation (class exposes only static methods)
- `MemoryService` — added `private` constructor to prevent instantiation (class exposes only static methods)
- Bumped `phpstan/phpstan` `2.1.46` → `2.1.47`
- Bumped `precision-soft/symfony-phpunit` `v3.2.0` → `v3.2.1`

## [v4.2.0] - 2026-04-13 - Widen private methods to protected for extensibility

### Changed

- `KubernetesJobTrait` — `convertArrayToString()`, `sanitize()`, `escapeYamlValue()`, `getIndent()` visibility widened from `private` to `protected`
- `ConfFileWriter` — `writeTemporaryFiles()`, `activateDirectory()`, `tryRestoreBackup()`, `silentRemove()` visibility widened from `private` to `protected`
- `ConfGenerateService` — `getTemplate()` visibility widened from `private` to `protected`
- `SymfonyStyleTrait` — `initializeSymfonyStyle()` visibility widened from `private` to `protected`
- `Configuration` — `buildCronjob()`, `buildWorker()`, `appendSupervisorConfig()` visibility widened from `private` to `protected`
- `SettingsTrait` — `toCamelCase()` visibility widened from `private` to `protected`
- `ConfigTrait` — `setConfigs()` visibility widened from `private` to `protected`
- `ScheduleDto` — `validateField()` visibility widened from `private` to `protected`

## [v4.1.2] - 2026-04-10 - Validate cron range order and remove unused buildCommand parameter

### Fixed

- `ScheduleDto::validateField()` — validate that cron range start is less than or equal to end (e.g. `5-3` now throws `InvalidValueException`)
- `KubernetesCronjobTemplate::buildCommand()` — removed unused `$configDto` parameter

### Changed

- `TypeError` imported via `use` in `ConfigDtoTest` (cronjob & worker) — replaced inline `\TypeError::class`
- Bumped `precision-soft/symfony-phpunit` `v3.1.0` → `v3.1.1`

## [v4.1.1] - 2026-04-09 - Extract ConfFileWriter helpers and expand DTO test coverage

### Changed

- `ConfFileWriter::save()` — extracted `writeTemporaryFiles()`, `activateDirectory()`, and `silentRemove()` private helpers; reduced method length and duplication

### Added

- Expanded DTO test coverage: `ConfigDtoTest` and `ConfigSettingsDtoTest` for both cronjob and worker

## [v4.1.0] - 2026-04-07 - Cron schedule validation and settings type error handling

### Fixed

- `KubernetesCronjobTemplate::generate()` — guard `null` `destinationFile` before processing commands (fail-fast, consistent with `KubernetesWorkerTemplate`)

### Changed

- `ConfigInterface` — removed `getSettings(): SettingInterface` method (responsibility moved to `SettingsTrait`)

### Added

- `ScheduleDto::validateField()` — validates each cron field against `CRON_FIELD_PATTERN` regex and enforces numeric range limits (minute 0–59, hour 0–23, day 1–31, month 1–12, weekday 0–7)
- `ScheduleDto::toCronExpression()` — assembles and returns the full cron expression string
- `SettingsTrait::getSetting()` — catches `TypeError` from invalid property assignments and converts to `InvalidValueException`; rejects non-scalar setting values at read time
- `InstancesTrait` — validates that `--max-instances` and `--instance-index` options are integer-parseable strings before use

## [v4.0.1] - 2026-04-06 - Supervisor nullable defaults and Kubernetes destination guards

### Fixed

- `Configuration::appendSupervisorConfig()` — command-level supervisor defaults (`number_of_processes`, `auto_start`, `auto_restart`) are now `null`, making the config-level fallback reachable via `??`
- `ConfFileWriter::save()` — early return when `$confFilesDto` has zero files, avoiding unnecessary temp directory creation
- `KubernetesCronjobTemplate::generate()` — validate `destinationFile` before processing commands (fail-fast)
- `KubernetesWorkerTemplate::generate()` — validate `destinationFile` before processing commands (fail-fast)

### Changed

- `SettingsTrait::toCamelCase()` — support hyphenated keys in addition to underscored
- `InstancesTrait::computeInstances()` — add empty string guard, numeric validation, and Yoda comparison for `$maxInstances < $instanceIndex`
- `MemoryLimitTrait` — Yoda comparison `$memoryLimit < $memoryUsage`
- `TimeLimitTrait` — Yoda comparison `$this->timeLimit <= $timeUsed`
- Remove unused `ReflectionException` import from `AttributeService`
- `ConfigSettingsDto` — add default values to `$log`, `$destinationFile`, `$heartbeat` properties
- `SupervisorTemplate` — rename `$configurationParams` to `$configurationParameters`
- `KubernetesJobTrait::escapeYamlValue()` — quote YAML reserved words, numeric values, and empty strings
- `KubernetesJobTrait::sanitizeKubernetesName()` — trim trailing dashes from sanitized output
- `KubernetesJobTrait::convertArrayToString()` — type-aware value output (string values escaped, others cast)
- `.dev/docker/entrypoint.sh` — skip `composer install` when `composer.lock` hash matches cached vendor
- Remove 4 unused `use` imports from `TemplateInterface` (`CronjobCommandDto`, `CronjobConfigDto`, `WorkerCommandDto`, `WorkerConfigDto`)
- Add `@param array<string, mixed> $commands` PHPDoc to `TemplateInterface::generate()`
- Update `phpstan-baseline.neon`

## [v4.0.0] - 2026-04-04 - Upgrade PHPUnit to 11.5 and rename SettingNotFound

### Breaking Changes

- Upgrade from PHPUnit 9 to PHPUnit 11.5 via `precision-soft/symfony-phpunit: ^3.0` — consumers must update their `phpunit.xml.dist` to PHPUnit 11 format (`<source>` instead of `<coverage>`, `<extensions>` instead of `<listeners>`, `SYMFONY_PHPUNIT_VERSION` set to `11.5`)
- Rename `SettingNotFound` to `SettingNotFoundException` — consistent with other exception class names (`InvalidConfigurationException`, `InvalidValueException`, etc.)

### Changed

- Replace `<coverage processUncoveredFiles="true">` with `<source>` element in `phpunit.xml.dist`
- Replace `<listeners>` with `<extensions>` using `Symfony\Bridge\PhpUnit\SymfonyExtension`
- Add `failOnRisky` and `failOnWarning` attributes to `phpunit.xml.dist`
- Migrate `KubernetesJobTraitTest` and `WorkerNumberOfProcessesTraitTest` from `TestCase` + `MockeryPHPUnitIntegration` to `AbstractTestCase` with `getMockDto()` pattern
- Rename `$exception` to `$settingNotFoundException` in `ExceptionTest` — variable naming convention compliance

## [v3.0.3] - 2026-04-03 - Add @throws annotations across services and README documentation

### Changed

- Fix Yoda comparison in `MemoryService::returnBytes()` — `$numericValue > X` to `X < $numericValue`
- Fix CHANGELOG v3.0.0 — correct `didScriptReachedLimits` to `hasScriptReachedLimits` in breaking changes
- Clarify Kubernetes `destination_file` documentation in README
- Rename `test()` to `testGenerate()` in `CrontabTemplateTest` and `SupervisorTemplateTest`
- Replace `expectNotToPerformAssertions()` with explicit `assertFalse(getScriptReachedLimits())` in `MemoryAndTimeLimitsTraitTest`

### Added

- Add `@throws` annotations to `InstancesTrait::computeInstances()`, `InstancesTrait::formatMessageWithInstances()`, `MemoryAndTimeLimitsTrait::stopScriptIfLimitsReached()`, `TimeLimitTrait::initializeTimeLimit()`, `ConfFilesDto::addFile()`, `Cronjob\CommandDto::__construct()`, `SettingsTrait::getSetting()`, `WorkerNumberOfProcessesTrait::getNumberOfProcesses()`
- Add `@throws` annotations to `KubernetesCronjobTemplate::generate()`, `KubernetesWorkerTemplate::generate()`, `SupervisorTemplate::generate()`
- Add `InvalidValueException` import to `KubernetesCronjobTemplate` and `KubernetesWorkerTemplate`
- Add `Contracts`, `Services`, `Exceptions`, and `AbstractCommand` sections to README
- Add `Troubleshooting` section to README — covers memory limit, file permissions, Kubernetes config, and trait conflicts
- Add `Security Considerations` section to README — documents heartbeat file safety, path traversal protection, and configuration value escaping

## [v3.0.2] - 2026-03-31 - Autowire and autoconfigure all services

### Fixed

- Add `autowire()` and `autoconfigure()` to all service definitions in `services.php` — ensures services and console commands are properly wired via the Symfony container

## [v3.0.1] - 2026-03-30 - Memory limit unlimited guard and Kubernetes null destination

### Fixed

- `MemoryLimitTrait::getMemoryLimitReached()` — returns `false` when memory limit is `-1` (unlimited) instead of comparing usage against `-1` bytes
- `KubernetesCronjobTemplate::generate()` — added null check on `destinationFile` consistent with `KubernetesWorkerTemplate`
- `phpstan-baseline.neon` — added entry for defensive null check on `KubernetesCronjobTemplate` (`identical.alwaysFalse`)

## [v3.0.0] - 2026-03-30 - Typed exceptions, atomic conf writes, and PHPStan level 8

### Breaking Changes

- `ConfigInterface` now extends `SettingsInterface` — all implementations must add a `getSettings(): SettingInterface` method
- `MemoryAndTimeLimitsTrait::stopScriptIfLimitsReached()` now throws `LimitExceededException` instead of calling `exit(Command::INVALID)` — callers must catch this exception
- `MemoryAndTimeLimitsTrait::hasScriptReachedLimits()` renamed to `getScriptReachedLimits()` — naming convention compliance, update all call sites
- `MemoryLimitTrait::isMemoryLimitReached()` renamed to `getMemoryLimitReached()` — naming convention compliance
- `TimeLimitTrait::isTimeLimitReached()` renamed to `getTimeLimitReached()` — naming convention compliance
- `CronjobCreateCommand` and `WorkerCreateCommand` now extend `AbstractCreateConfigCommand` instead of `AbstractCommand` — constructor signatures changed; `execute()` logic moved to parent
- `CronjobCreateCommand` catches `ConfGenerateException` only instead of generic `Throwable` — unexpected exceptions now propagate
- `WorkerCreateCommand` catches `ConfGenerateException` only instead of generic `Throwable` — unexpected exceptions now propagate
- Removed `version` field from `composer.json` — version is now derived from git tags only
- Replaced `squizlabs/php_codesniffer` with `phpstan/phpstan` in dev dependencies
- Upgraded `precision-soft/symfony-phpunit` from `1.*` to `^2.0`
- Symfony dependency constraints changed from `7.*` to `^7.0` (stricter semver)
- Renamed `phpunit.xml` to `phpunit.xml.dist` — local overrides via `phpunit.xml` are now gitignored

### Fixed

- `CrontabTemplate` — heartbeat logic no longer adds duplicate commands when heartbeat setting is disabled
- `CrontabTemplate` — command-level `user` now correctly overrides config-level user
- `MemoryService::returnBytes()` — handles plain integer strings and `-1` (unlimited) correctly
- `MemoryService::convertBytesToHumanReadable()` — clamps unit index to prevent out-of-bounds array access
- `KubernetesWorkerTemplate` — validates `destinationFile` is not null before generating
- Trailing space removed from bytes unit in `MemoryService::convertBytesToHumanReadable()`
- `services.php` DI argument name mismatch — `$config` did not match constructor parameters `$cronjobConfiguration`/`$workerConfiguration`, commands never received configuration via the service container
- `ConfFileWriter` false failure on backup cleanup — a failed backup removal after successful deploy no longer masks the success as a failure

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

## [v2.3.7] - 2026-03-21 - Fix trailing space in human readable byte formatting

### Fixed

- `MemoryService::convertBytesToHumanReadable()` — trailing space removed from the `B` entry in the units array; byte-level output previously rendered with a double space (`1024  B`)

## [v2.3.6] - 2026-03-20 - Correct README clone URL to GitHub

### Fixed

- `README.md` — clone URL corrected to point at GitHub instead of GitLab

## [v2.3.5] - 2026-03-19 - Clamp bytes-to-human unit index

### Fixed

- `MemoryService::convertBytesToHumanReadable()` — unit index clamped with `\min((int)\floor(\log($bytes, 1024)), \count($unit) - 1)` to prevent out-of-bounds array access for values larger than `PB`

## [v2.3.4] - 2026-03-19 - Atomic conf generation with rollback

### Changed

- `ConfGenerateService::generate()` — refactored to write configuration into a temporary directory and atomically rename to the destination, with rollback/cleanup on exception so a failed generation cannot corrupt the active config
- `TimeLimitTrait::initializeTimeLimit()`, `MemoryLimitTrait::initializeMemoryLimit()`, `SupervisorTemplate`, `WorkerNumberOfProcessesTrait` — `empty(...)` replaced with explicit null/zero/boolean comparisons for predictable evaluation

## [v2.3.3] - 2026-03-19 - Move dev scripts to hidden .dev directory

### Changed

- Moved development scripts directory from `dev/` to `.dev/` (Docker config, git hooks, shared shell utilities, `.profile`, `.env`)
- Updated pre-commit hook, composer scripts, and utility references to the new `.dev/` location
- `composer.json` — homepage URL corrected to match the GitHub repository URL
- `composer.lock` refreshed via `composer update`

## [v2.3.2] - 2026-03-19 - Tighten DTO return types and zero-byte memory format

### Fixed

- `Worker\CommandDto::getName()` and `getCommand()` — return types tightened from `?string` to `string`
- `CronjobDto::getConfig()` and `getCommands()` — return types tightened to non-nullable `ConfigDto` / `array`
- `WorkerDto::getConfig()` and `getCommands()` — return types tightened to non-nullable equivalents
- `MemoryService::convertBytesToHumanReadable()` — zero-byte input now returns a formatted `0 B` instead of dividing by zero via `\log(0, 1024)`

### Changed

- `CrontabTemplate::buildLog()` — Yoda-style conditionals standardized; `\array_merge()` and `\sprintf()` calls prefixed with the global namespace for consistency

### Added

- `Worker\ConfigSettingsDto::getDestinationFile()` — typed getter for the destination file setting; `KubernetesWorkerTemplate` and `SupervisorTemplate` switched from raw setting lookups to this accessor

## [v2.3.1] - 2026-03-13 - Source code style normalization

### Changed

- Source code style normalized across `src/`: parameter formatting, Yoda conditions, and variable naming aligned across `MemoryLimitTrait`, `TimeLimitTrait`, `SymfonyStyleTrait`, `AttributeService`, and `ConfGenerateService` without any functional changes
- `composer.json` — version field refreshed for the v2 package line

## [v2.3.0] - 2025-11-03 - Introduce memory and time limit traits

### Added

- `MemoryLimitTrait` — `initializeMemoryLimit()` and `getMemoryLimit()` for enforcing a byte budget inside long-running workers and cronjobs
- `TimeLimitTrait` — `$startTime`, `initializeTimeLimit()`, `getTimeLimit()` for enforcing a wall-clock budget
- `MemoryAndTimeLimitsTrait` — composite trait combining both limits so a single `stopScriptIfLimitsReached()` call covers memory and time
- `MemoryService::setMemoryLimitIfNotHigher()` — raises the PHP `memory_limit` only when the requested value is greater than the current one

## [v2.2.1] - 2025-10-25 - Silence PHP 8.4 implicit-nullable deprecations

### Fixed

- `SymfonyStyleTrait` and related output helpers — nullable type handling corrected to silence PHP 8.4 implicit-nullable deprecations

## [v2.2.0] - 2025-01-06 - Supervisor log_file per-command setting

### Added

- `SupervisorSettingsTrait::getLogFile()` — typed accessor for per-command Supervisor log file path
- `Configuration` — `log_file` node added to the Supervisor configuration schema

## [v2.1.0] - 2024-12-13 - Overridable heartbeat command in CrontabTemplate

### Changed

- Heartbeat command generation moved out of the cronjob DTO and into template-level logic; `CrontabTemplate::buildCrontab()` now composes heartbeat entries uniformly alongside user-defined commands

### Added

- `CrontabTemplate::getHeartbeatCommand()` — protected method that returns the `CommandDto` used as heartbeat so subclasses can override the default heartbeat command

## [v2.0.1] - 2024-12-13 - Emit heartbeat entries across all cronjob files

### Fixed

- `CrontabTemplate::buildCrontab()` — heartbeat entries are now emitted in every generated crontab file, not only the first one produced during a multi-file generation

## [v2.0.0] - 2024-12-11 - Multiple cronjob file generation

### Changed

- `CrontabTemplate::buildLog()`, `buildCommand()`, `buildSchedule()` visibility widened from `private` to `protected` to support subclass customization of the new multi-file flow
- `CronjobCreateCommand`, `WorkerCreateCommand` — internal helpers relaxed from `private` to `protected` to support the shared generation pipeline
- `CommandDto` and `ScheduleDto` refactored to carry the additional per-command destination context

### Added

- Multiple cronjob file generation — a single configuration can target several crontab outputs via per-command `destination_file`; `ConfGenerateService` iterates the declared files accordingly
- `TemplateInterface::setDestinationFile()` / `getDestinationFile()` — introduced on the template contract so implementations carry their target file through generation

## [v1.2.1] - 2024-12-09 - Fix InstancesTrait prefix formatting and crontab edges

### Fixed

- `InstancesTrait::formatMessageWithInstances()` — template-string interpolation corrected so `[index/max]` prefixes render consistently
- `CrontabTemplate` — cron expression generation edge cases addressed (whitespace handling, trailing separators)

## [v1.2.0] - 2024-11-05 - InstancesTrait message prefix helper

### Added

- `InstancesTrait::formatMessageWithInstances()` — helper for prefixing output with `[<instanceIndex>/<maxInstances>]` so parallel workers/cronjobs produce self-identifying log lines

## [v1.1.0] - 2024-10-04 - Per-command user and log_file_name cronjob options

### Added

- `CrontabTemplate` — `user` and `log_file_name` options exposed on cronjob commands; generated cron lines respect per-command overrides
- `AttributeService::getCommandName()` — reads the Symfony `#[AsCommand]` attribute to extract command names for template generation
- `Configuration` — schema extended to expose `user` and `log_file_name` settings under the cronjob command tree

## [v1.0.0] - 2024-09-19 - Initial release

### Added

- `CronjobCreateCommand` and `WorkerCreateCommand` — Symfony console commands that render runtime configuration from the bundle's own configuration tree
- `CrontabTemplate` — generates standard crontab files
- `SupervisorTemplate` — generates Supervisor `.conf` files
- `KubernetesCronjobTemplate` — generates Kubernetes `CronJob` manifests
- `KubernetesWorkerTemplate` — generates Kubernetes `Deployment`/worker manifests
- `ConfGenerateService` — orchestrates template selection, generation, and file output
- `MemoryService` — memory-usage helpers (`returnBytes()`, `convertBytesToHumanReadable()`)
- `SymfonyStyleTrait` — styled output wrapper with timestamp and memory-usage prefix
- `InstancesTrait` — parallel-execution helper using `--max-instances` and `--instance-index` options
- `TemplateInterface` — extension contract for custom template implementations
- `ConfigInterface`, `SettingsInterface` — configuration contracts
- Symfony DI configuration under the `precision_soft_symfony_console` tree

### Notes

- Initial public release of `precision-soft/symfony-console`

[Unreleased]: https://github.com/precision-soft/symfony-console/compare/v4.2.5...HEAD

[v4.2.5]: https://github.com/precision-soft/symfony-console/compare/v4.2.4...v4.2.5

[v4.2.4]: https://github.com/precision-soft/symfony-console/compare/v4.2.3...v4.2.4

[v4.2.3]: https://github.com/precision-soft/symfony-console/compare/v4.2.2...v4.2.3

[v4.2.2]: https://github.com/precision-soft/symfony-console/compare/v4.2.1...v4.2.2

[v4.2.1]: https://github.com/precision-soft/symfony-console/compare/v4.2.0...v4.2.1

[v4.2.0]: https://github.com/precision-soft/symfony-console/compare/v4.1.2...v4.2.0

[v4.1.2]: https://github.com/precision-soft/symfony-console/compare/v4.1.1...v4.1.2

[v4.1.1]: https://github.com/precision-soft/symfony-console/compare/v4.1.0...v4.1.1

[v4.1.0]: https://github.com/precision-soft/symfony-console/compare/v4.0.1...v4.1.0

[v4.0.1]: https://github.com/precision-soft/symfony-console/compare/v4.0.0...v4.0.1

[v4.0.0]: https://github.com/precision-soft/symfony-console/compare/v3.0.3...v4.0.0

[v3.0.3]: https://github.com/precision-soft/symfony-console/compare/v3.0.2...v3.0.3

[v3.0.2]: https://github.com/precision-soft/symfony-console/compare/v3.0.1...v3.0.2

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
