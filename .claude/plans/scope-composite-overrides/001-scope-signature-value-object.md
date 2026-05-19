# Task 001: ScopeSignature Value Object

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Introduce `ScopeSignature` — the canonical immutable representation of a composite scope key. A signature is a set of `{axis: value}` pairs, alphabetically normalized when serialized so the same logical signature always produces the same string. Replaces both the old single-axis `Scope` (which will be deleted in task 006) and the ad-hoc string keys in storage.

This is the foundational value type for the entire refactor. It MUST be allocation-cheap (precompute `toString` once in the constructor) and deterministic.

## Context
- Replaces `packages/scope/src/Scope.php` (which will be deleted in task 006 — DO NOT delete it in this task).
- The new file lives at `packages/scope/src/Signature/ScopeSignature.php` (new directory).
- Storage signature spec (from `_plan.md`):
  - Axes alphabetically sorted, joined by `|`, each axis as `name:value`.
  - Examples: `channel:b2b`, `locale:es`, `channel:b2b|locale:es`, `channel:b2b|locale:es|market:eu.es`.
- Must support: construction from array, parsing from string (round-trip), equality, `hasAxis`, `get`.
- Must be `readonly class` (project standard: prefer `readonly class` when all properties are immutable; CLAUDE.md §4).
- Must NOT be `final` (project standard: no `final` classes; CLAUDE.md §5).
- Must declare `strict_types=1` and a `@throws` PHPDoc tag on every throwing method (CLAUDE.md §1, §8).
- New exception lives at `packages/scope/src/Exceptions/InvalidSignatureException.php`, extending `Marko\Core\Exceptions\MarkoException` with named `message` / `context` / `suggestion` (project Exception Standards).

## Requirements (Test Descriptions)

- [x] `it constructs a signature from an associative array of axis to value`
- [x] `it stores axes alphabetically sorted regardless of input array order`
- [x] `it serializes to the canonical pipe-separated string with axes alphabetically ordered`
- [x] `it round-trips fromString through toString to the same canonical string`
- [x] `it round-trips a single-axis signature through fromString and toString`
- [x] `it round-trips a three-axis signature through fromString and toString`
- [x] `it produces equal signatures from equivalent inputs regardless of array order`
- [x] `it considers two signatures with different axes or values unequal`
- [x] `it returns true from hasAxis when the axis is present`
- [x] `it returns false from hasAxis when the axis is absent`
- [x] `it returns the value for a present axis via get`
- [x] `it returns null from get when the axis is absent`
- [x] `it returns the alphabetically sorted axes list via axes`
- [x] `it throws InvalidSignatureException when constructed with an empty array`
- [x] `it throws InvalidSignatureException when an axis name is empty`
- [x] `it throws InvalidSignatureException when an axis value is empty`
- [x] `it throws InvalidSignatureException when fromString receives a malformed string`
- [x] `it throws InvalidSignatureException when fromString receives an empty string`
- [x] `it throws InvalidSignatureException when fromString receives a signature with duplicate axes (concrete: "locale:es|locale:de")`
- [x] `it throws InvalidSignatureException when fromString receives a part containing more than one colon (concrete: "locale:es:extra")`
- [x] `it precomputes toString once in the constructor (no recomputation on repeated calls)`

## Acceptance Criteria
- All requirements have passing tests.
- `ScopeSignature` is a `readonly class`, not `final`.
- `toString()` returns the precomputed value; the property is set in the constructor.
- `fromString` and `toString` are exact inverses for any valid signature.
- `InvalidSignatureException` extends `MarkoException` with `message`, `context`, `suggestion` named params and static factory methods.
- `phpcs`, `php-cs-fixer --dry-run`, and `phpstan` (level 8) are all clean for the new files.

## Implementation Notes
- `ScopeSignature` lives at `packages/scope/src/Signature/ScopeSignature.php` as a `readonly class` (not `final`).
- The `$serialized` string is precomputed once in the constructor via `ksort` + `implode(array_map(...))`.
- `InvalidSignatureException` lives at `packages/scope/src/Exceptions/InvalidSignatureException.php` with static factory methods: `emptyArray`, `emptyAxis`, `emptyValue`, `emptyString`, `malformedString`, `duplicateAxis`.
- `fromString` validates: non-empty input, exactly one colon per part, no duplicate axes — then delegates to the constructor which re-validates and sorts.
- The `axes()` method returns `array_keys($this->axes)` which is already alphabetically sorted.
- All 21 tests pass. `phpcs`, `php-cs-fixer --dry-run`, and `phpstan` level 8 are clean.
