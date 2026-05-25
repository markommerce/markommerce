# Task 009: `ConfigWriter`

**Status**: completed
**Depends on**: 005, 006, 012
**Retry count**: 0

## Description
Implement `ConfigWriterInterface` + a default `ConfigWriter` that handles all merchant-facing writes: setting/unsetting global values and setting/unsetting per-scope overrides. Wraps every write in a load → mutate → compareAndSave loop with a bounded retry count (3) for optimistic-lock contention. Validates at write time that the override's signature axes match those declared on the target property — rejecting orphan overrides loudly.

## Context
- Interface methods: `setGlobal(string $key, mixed $value): void`, `unsetGlobal(string $key): void`, `setOverride(string $key, ScopeSignature $signature, mixed $value): void`, `unsetOverride(string $key, ScopeSignature $signature): void`
- **`null` is `unset`**: `setGlobal($key, null)` is semantically and structurally identical to `unsetGlobal($key)` — both produce the same row mutation (drop the global). Likewise `setOverride($key, $sig, null)` ≡ `unsetOverride($key, $sig)`. Both API methods are retained for clarity at call sites, but the impl normalizes through one internal code path. Consequence: nullable configs cannot persist an explicit null distinct from "no global set" — null reads always fall back to the property's PHP default.
- All four resolve the `ConfigDefinition` via the injected `ConfigRegistry` (throws `ConfigNotFoundException` for unknown keys)
- `setOverride` validates each axis name in `$signature` against `$definition->axes` — if any axis is not declared on the property, throw `AxisNotDeclaredException`
- The write loop: read existing row (or build a blank one with version 0), apply `withGlobal` / `withOverride` / etc., call `storage->compareAndSave(...)`. If false, sleep zero (no jitter needed for v1), reload, retry up to 3 times. If still failing, throw `StaleConfigWriteException`.
- When mutation results in an empty row (no global, no overrides), `compareAndSave` deletes per task 006 contract — writer should not special-case this
- When unsetting an already-absent override against an absent row, task 006's idempotent no-op rule (compareAndSave returns true) prevents the retry loop from exhausting
- **Constructor**: declares `SecretCipherInterface` as a dependency now (with a `NullSecretCipher` stub binding from task 012). Behavior is NOT exercised in this task — task 013 adds the secret-branch logic. The ctor signature does NOT change between this task and task 013.

## Requirements (Test Descriptions)
- [x] `it persists a new global value via setGlobal when the key has no existing row`
- [x] `it replaces an existing global value via setGlobal preserving any per-scope overrides on the same key`
- [x] `it removes the global value via unsetGlobal while keeping per-scope overrides intact`
- [x] `it persists a new per-scope override via setOverride keyed by the signature string`
- [x] `it replaces an existing per-scope override for the same signature`
- [x] `it removes a specific per-scope override via unsetOverride leaving other overrides untouched`
- [x] `it throws ConfigNotFoundException when writing to a key that no ConfigDefinition exists for`
- [x] `it throws AxisNotDeclaredException when setOverride's signature uses an axis not declared on the property`
- [x] `it retries up to 3 times on compareAndSave version conflict before throwing StaleConfigWriteException`
- [x] `it bumps the row version after a successful write (visible via subsequent load)`
- [x] `it returns successfully when unsetOverride is called for a signature that was never set (idempotent no-op via storage contract)`
- [x] `it produces an identical row mutation when setGlobal is called with null as when unsetGlobal is called`
- [x] `it produces an identical row mutation when setOverride is called with null as when unsetOverride is called`
- [x] `it accepts SecretCipherInterface as a constructor dependency but does NOT invoke it for non-secret writes in this task's tests`

## Acceptance Criteria
- `ConfigWriterInterface` defines all four methods listed above
- `ConfigWriter` is the default impl, non-`final`
- Tests use `InMemoryConfigStorage` (task 006's real fake) — not mocks
- For the retry-exhaustion test, use a `ContentiousStorage` test double that fails N times then succeeds (or always fails)
- PHPStan level 8 clean
- All `@throws` tags accurate on both interface and impl

## Implementation Notes
(Left blank — filled in by programmer)
