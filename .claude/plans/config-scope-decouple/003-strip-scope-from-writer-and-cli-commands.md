# Task 003: Strip scope from ConfigWriter, ConfigWriterInterface, and CLI commands

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove `setOverride(...)` and `unsetOverride(...)` from `ConfigWriterInterface` and `ConfigWriter`. Drop the `--scope=axis=value,…` option, `parseScope()` helper, and `ScopeSignature` import from `SetCommand` and `UnsetCommand`. Drop `ScopeRegistryInterface`, the ad-hoc `OverrideMatcher`/`SignatureCandidateEnumerator` wiring, and the `buildContext()` helper from `ConfigGetCommand`; the command now calls `resolver->resolved(...)` directly. `ConfigListCommand` already has no scope coupling beyond the imports — remove those imports.

**Critical visibility prep for Tier 2 subclassing:** relax `ConfigWriter`'s constructor-promoted properties (`$registry`, `$storage`, `$cipher`) from `private` to `protected` so `ScopedConfigWriter` (task 010) can read `$this->registry->byKey(...)`, call `$this->cipher->encrypt(...)`, and persist via `$this->storage`. Also relax `ConfigWriter::writeWithRetry()` if `ScopedConfigWriter` needs to compose retry behavior; otherwise leave private.

**Empty-row semantics in ConfigWriter:** the `writeWithRetry` helper today builds a default `ConfigRow(key, value: null, overrides: [], version: 0)`. After task 001 dropped `overrides` from `ConfigRow`, the constructor call here must be updated to `new ConfigRow(key: $key, value: null, version: 0)`. PHPStan level 8 will catch a stale `overrides` argument.

## Context
- Related files:
  - `packages/config/src/ConfigWriter.php`
  - `packages/config/src/Contracts/ConfigWriterInterface.php`
  - `packages/config/src/Command/SetCommand.php`
  - `packages/config/src/Command/UnsetCommand.php`
  - `packages/config/src/Command/ConfigGetCommand.php`
  - `packages/config/src/Command/ConfigListCommand.php`
  - `packages/config/tests/Unit/ConfigWriterTest.php`
  - `packages/config/tests/Unit/Command/SetCommandTest.php`
  - `packages/config/tests/Unit/Command/UnsetCommandTest.php`
  - `packages/config/tests/Unit/Command/ConfigGetCommandTest.php`
  - `packages/config/tests/Unit/Command/ConfigListCommandTest.php`
- Patterns to follow: `ConfigWriter::writeWithRetry` keeps its retry semantics; only the override mutation closures get removed. After this task, `ConfigWriter::setGlobal` and `unsetGlobal` are the only public mutation methods.
- All scope-aware test cases relocate in task 010 (writer) and task 010 again (commands). Delete them from the listed files here.

## Requirements (Test Descriptions)
- [x] `it declares only setGlobal and unsetGlobal methods on ConfigWriterInterface after task completes`
- [x] `it does not declare a setOverride or unsetOverride method on ConfigWriterInterface`
- [x] `it persists a new global value via ConfigWriter setGlobal when the key has no existing row`
- [x] `it replaces an existing global value via ConfigWriter setGlobal preserving version increments`
- [x] `it removes the global value via ConfigWriter unsetGlobal`
- [x] `it bumps the row version after a successful write to ConfigWriter`
- [x] `it retries up to 3 times on compareAndSave conflict before throwing StaleConfigWriteException from ConfigWriter`
- [x] `it throws ConfigNotFoundException from ConfigWriter setGlobal when the key is not in the registry`
- [x] `it ignores any --scope option passed to SetCommand execute and treats the call as a global write (descoped command no longer reads the option)`
- [x] `it sets a global value via the SetCommand without parsing any scope option`
- [x] `it ignores any --scope option passed to UnsetCommand execute and treats the call as a global unset`
- [x] `it unsets a global value via the UnsetCommand without parsing any scope option`
- [x] `it declares ConfigWriter's constructor-promoted properties (registry, storage, cipher) with protected visibility (verified via reflection)`
- [x] `it resolves the global value via ConfigGetCommand by calling resolver.resolved directly (no synthetic ScopeContext)`
- [x] `it does not inject a ScopeRegistryInterface into ConfigGetCommand's constructor after task completes`
- [x] `it does not import Markommerce\Scope\... namespaces from any of the touched production files after task completes`

## Acceptance Criteria
- All requirements have passing tests.
- `grep -rn "Markommerce\\Scope\|ScopeSignature\|ScopeContext" packages/config/src/ConfigWriter.php packages/config/src/Contracts/ConfigWriterInterface.php packages/config/src/Command/` returns zero matches.
- PHPStan level 8 clean for all touched files.
- The pre-existing `SetCommand`/`UnsetCommand` `parseScope()` private methods are deleted.
- `ConfigGetCommand` no longer constructs `ConfigResolver` ad-hoc — it accepts one via constructor injection. (Note: with the descope, `ConfigGetCommand` can either take a `ConfigResolver` directly, or keep building it ad-hoc using the descoped constructor. Pick the simpler option and document.)

## Implementation Notes
- `ConfigWriterInterface` and production commands were already descoped by Task 001; only the test files needed rewriting.
- Changed `ConfigWriter` constructor-promoted properties from `private` to `protected` for Tier-2 subclassing.
- Rewrote all five test files to remove scope fixtures/helpers and add the new requirement tests.
- `ConfigGetCommand` continues to build `ConfigResolver` ad-hoc (simpler option; no constructor injection needed).
- PHPStan level 8 clean; zero Scope namespace references in all touched production files.
