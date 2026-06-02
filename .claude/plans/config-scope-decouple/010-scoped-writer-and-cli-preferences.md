# Task 010: Implement ScopedConfigWriter + ScopedConfigWriterInterface and scope-aware CLI command Preferences

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Add `Markommerce\\ConfigScope\\Contracts\\ScopedConfigWriterInterface` extending `Markommerce\\Config\\Contracts\\ConfigWriterInterface` with `setOverride(string $key, ScopeSignature $signature, mixed $value): void` and `unsetOverride(string $key, ScopeSignature $signature): void` methods. Add `Markommerce\\ConfigScope\\ScopedConfigWriter` extending `Markommerce\\Config\\ConfigWriter` and implementing the new interface; carry `#[Preference(replaces: ConfigWriter::class)]`. The writer validates signature axes against `ScopedFieldRegistry::axesForProperty(...)` and persists overrides via `ScopedConfigStorageInterface::saveOverride` / `deleteOverride`. Re-introduce `--scope=axis=value,…` parsing for the CLI by Preferences-replacing the three Tier 1 commands with `ScopedSetCommand`, `ScopedUnsetCommand`, and `ScopedConfigGetCommand`. Each extends its descoped Tier 1 parent and overrides `execute()`. Module manifest binds `ConfigWriterInterface => ScopedConfigWriter` so consumers typed against the base interface still get the scope-aware implementation.

**Critical command-discovery gotcha:** `Marko\Core\Command\CommandDiscovery` reflects classes carrying `#[Command]` and feeds them to `CommandRegistry::register()`, which throws `duplicateCommandName` on a name collision. The three Scoped commands therefore MUST NOT carry their own `#[Command(name: …)]` attribute — that would clash with the parent's. They carry ONLY `#[Preference(replaces: \Markommerce\Config\Command\SetCommand::class)]` etc. `CommandDiscovery` finds the parent's `#[Command(name: 'config:set')]` and registers `commandClass: \Markommerce\Config\Command\SetCommand`. At runtime, `CommandRunner::run('config:set', …)` calls `$container->get(SetCommand::class)`, the container's preference lookup swaps to `ScopedSetCommand`, and the Scoped variant's `execute()` runs.

**ScopedConfigGetCommand typing:** the parent `ConfigGetCommand` (per task 003) does not depend on `ConfigResolver` directly. `ScopedConfigGetCommand` must inject the resolver explicitly to access `resolvedAt(...)`. The constructor type-hint should be `Markommerce\ConfigScope\ScopedConfigResolver $resolver` (NOT the base `ConfigResolver`, which has no `resolvedAt` after task 002). The container hands back the right instance because the Preference + factory binding in task 009 make `ScopedConfigResolver` reachable via auto-wiring of the concrete class.

## Context
- Related files (new):
  - `packages/config-scope/src/Contracts/ScopedConfigWriterInterface.php`
  - `packages/config-scope/src/ScopedConfigWriter.php`
  - `packages/config-scope/src/Command/ScopedSetCommand.php`
  - `packages/config-scope/src/Command/ScopedUnsetCommand.php`
  - `packages/config-scope/src/Command/ScopedConfigGetCommand.php`
  - `packages/config-scope/tests/Unit/ScopedConfigWriterTest.php`
  - `packages/config-scope/tests/Unit/Command/ScopedSetCommandTest.php`
  - `packages/config-scope/tests/Unit/Command/ScopedUnsetCommandTest.php`
  - `packages/config-scope/tests/Unit/Command/ScopedConfigGetCommandTest.php`
- Related (read-only):
  - `packages/config/src/ConfigWriter.php` (parent)
  - `packages/config/src/Command/SetCommand.php`, `UnsetCommand.php`, `ConfigGetCommand.php` (parents)
  - `packages/scope/src/Signature/ScopeSignature.php`
- Patterns to follow: `Markommerce\Config\Tests\Unit\ConfigWriterTest`'s relocated cases inform the writer's behaviour (override persist/replace/remove, AxisNotDeclaredException on undeclared axis, idempotent unset). Commands extend parents and override `execute()` only.

## Requirements (Test Descriptions)
- [x] `it declares setOverride and unsetOverride methods on ScopedConfigWriterInterface that extend ConfigWriterInterface`
- [x] `it carries #[Preference(replaces: ConfigWriter::class)] on ScopedConfigWriter`
- [x] `it extends Markommerce\Config\ConfigWriter and implements ScopedConfigWriterInterface`
- [x] `it persists a new per-scope override via ScopedConfigWriter setOverride keyed by the signature string`
- [x] `it replaces an existing per-scope override for the same signature on a second setOverride call`
- [x] `it removes a specific per-scope override via ScopedConfigWriter unsetOverride leaving other overrides untouched`
- [x] `it accepts SecretCipherInterface but does NOT invoke it for non-secret writes`
- [x] `it encrypts secret values via SecretCipher before persisting an override`
- [x] `it throws AxisNotDeclaredException when setOverride's signature uses an axis not registered for the property via ScopedFieldRegistry`
- [x] `it throws ConfigNotFoundException when ScopedConfigWriter setOverride is called for a key not in the registry`
- [x] `it carries #[Preference(replaces: SetCommand::class)] on ScopedSetCommand`
- [x] `it does NOT carry a #[Command] attribute on ScopedSetCommand (parent's #[Command(name: 'config:set')] is the single source of command-name registration; a duplicate attribute would cause CommandRegistry::register to throw duplicateCommandName)`
- [x] `it parses --scope=axis=value into a ScopeSignature inside ScopedSetCommand execute`
- [x] `it calls writer.setOverride when --scope is provided and writer.setGlobal otherwise from ScopedSetCommand`
- [x] `it carries #[Preference(replaces: UnsetCommand::class)] on ScopedUnsetCommand and calls writer.unsetOverride when --scope is provided`
- [x] `it does NOT carry a #[Command] attribute on ScopedUnsetCommand`
- [x] `it carries #[Preference(replaces: ConfigGetCommand::class)] on ScopedConfigGetCommand and builds a synthetic ScopeContext from --scope=axis=value`
- [x] `it does NOT carry a #[Command] attribute on ScopedConfigGetCommand`
- [x] `it returns the resolved override value from ScopedConfigGetCommand when --scope matches a persisted override`
- [x] `it type-hints ScopedConfigResolver (not the base ConfigResolver) on ScopedConfigGetCommand's constructor parameter so resolvedAt(...) is statically accessible`

## Acceptance Criteria
- All requirements have passing tests.
- The Preference attributes on the three command classes mean a container `get('config:set')` (or however commands are looked up) returns the Scoped variants when config-scope is booted.
- PHPStan level 8 clean for new files.
- `OverrideMatcher`'s signature change (now takes `array` instead of `ConfigRow`) is reflected in the writer's call-site; tests assert the round-trip.

## Implementation Notes
- Created `ScopedConfigWriterInterface` extending `ConfigWriterInterface` with `setOverride()` and `unsetOverride()` methods
- Created `ScopedConfigWriter` extending `ConfigWriter` with `#[Preference(replaces: ConfigWriter::class)]`, validates axes against `ScopedFieldRegistry`, encrypts secrets, delegates to `ScopedConfigStorageInterface`
- Created `ScopedSetCommand` with `#[Preference(replaces: SetCommand::class)]` (no `#[Command]` attribute), parses `--scope=axis=value,...` into `ScopeSignature`, calls `setOverride()` or `setGlobal()` accordingly
- Created `ScopedUnsetCommand` with `#[Preference(replaces: UnsetCommand::class)]` (no `#[Command]` attribute), parses `--scope` and calls `unsetOverride()` or `unsetGlobal()`
- Created `ScopedConfigGetCommand` with `#[Preference(replaces: ConfigGetCommand::class)]` (no `#[Command]` attribute), type-hints `ScopedConfigResolver $resolver`, builds synthetic `ScopeContext` by calling `in()` on the injected context, calls `resolvedAt()`, then `clearAll()`
- PHPStan level 8 clean; CS fixer clean; 62 tests passing
