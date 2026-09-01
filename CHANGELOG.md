# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v4.7.0] - 2026-09-01 - Systemd service generation and read-only preview modes

### Added

- `Template\SystemdServiceTemplate` — a worker template that emits one concrete systemd `.service` unit per configured instance, so nothing has to be templated at install time: `number_of_processes: 4` writes four units named `-1` through `-4`, and a count of one drops the numeric suffix entirely. The unit name is `<prefix>-<command-name>` sanitized down to what systemd accepts — everything outside `A-Za-z0-9_.-` collapses to `-`, `..` collapses to `.`, surrounding `-` and `.` are stripped — and a name that sanitizes down to nothing is rejected rather than written. `@` is deliberately not preserved: a unit named `foo@.service` is a *template unit* systemd refuses to start without an instance name, so a command named `@mail` would otherwise produce a file that installs cleanly and never runs. Unlike Supervisor, `prefix` is optional. `ExecStart` is validated to start from an absolute path, because systemd rejects a unit whose executable is not absolute and the failure would otherwise
  surface at `systemctl start`, long after generation reported success. `destination_sub_dir` and `destination_suffix` behave exactly as they do for Supervisor, the suffix landing before the `.service` extension
- `worker.config.settings` and `worker.commands.*.settings` gain `working_directory` (defaulting to `%kernel.project_dir%`), `environment_file`, `restart_policy` (defaulting to `always`, validated against the seven systemd values at container build time), `standard_output` and `standard_error`, each resolved per command first and falling back to the config level. They are modelled as typed properties on the worker settings DTOs through `Dto\Trait\SystemdSettingsTrait`, so they are reachable as `getWorkingDirectory()` and friends rather than through the unmodelled `getSetting()` bag. Both streams default to `append:<log_file>`, and the `EnvironmentFile=` line is omitted entirely when no file is configured
- Config-generation commands expose three read-only modes: `--dry-run` lists the destination paths that would be added, changed or removed; `--diff` follows each pending path with a unified diff of its content, three lines of context per hunk, rendered by `Service\ConfGenerate\ConfFileDiffRenderer`; `--check` exits with failure when anything is pending, which is what makes it usable as a CI drift check. `--check` and `--diff` combine, so a failing check can show what drifted. Diff lines are escaped before they reach the console, or a generated `<` would be read as a formatter tag and swallowed, and they bypass the `[time][memory]` line prefix that would otherwise break every line of a diff
- `Service\ConfGenerate\ConfFileWriter::diff()` and `Service\ConfGenerate\ConfGenerateService::preview()` — the read-only counterpart of `save()`, returning a `Dto\ConfFileChangesDto` of `Dto\ConfFileChangeDto`, each carrying its `Dto\ConfFileStatus` and both sides of the content, sorted by path
- `Template\Trait\WorkerDestinationPathTrait` and `Template\Trait\WorkerSettingsTrait` — the destination path composition and the `user` / `log_file` resolution that `SupervisorTemplate` performed inline, extracted and shared with `SystemdServiceTemplate`. Supervisor paths and rendered programs are unchanged, byte for byte, apart from the `log_file` fix below

### Changed

- Symfony component constraints now accept 7.x and 8.x. Symfony 8 itself requires PHP 8.4+, so a PHP 8.2 or 8.3 install still resolves Symfony 7. CI covers PHP 8.5 in the locked matrix and gains a `symfony-latest` lane that resolves the highest allowed dependency set; that lane passes `--fail-on-skipped` to both suites, being the only one whose dependencies are not pinned and therefore the only one where a suite could degrade to skips unnoticed
- `Service\ConfGenerate\ConfGenerateService` takes a `ConfFileDiffRenderer` as a third constructor argument. It defaults to a fresh instance, so existing manual instantiations keep working; the container injects the registered service

### Fixed

- `ConfFileWriter::diff()` reported every file in the destination directory as `removed` when the configuration declared no file at all, while `save()` returns early on an empty set and never touches the directory. `--check` therefore reported drift that no generation run could ever clear, leaving a consumer's CI permanently red with no way out. The guard now mirrors `save()`: a writer that would touch nothing reports nothing
- `worker.commands.*.settings.restart_policy` rejected an explicit `null` — the very value it defaults to — because the validation ran `ifNotInArray` against the seven systemd policies, and `null` is in none of them. A command could therefore not opt out of the config-level policy the way it opts out of every other nullable setting. `working_directory` had the mirror-image problem: `cannotBeEmpty()` on a node whose per-command default is `null` rejected `working_directory: ~` as an empty value
- `SupervisorTemplate` read `log_file` only from the command level, so `worker.config.settings.log_file` was a dead node for the only template that consumed it. It is now resolved command level first, then config level, then the derived `<logs_dir>/<command-name>.log` — the same chain the systemd template uses
- `ConfFileWriter::diff()` iterated the destination directory exactly as configured while matching against the normalized prefix, so an unnormalized `conf_files_dir` ending in `//` spelled the same file two ways: the declared path came back `unchanged` and the iterated one came back `removed`, at the same time, for the same file. `--check` therefore failed forever on a trailing double separator. Iteration now starts from the normalized prefix, so both sides agree
- A symlinked `conf_files_dir` was mishandled from both ends: `save()` silently replaced the link with a real directory, because activation renames the destination path itself rather than writing through it, and nothing said so; `diff()` skipped removal detection entirely and answered "current" for a tree that write would have flattened. Both now refuse a symlinked destination with a named `ConfGenerateException`, so a preview cannot pass what a write would break and the link is never destroyed without a word
- `SystemdServiceTemplate` emitted every value verbatim, and systemd expands specifiers in all of them: a literal `%` in a command argument, a working directory or a log path was read as a specifier — silently substituted when it happened to be a known one (`--format=%s`), and a fatal "unit will not be started" when it was not (`--format=%Q`), which `systemd-analyze verify` confirms. Literal `%` is now emitted as `%%`
- `SystemdServiceTemplate` joined the command parts with a single space, and systemd splits `ExecStart` on whitespace, so an argument carrying any became several arguments without a word of warning. A part carrying whitespace, a quote or a backslash is now emitted double-quoted with the inner characters escaped; ordinary parts are unchanged

## [v4.6.0] - 2026-08-25 - Per-file heartbeat and declared destination files

### Added

- `CrontabTemplate::DESTINATION_FILE_PLACEHOLDER` (`{destination_file}`) — an overridden `heartbeat` command is emitted into every generated crontab file, and until now the same command string reached all of them: `$destinationFile` was passed only to the `/bin/touch` fallback. A heartbeat that has to name the file it proves alive — one that records it in a shared database rather than touching a local one — could therefore not go through the override mechanism at all, and the only way to get per-file arguments was one ordinary cron row per file, with the fleet hardcoded at the call site. The placeholder is replaced with the destination path relative to `conf_files_dir`, flattened by replacing `/` with `.` (`machine-a/crontab` becomes `machine-a.crontab`) so that two files sharing a base name in different sub directories stay distinguishable, in the command parts and in `log_file_name` alike — before the line is assembled, so the log path is escaped around the substituted value rather
  than the other way round. Substitution applies to the heartbeat command only, and an override that does not contain the placeholder is returned untouched and emitted byte for byte as before, so nothing that works today changes. An override that does use the placeholder is resolved into a fresh `CommandDto` carrying every key the original declared, unmodelled `settings` entries included, so `getSetting()` returns the same value whether or not the placeholder was present
- `cronjob.config.settings.destination_files` — a list of crontab files that are generated even when no command targets them, unconditionally, so with `heartbeat` off a declared file nothing targets is written with no cron rows in it, validated for `..`, backslashes and paths that normalize away to nothing like every other path fragment, non-deep-merging so an environment can replace the list rather than append to it, and re-indexed before validation so a mapping written where a list was meant still yields a list. A crontab file only comes into existence because some command names it in `destination_file`, which means a machine that is supposed to run **nothing but the heartbeat** ends up with no crontab, and therefore no heartbeat either — the one case the feature above cannot cover on its own. Declared files are added to the ones the commands already produce, never instead of them: the default file is still materialised for a configuration whose only command is the heartbeat. The
  precedent was already in the template, which materialises the default file when the configuration carries no commands at all
- `ConfigSettingsDto::getDestinationFiles()` on the cron job settings DTO

### Changed

- `Template\Trait\DestinationPathTrait` — the `.`-and-empty path segment filtering that `SupervisorTemplate::buildPath()` already performed inline is extracted and shared with `CrontabTemplate`, which needs the same rule for the fixes below. Supervisor paths are unchanged, byte for byte. Both templates gain `splitDestinationPath()`, `normalizeDestinationPath()` and `buildDestinationPathLabel()` as protected methods, so a subclass that already declares any of those names will collide
- `Dto\Trait\SettingsTrait::toArray()` — the inverse of `loadProperties()`, returning modelled properties and the unmodelled `stdClass` bag under the snake case keys they were loaded from, so a settings array can be round-tripped back through the constructor. Added on the trait rather than on `Contract\SettingInterface`, which would have broken every implementation outside this package
- `Configuration::appendStringListPrototype()` — the node shape that `logs_dirs` and `destination_files` share (replaced rather than merged, no empty entry, entries validated as strings with the node named in the message) is declared once. `logs_dirs` is unchanged; `destination_files` keeps its own re-indexing and path validations on top

### Fixed

- `CrontabTemplate::generate()` — a destination file named with a decimal integer (`2026`, `123`) aborted the whole generation with a `TypeError` instead of producing a crontab. PHP casts numeric-string array keys to `int`, and the destination file name is the key of the per-file command list, so `getHeartbeatCommand()` was handed an `int` where its signature declares `string`. The defect is present in v4.5.0 and reachable there through a command-level `destination_file`; `destination_files` would have widened it to the config level. The key is now cast once, at the top of the loop, before anything derives a heartbeat label from it
- `CrontabTemplate` — every generated crontab file received a heartbeat naming only the **base name** of its destination, so `machine-a/crontab` and `machine-b/crontab` both emitted `/bin/touch <logs_dir>/heartbeat.crontab` and, through the placeholder above, both received the substituted value `crontab`. Two machines then touched the same heartbeat file and the heartbeat could no longer say which crontab it proved alive — precisely what the feature exists to do. The value is now the flattened relative path. Flat destination files, which is every file in this repository's tests and documentation, are unchanged: `crontab` still yields `heartbeat.crontab`. A configuration that already used nested destination files moves from `heartbeat.crontab` to `heartbeat.machine-a.crontab`, so a monitor watching the old path has to follow
- `CrontabTemplate::generate()` — a destination file built only from `.` and `/` segments (`.`, `./`, `/`) normalized away to an empty string, which made the generated path `conf_files_dir` itself with a trailing separator. That path passed `ConfFileWriter`'s prefix check because it *equals* the prefix, and the failure surfaced only as an `IOException` about `dumpFile` on a directory. Such values — `''` included, which `destination_file` accepted because it never carried a `cannotBeEmpty()` — are now rejected at container build time by `destination_file` and `destination_files` alike, with the node named. A configuration that literally declares one of them stops booting instead of failing at generation time; one that arrives at the same value through a container parameter still reaches the template, since the node sees an unexpanded placeholder at configuration time; `CrontabTemplate` keeps a matching `InvalidConfigurationException` as the backstop for a value that arrives through a
  container parameter and so cannot be checked at configuration time
- `CrontabTemplate::generate()` — two destination files spelled differently but resolving to the same path (`crontab` and `./crontab`) were treated as two files, so both were written to the same place and the second silently overwrote the first: the command rows of the losing spelling disappeared, with a success exit code and a `generated \`2\` conf files` message that counted a file nobody could find. The name is now normalized — `.` and empty path segments are dropped — before it becomes the key of the per-file command list, so the two spellings merge into one file that carries both. Nothing that already worked changes place: the filesystem was resolving `conf_files_dir/./crontab` to `conf_files_dir/crontab` all along, and a spelling with no colliding sibling still lands exactly where it did. A trailing slash (`crontab/`), which used to abort the whole generation, now normalizes and generates. `..` remains rejected at configuration time

## [v4.5.0] - 2026-08-17 - Exceptions carry structured context

### Added

- `Contract\ExceptionInterface` and `Exception\Trait\ExceptionTrait` — every exception in the bundle now carries a structured `context` array alongside its message, readable with `getContext()` and replaceable with `setContext()`. Values belong in the context rather than interpolated into the message, so a consumer can log or assert on them without parsing a string
- `Exception::__construct()` — accepts an optional fourth `?array $context` argument. Purely additive: no existing message, code or previous throwable changed, so every existing `new Exception($message, $code, $previous)` call keeps working and a consumer logging only `getMessage()` sees exactly what it saw before. The one way it can break somebody: a consumer subclass that already declares its own `$context` property or a `getContext()`/`setContext()` method will collide with the trait
- `ConfGenerateException::from()` — re-wraps a throwable while keeping it as the previous one and attaching context. Declared on `ConfGenerateException` rather than on the shared base because `SettingNotFoundException` deliberately takes a different constructor signature, which `new static()` could not honour
- `ConfGenerateService::generate()` and `ConfFileWriter` now populate that context: `templateClass` and `confFilesDir` when a template fails, `destinationDir`, `backupDirectory` and `backupRestored` when an activation failure leaves a backup behind, and `logsDir` when a logs directory cannot be created
- `CronjobCreateEndToEndTest` and `KubernetesTemplateEndToEndTest` — compile a real container and run `cronjob-create` / `worker-create` end to end for the crontab and both Kubernetes templates, asserting that the generated file **parses** rather than that it contains the right substrings: the crontab through a field-count tokenizer, the Supervisor `.conf` through `parse_ini_string()` and the manifests through `Yaml::parseFile()`. The mapping-instead-of-sequence defect above was invisible to every substring assertion in the suite and was found by the first parse
- Coverage for four contracts that were exercised only through their rejecting or exceptional branch, so the ordinary path every consumer takes was asserted nowhere. **A `destination_file` that is explicitly set is now asserted to survive validation** at all three declaration sites — the suite only ever proved the validator rejects `..`, so a validator that refused every legitimate value would have shipped green. **Unrecognised `settings` keys are asserted to survive** at all four `settings` nodes, which is how a supervisor directive the bundle does not model reaches the template. **`error()` without `$exposeTrace` is asserted not to print a stack trace**, and `formatThrowable()` is asserted to stop at `class::file::line` when the exception carries no context. **The staged and backup directories are asserted to be unpredictable siblings of the destination** — the parent directory is what makes the activation rename atomic and the random suffix is what stops an attacker pre-creating a
  symlink at a path they can predict, and neither half was observed by any assertion
- `Configuration::appendDestinationFileConfig()` — declares the `destination_file` node at all three places it exists (`cronjob.config.settings`, per cron job command, `worker.config.settings`) with the same `..`/backslash validation `destination_sub_dir` already had. The templates append this value to `conf_files_dir` exactly the way they append `destination_sub_dir`, so it was the one path fragment reaching the filesystem without a build-time check

### Changed

- **`KubernetesCronjobTemplate` and `KubernetesWorkerTemplate` emit their collection as a YAML sequence.** Up to and including v4.4.0 the hand-rolled emitter wrote a PHP list as a mapping keyed `0:`, `1:` — `CronJobs.jobs.0.name` rather than the first item of a list. Both forms parse into the same structure in most consumers, which is why no test and no review caught it, but they do not **merge** the same way: Helm merges two values files key by key for a mapping and replaces the whole value for a sequence, so a second values file used to override job 0 field by field instead of replacing the list. **Upgrade note:** a chart that indexes an entry directly (`.Values.CronJobs.jobs.0`) must move to `index` or a `range`; a chart that already iterates with `range` is unaffected
- `Template\Trait\KubernetesJobTrait` — the manifests are now written by `Symfony\Component\Yaml\Yaml::dump()`, which is correct by construction for sequences, quoting and indentation. `convertArrayToString()` keeps its name but takes only the array (the `$baseIndentLevel` / `$indentSize` parameters are gone), and `escapeYamlValue()` and `getIndent()` are removed — a subclass overriding any of the three needs updating. Quoting style changes with the dumper: values that need quoting now use single quotes rather than double
- `symfony/yaml` (`^7.0`) is a runtime dependency, required by the two Kubernetes templates
- `SymfonyStyleTrait::formatThrowable()` — walks the whole `previous` chain and prints every link joined by ` <- `, with each link's context appended as JSON. It previously printed only the outermost throwable's class, file and line, which for any rewrapped failure is the `catch` block rather than the origin: a `TypeError` raised inside a template was reported to the operator as `ConfGenerateException` at `ConfGenerateService.php`. The chain is capped at 10 links so a cyclic `previous` cannot hang the command, and `$exposeTrace` now exposes the root cause's trace instead of the wrapper's
- comments across the package normalized to the house rule — the default is no comment, and a warranted one is a single short line. Every multi-line rationale block, narrative test docblock and shell section header was removed; the `.dev/` scripts, the `Dockerfile` and the compose file now carry nothing but their shebang and one line about `tini` as PID 1. Nothing behavioral changed. `CONTRIBUTING.md` gained the two sections that now carry the rationale — *Development toolchain* (the pinned pcov and infection builds, the `php.dev.ini` overlay, the mutation thresholds) and *Continuous integration* (the jobs, and why `--fail-on-skipped` is passed in CI only) — and its *Verification* section now documents `.dev/validate/all.sh` and its flags, replacing the stale description of the old hook

### Fixed

- `ConfGenerateService::generate()` — catches `Exception` instead of `Throwable`, so a `TypeError` or any other `Error` raised inside a template propagates untouched instead of being rewrapped as a `ConfGenerateException`. Wrapping consumer exceptions is still correct — `TemplateInterface::generate()` declares no `@throws`, so a custom template may throw anything — but a programming error disguised as a configuration error sends the operator looking for a bad config value that does not exist
- `Configuration::buildLogsDirs()` — `logs_dirs` entries are now validated as strings at container build time, naming the offending node. A YAML scalar need not be a string, and the extension hands this list straight to `LogsDirCreateCommand::$logsDirs`, which is typed `array<int, string>`: an `int` or a `bool` entry passed the `?string`-typed filter closure untouched (PHP does not apply `strict_types` to callbacks invoked by internal functions such as `array_filter`), landed in the container parameter as-is and only failed inside `ConfFileWriter::initLogsDir()` — at deployment time, with a message naming neither the node nor the configuration file
- `LogsDirCreateCommand` — the success message formats the directory count with `%d` instead of `%s`
- `phpstan-baseline.neon` — **deleted.** It was 129 lines / 21 entries, and the premise that it was mock-typing noise turned out to be wrong here: all 93 `X|MockInterface` sites are `@var` docblocks over locals, and converting every one of them changed the baseline by nothing. The 63 raw errors were four unrelated families, and **41 of them were one** — `reset($files)` returns `string|false` and the result was consumed directly, fixed once in `tests/Utility/ConfFiles`. Level 8 is `[OK]` with two `ignoreErrors` entries in `phpstan.neon`, each carrying the reason it exists
- `phpstan.neon` — the `Mockery::namedMock()` `argument.type` suppression is **removed**: mockery 1.6.13 fixed the variadic `$args` signature that produced the false positive, so the pattern stopped matching and PHPStan reported it as a `ignore.unmatched` error, which is non-ignorable. The suppression had done its job and outlived its cause

## [v4.4.0] - 2026-07-23 - Per-Command Supervisor Destination Sub Directories

### Added

- `destination_sub_dir` and `destination_suffix` configuration nodes for workers, available both under `worker.config.settings` and per command. `SupervisorTemplate` resolves the per-command value first and falls back to the config-level one, so a single application config can spread its Supervisor `.conf` files across sub directories of `conf_files_dir` — one per machine, region or deployment colour — instead of dumping them all in a flat directory. Setting either to an empty string at command level opts that command out of the config-level value
- `Worker\CommandDto::getDestinationSubDir()` and `getDestinationSuffix()` — typed accessors for the per-command values
- `Worker\ConfigSettingsDto::getDestinationSubDir()` and `getDestinationSuffix()` — typed accessors for the config-level values
- `Configuration::appendDestinationConfig()` — declares both nodes with validation that rejects `..` and backslashes (plus `/` for the suffix) at container build time, naming the offending node in the message. `ConfFileWriter` already rejected such paths, but only at generation time and with a raw filesystem path in the error
- `WorkerCreateEndToEndTest` — compiles a real container and runs `worker-create` end to end, covering the worker configuration surviving `%kernel.project_dir%` expansion inside an array parameter and `destination_sub_dir` reaching the filesystem as an actual directory tree

### Fixed

- `SupervisorTemplate::getPath()` — the returned path is now collapsed to its meaningful segments, so a `destination_sub_dir` of `.`, `a//b` or `./x` no longer yields a path that differs from the file actually written. `AbstractCreateConfigCommand` prints these paths back to the user, and `ConfFilesDto::addFile()` detects collisions by comparing them verbatim, so both were working off a path that could not be resolved as printed
- `ConfGenerateService::generate()` — template failures are now wrapped in `ConfGenerateException` instead of escaping as `InvalidConfigurationException` / `InvalidValueException`, honouring the `@throws ConfGenerateException` the method already declared and matching how `ConfFileWriter` wraps its own failures. `AbstractCreateConfigCommand` only catches `ConfGenerateException`, so those exceptions escaped the command as uncaught fatals with a stack trace instead of a clean error message and a `FAILURE` exit code. Previously unreachable for `SupervisorTemplate` — command names are unique config keys, so its generated paths could never collide — but `destination_suffix` makes a collision expressible (a command `worker` with suffix `blue` and a command `worker.blue` both resolve to `worker.blue.conf`)

### Changed

- `Worker\ConfigSettingsDto` — `destination_file` is now declared on the `worker.config.settings` node instead of being carried through as an undeclared extra key, so it normalizes and defaults to `null` like every other setting

## [v4.3.0] - 2026-07-10 - Standalone Logs Dir Creation

### Added

- `LogsDirCreateCommand` (`precision-soft:symfony:console:logs-dir-create`) — creates the configured logs dirs on its own, idempotently. `cronjob-create` and `worker-create` create their own logs dir too, but only as a side effect of generating conf files, so a deployment that never runs them is left without the directories
- `logs_dirs` configuration node — a root-level list of *additional* directories to create, empty by default, for applications that log into directories no cronjob or worker owns. Deliberately kept outside `cronjob`/`worker` so it stays meaningful when neither is declared
- `precision_soft_symfony_console.logs_dirs` container parameter — derived as `cronjob.config.logs_dir` + `worker.config.logs_dir` + `logs_dirs`, deduplicated. Overriding `cronjob.config.logs_dir` therefore moves the directory `logs-dir-create` creates, instead of silently drifting away from it

### Changed

- `ConfFileWriter::initLogsDir()` now wraps filesystem failures in `ConfGenerateException` instead of letting Symfony's `IOException` escape, matching `save()` and honouring the `@throws ConfGenerateException` already declared on `ConfGenerateService::generate()`

## [v4.2.11] - 2026-06-17 - Atomic Same-Filesystem Staging for Config Activation

### Fixed

- `ConfFileWriter::save()` — the staging directory is now created next to the destination (same parent directory, hidden `.conf_` prefix) instead of under `sys_get_temp_dir()`, so the activation step is a true atomic same-filesystem `rename()`; staging under `/tmp` degraded to a non-atomic cross-filesystem copy whenever `/tmp` sits on a different mount (common in containers), opening a window in which a partially-populated destination could be observed

## [v4.2.10] - 2026-06-17 - Add composer convenience scripts

### Added

- `composer.json` — added `test`, `phpstan`, `cs-check`, `cs-fix` and an aggregate `check` convenience script wrapping `simple-phpunit`, `phpstan`, and `php-cs-fixer`

## [v4.2.9] - 2026-04-23 - Widen ConfFilesDto fluent return type to static

### Changed

- `ConfFilesDto::addFile()` — return type widened from `self` to `static`; the class has a `protected array $files` property indicating subclassing is intended, so fluent chains on a `ConfFilesDto` subclass now return the subclass type

## [v4.2.8] - 2026-04-23 - Complete Late Static Binding in Configuration

### Fixed

- `Configuration::DESTINATION_DIR` and `Configuration::NAME` — widened from `private` to `protected` and changed all 6 `self::` references in `buildCronjob()` and `buildWorker()` to `static::` so subclasses can override the destination path and key name defaults; the v4.2.7 pass covered public constants but left these two private, blocking both visibility and late static binding

## [v4.2.7] - 2026-04-23 - Rename YAML output accumulator in KubernetesJobTrait

### Changed

- `KubernetesJobTrait::convertArrayToString()` — local variable `$command` renamed to `$lines`; the variable accumulates YAML output lines, not a command, so the old name was semantically wrong and misleading to readers

## [v4.2.6] - 2026-04-21 - Complete late static binding coverage in Configuration and MemoryService

### Changed

- `Configuration::buildCronjob()`, `buildWorker()`, `appendSupervisorConfig()` — remaining `self::` references to the class's public configuration constants (`CRONJOB`, `WORKER`, `CONFIG`, `COMMANDS`, `COMMAND`, `SCHEDULE`, `SETTINGS`, `LOG`, `LOG_FILE`, `LOG_FILE_NAME`, `TEMPLATE_CLASS`, `CONF_FILES_DIR`, `LOGS_DIR`, `HEARTBEAT`, `DESTINATION_FILE`, `MINUTE`, `HOUR`, `DAY_OF_MONTH`, `MONTH`, `DAY_OF_WEEK`, `NUMBER_OF_PROCESSES`, `AUTO_START`, `AUTO_RESTART`, `PREFIX`, `USER`) switched to
  `static::` for late static binding; a subclass that overrides a configuration-key constant now has its override picked up by the tree-builder methods instead of being locked to the parent's value. The two private constants (`self::DESTINATION_DIR`, `self::NAME`) deliberately keep `self::` since private constants are not inherited and `static::` against them would fail in a subclass context
- `MemoryService::setMemoryLimitIfNotHigher()` — internal `self::returnBytes()` calls switched to `static::returnBytes()` so a subclass that overrides the parser (e.g. for custom memory-value syntax) has its override honored when the current and target limits are compared
- `MemoryService::getMemoryUsage()` — internal `self::convertBytesToHumanReadable()` switched to `static::convertBytesToHumanReadable()` for the same reason

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

[Unreleased]: https://github.com/precision-soft/symfony-console/compare/v4.7.0...HEAD

[v4.7.0]: https://github.com/precision-soft/symfony-console/compare/v4.6.0...v4.7.0

[v4.6.0]: https://github.com/precision-soft/symfony-console/compare/v4.5.0...v4.6.0

[v4.5.0]: https://github.com/precision-soft/symfony-console/compare/v4.4.0...v4.5.0

[v4.4.0]: https://github.com/precision-soft/symfony-console/compare/v4.3.0...v4.4.0

[v4.3.0]: https://github.com/precision-soft/symfony-console/compare/v4.2.11...v4.3.0

[v4.2.11]: https://github.com/precision-soft/symfony-console/compare/v4.2.10...v4.2.11

[v4.2.10]: https://github.com/precision-soft/symfony-console/compare/v4.2.9...v4.2.10

[v4.2.9]: https://github.com/precision-soft/symfony-console/compare/v4.2.8...v4.2.9

[v4.2.8]: https://github.com/precision-soft/symfony-console/compare/v4.2.7...v4.2.8

[v4.2.7]: https://github.com/precision-soft/symfony-console/compare/v4.2.6...v4.2.7

[v4.2.6]: https://github.com/precision-soft/symfony-console/compare/v4.2.5...v4.2.6

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
