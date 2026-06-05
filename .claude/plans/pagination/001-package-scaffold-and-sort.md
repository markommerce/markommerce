# Task 001: Pagination package scaffold + Sort value objects

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the new `packages/criteria` Marko module (composer.json, namespace, test bootstrap) and the immutable sort value objects that describe ordering: `SortDirection`, `SortField`, and `Sort`. These are the foundation every strategy builds on.

## Context
- New package dir: `packages/criteria/` mirroring the structure of existing packages (e.g. `packages/catalog`): `src/`, `tests/Unit`, `tests/Feature`, `composer.json`, `module.php`.
- composer.json must mirror `packages/catalog/composer.json` exactly in shape: `"type": "marko-module"`, `"extra": { "marko": { "module": true } }`, namespace `Markommerce\Criteria\` (psr-4 → `src/`, tests → `Markommerce\Criteria\Tests\`), `"php": "^8.5"`, and `require` on `marko/core` (for `MarkoException`, used by the exceptions in Tasks 002/003) AND `marko/database` (for `RepositoryQueryBuilder`/`EntityCollection`), both pinned to `"self.version"`. require-dev: `marko/testing` (`self.version`) + `pestphp/pest` `^4.0`; add the `pestphp/pest-plugin` allow-plugins config block. Do NOT depend on any markommerce commerce module — the engine is standalone and entity-agnostic.
- Sort value objects live in `packages/criteria/src/Sort/` (namespace `Markommerce\Criteria\Sort`).
- Patterns: `declare(strict_types=1)`, no `final`, `readonly class`, explicit constant types, no magic methods.

## Requirements (Test Descriptions)
- [x] `it exposes ascending and descending sort directions`
- [x] `it creates a sort field with a column and default ascending direction`
- [x] `it creates a sort field with an explicit descending direction`
- [x] `it builds a sort from an ordered list of sort fields`
- [x] `it preserves the order of sort fields`
- [x] `it rejects a sort with no fields with a loud exception`

## Acceptance Criteria
- `packages/criteria` is discoverable as a Marko module and autoloads under `Markommerce\Criteria\`.
- All requirements have passing tests.
- Code follows code standards (strict types, readonly, no final).

## Implementation Notes
- Created `packages/criteria/` as a full Marko module mirroring `packages/catalog/` conventions.
- `SortDirection` is a backed string enum with `Ascending = 'ASC'` and `Descending = 'DESC'`.
- `SortField` is a `readonly class` with `public string $column` and `public SortDirection $direction = SortDirection::Ascending`.
- `Sort` is a `readonly class` accepting variadic `SortField ...$fields`, stored as `list<SortField>` in `$this->fields`. Throws `EmptySortException` when constructed with no fields. (`SortDirection`, `SortField`, `Sort` live at `packages/criteria/src/Sort/<Name>.php`, namespace `Markommerce\Criteria\Sort`.)
- `EmptySortException` extends `MarkoException` with a `create()` static factory (`packages/criteria/src/Exceptions/EmptySortException.php`, namespace `Markommerce\Criteria\Exceptions`).
- Added `markommerce/criteria` to root `composer.json` require and autoload-dev entries.
- Also added required package standard files: `LICENSE`, `.gitattributes`, `README.md`.
