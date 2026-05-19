# Changelog

All notable changes to `markommerce/scope-pgsql` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Auto-emitted `jsonb_path_ops` GIN index on the `scopes` column for every table using `HasScopes`. The index name follows the pattern `<table>_scopes_gin` (e.g. `products_scopes_gin`). Emitted via a side-channel `CREATE INDEX IF NOT EXISTS` statement — no manual migration is needed.
- Real-Postgres integration test suite (`composer test:all`). Requires the compose Postgres service to be running. Tests are marked destructive and are excluded from the default `composer test` run.

### Changed

- **BREAKING**: `PgSqlScopeSortRenderer` removed; replaced with `PgSqlScopedFieldRenderer` implementing `ScopedFieldRendererInterface` from `markommerce/scope`.
- `ScopedOrderBy` now emits COALESCE chains over composite-signature JSONB keys in descending-score order, replacing the old per-axis walk-up emission.

### Not Shipped

- `ScopedSelect` and `ScopedWhere` are **not included** in this release. They require `selectRaw` / `whereRaw` support on `marko/database`'s `QueryBuilderInterface`, which does not exist today. Deferred to a follow-up plan.
