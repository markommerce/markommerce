# Task 006: `ConfigStorageInterface` + `InMemoryConfigStorage`

**Status**: completed
**Depends on**: 002, 004
**Retry count**: 0

## Description
Define the driver contract for config persistence (`ConfigStorageInterface`) and ship a fully-functional in-memory implementation (`InMemoryConfigStorage`) for testing, bootstrap, and dev environments. The interface is the contract the pgsql driver (task 023) will implement.

## Context
- The interface must support: loading one row by key, loading many keys in one batch (for warming caches), and a `compareAndSave` operation that powers the optimistic-locking writer.
- `compareAndSave` returns a bool: `true` if the row's stored version matched `expectedVersion` and the new row was persisted (with version bumped by 1), `false` if version mismatch (writer retries).
- `InMemoryConfigStorage` is not a mock — it's a real fake suitable for production use in single-process testing setups. It maintains a `array<string, ConfigRow>` keyed by config key.
- Methods to support `unset` semantics: if `ConfigRow->value === null` AND `overrides === []`, `compareAndSave` deletes the row outright.
- Idempotent no-op rule: if the row would be empty (`value === null && overrides === []`) AND the row does not currently exist in storage AND `expectedVersion === 0`, `compareAndSave` returns `true` as a no-op success (nothing to delete; the desired end state is already true). This prevents the writer's retry loop from looping 3× then throwing `StaleConfigWriteException` when a caller unsets an already-absent override.

## Requirements (Test Descriptions)
- [x] `it returns null from load when the key is not stored`
- [x] `it returns the row from load after a compareAndSave with expectedVersion 0`
- [x] `it bumps version by one on each successful compareAndSave`
- [x] `it returns false from compareAndSave when expectedVersion does not match the stored version`
- [x] `it leaves the stored row unchanged after a failed compareAndSave`
- [x] `it returns multiple rows from loadMany in a single call`
- [x] `it omits keys absent from storage in the loadMany result map`
- [x] `it deletes the row when compareAndSave persists a row with null value and empty overrides`
- [x] `it returns true from compareAndSave as a no-op when persisting an empty row against an absent key with expectedVersion 0`

## Acceptance Criteria
- `ConfigStorageInterface` defines: `load(string $key): ?ConfigRow`, `loadMany(list<string> $keys): array<string, ConfigRow>`, `compareAndSave(string $key, ConfigRow $row, int $expectedVersion): bool`
- `InMemoryConfigStorage` implements the interface fully (no thrown "not implemented" stubs)
- PHPStan level 8 clean
- All methods have explicit `@throws` tags (or omit when no exceptions propagate)

## Implementation Notes
(Left blank — filled in by programmer)
