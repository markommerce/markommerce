# Task 009: Adapt docs/src/content/docs/packages/scope-pgsql.md for Markommerce

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Copy `/home/michal/www/marko/marko/docs/src/content/docs/packages/scope-pgsql.md` into `/home/michal/www/marko/markommerce/docs/src/content/docs/packages/scope-pgsql.md`, rewriting it to refer to `markommerce/scope-pgsql` and the `Markommerce\Scope\PgSql\…` namespace. Drop the `scope-mysql` cross-link in Related Packages. Keep external cross-links to upstream `marko/database-pgsql` docs pointing at the marko.build docs site.

## Context

- Source: `/home/michal/www/marko/marko/docs/src/content/docs/packages/scope-pgsql.md`.
- Target: `/home/michal/www/marko/markommerce/docs/src/content/docs/packages/scope-pgsql.md`.
- Same standards/style as Task 008.
- Rewrites:
  - Frontmatter `title: marko/scope-pgsql` → `title: markommerce/scope-pgsql`.
  - Description: `PostgreSQL driver for marko/scope` → `PostgreSQL driver for markommerce/scope`.
  - Intro paragraph: `marko/scope` → `markommerce/scope`, `marko/scope-pgsql` → `markommerce/scope-pgsql`.
  - Installation: `composer require marko/scope-pgsql` → `composer require markommerce/scope-pgsql`. The "automatically installs `marko/scope`" line → "automatically installs `markommerce/scope`".
  - Code examples: `Marko\Scope\` → `Markommerce\Scope\` (this includes `Marko\Scope\Attributes\Scoped`, `Marko\Scope\Storage\HasScopes`, `Marko\Scope\Storage\HasScopesInterface`, `Marko\Scope\Context\ScopeContext`, `Marko\Scope\Query\ScopedOrderByFactory`). Keep `Marko\Database\…` references.
  - API Reference: `PgSqlScopeSortRenderer` — keep, this is the renamed `Markommerce\Scope\PgSql\Query\PgSqlScopeSortRenderer`. The table doesn't currently include the FQCN so no rewrite needed there, but if the FQCN is referenced anywhere, update.
  - Related Packages: rewrite as:
    - `[markommerce/scope](/docs/packages/scope/) — Core scoped attributes package`
    - `[marko/database-pgsql](https://marko.build/docs/packages/database-pgsql/) — PostgreSQL database driver`
    - REMOVE the `marko/scope-mysql` entry entirely.

## Requirements (Test Descriptions)

- [ ] `it copies marko docs/scope-pgsql.md to markommerce docs/packages/scope-pgsql.md`
- [ ] `it has frontmatter title: markommerce/scope-pgsql`
- [ ] `it has an Installation section with composer require markommerce/scope-pgsql`
- [ ] `it has zero references to scope-mysql in the markommerce scope-pgsql.md`
- [ ] `it uses Markommerce\Scope\ and Markommerce\Scope\PgSql\ namespaces in every code example`
- [ ] `it preserves Marko\Database\ references in code examples`
- [ ] `it links to markommerce/scope as the parent package`
- [ ] `it links to marko/database-pgsql externally at marko.build`

## Acceptance Criteria

- File exists at the target path.
- Grep for `marko/scope-mysql` and `marko/scope` (the latter except inside `markommerce/scope`) returns zero matches in the new file.
- Grep for `Marko\\Scope\\` returns zero matches.
- Grep for `Marko\\Database\\` returns at least one match (the unchanged upstream dep).
- File conforms to DOCS-STANDARDS.md package page structure.

## Implementation Notes
(Left blank — filled in during implementation)
