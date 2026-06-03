# Task 001: Strip scope from config value objects and in-memory storage; delete OverrideMatcher and AxisNotDeclaredException

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Remove scope-related state from the foundational value objects (`ConfigDefinition`, `ConfigRow`) and the in-memory storage backend (`InMemoryConfigStorage`). Delete `Resolution/OverrideMatcher.php` and `Exceptions/AxisNotDeclaredException.php` — both relocate to `markommerce/config-scope` in task 008. After this task, `ConfigDefinition` is `{key, configClass, field, type, defaultValue, secret}` only, `ConfigRow` is `{key, value, version, updatedAt}` only, and `InMemoryConfigStorage::compareAndSave` treats `value === null` as the empty-row trigger.

## Context
- Related files:
  - `packages/config/src/ValueObjects/ConfigDefinition.php`
  - `packages/config/src/ValueObjects/ConfigRow.php`
  - `packages/config/src/Storage/InMemoryConfigStorage.php`
  - `packages/config/src/Resolution/OverrideMatcher.php` (to be deleted)
  - `packages/config/src/Exceptions/AxisNotDeclaredException.php` (to be deleted)
  - `packages/config/tests/Unit/ValueObjects/ConfigDefinitionTest.php`
  - `packages/config/tests/Unit/ValueObjects/ConfigRowTest.php`
  - `packages/config/tests/Unit/Storage/InMemoryConfigStorageTest.php`
  - `packages/config/tests/Unit/Resolution/OverrideMatcherTest.php` (delete or relocate)
  - `packages/config/tests/Unit/Exceptions/ConfigExceptionsTest.php`
- Patterns to follow: P2's analogous descope of `Markommerce\Catalog\Entity\Product` — strip imports + traits + attributes in lock-step with test updates. Use named-argument constructors throughout.
- The Tier 1 `ConfigRow`'s empty-row check moves from `value === null && overrides === []` to just `value === null`.

## Requirements (Test Descriptions)
- [ ] `it constructs ConfigDefinition with key, configClass, field, type, defaultValue, and secret but no axes property`
- [ ] `it rejects a constructor call passing an axes named argument to ConfigDefinition`
- [ ] `it constructs ConfigRow with key, value, version, and updatedAt but no overrides property`
- [ ] `it rejects withOverride and withoutOverride method calls on ConfigRow (methods removed)`
- [ ] `it keeps withGlobal and withoutGlobal methods on ConfigRow (used by ConfigWriter's writeWithRetry mutate closures; no signature change)`
- [ ] `it treats InMemoryConfigStorage compareAndSave as empty-row when value is null regardless of any pre-existing state`
- [ ] `it deletes the existing in-memory row when compareAndSave is called with value=null and the stored version matches`
- [ ] `it persists a new row with value set, version 1, when compareAndSave is called with expectedVersion=0 and a non-null value`
- [ ] `it does not include packages/config/src/Resolution/OverrideMatcher.php on the filesystem after task completes`
- [ ] `it does not include packages/config/src/Exceptions/AxisNotDeclaredException.php on the filesystem after task completes`
- [ ] `it does not include packages/config/tests/Unit/Resolution/OverrideMatcherTest.php on the filesystem after task completes`

## Acceptance Criteria
- All requirements have passing tests.
- `grep -rn "overrides\|withOverride\|withoutOverride" packages/config/src` returns no matches in production code (test fixtures may still mention `overrides` as a stored value name temporarily until other tasks complete — those are mopped up in task 004).
- `grep -rn "OverrideMatcher\|AxisNotDeclaredException" packages/config/src` returns no matches.
- PHPStan level 8 clean for the touched files.
- The pre-existing `ConfigExceptionsTest` cases that reference `AxisNotDeclaredException` are deleted from that file.
- `ConfigRowTest` and `ConfigDefinitionTest` are reduced to the descoped surface; deleted cases for `axes`/`overrides`/`withOverride` go away cleanly.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
