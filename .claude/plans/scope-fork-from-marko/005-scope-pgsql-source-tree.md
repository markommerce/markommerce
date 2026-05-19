# Task 005: Copy & Rename markommerce/scope-pgsql Source Tree

**Status**: pending
**Depends on**: 002, 004
**Retry count**: 0

## Description
Copy every `src/**/*.php` from `marko/packages/scope-pgsql/src/` into `markommerce/packages/scope-pgsql/src/`, rewriting `Marko\Scope\PgSql\` → `Markommerce\Scope\PgSql\` and any references to `Marko\Scope\…` (the parent package) → `Markommerce\Scope\…`. Marko\Database\…, Marko\Database\PgSql\…, Marko\Core\… imports are preserved. The rename is mechanical — no API changes.

## Context

- Source files to copy (under `/home/michal/www/marko/marko/packages/scope-pgsql/src/`):
  - `Query/PgSqlScopeSortRenderer.php` (the only source file in the upstream package)
- Rename rules:
  - `namespace Marko\Scope\PgSql` → `namespace Markommerce\Scope\PgSql`
  - `use Marko\Scope\PgSql\…` → `use Markommerce\Scope\PgSql\…`
  - `use Marko\Scope\…` (parent package) → `use Markommerce\Scope\…`
  - Leave alone: `use Marko\Database\…`, `use Marko\Database\PgSql\…`, `use Marko\Core\…`
- The class implements `Markommerce\Scope\Query\ScopeSortRendererInterface` (after rename) — verify that interface exists in `packages/scope/src/Query/` (created by Task 002).
- Keep `declare(strict_types=1);` and do not introduce `final`.

## Requirements (Test Descriptions)

- [ ] `it autoloads Markommerce\Scope\PgSql\Query\PgSqlScopeSortRenderer without a fatal error`
- [ ] `it has no remaining Marko\\Scope\\ references in packages/scope-pgsql/src/` (both single-backslash and double-backslash forms)
- [ ] `it preserves Marko\Database\, Marko\Database\PgSql\, Marko\Core\ imports unchanged`
- [ ] `it keeps declare(strict_types=1) at the top of every src file`
- [ ] `PgSqlScopeSortRenderer implements Markommerce\Scope\Query\ScopeSortRendererInterface`

## Acceptance Criteria

- Target file `packages/scope-pgsql/src/Query/PgSqlScopeSortRenderer.php` exists with the renamed namespace.
- `composer dump-autoload` succeeds.
- The scaffolding tests from Task 004 keep passing.
- No `final` class.

## Implementation Notes
(Left blank — filled in during implementation)
