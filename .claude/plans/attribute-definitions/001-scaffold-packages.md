# Task 001: Scaffold `attribute` + `attribute-pgsql` packages

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the two new monorepo packages with their `composer.json`, autoload config, Pest test
bootstrap, and `src/`/`tests/` skeleton. `attribute` is the interface/kernel package;
`attribute-pgsql` is its Postgres driver. No domain logic yet — just a loadable, testable shell.

## Context
- Mirror exactly: `packages/config/composer.json` and `packages/config-pgsql/composer.json`
  (`type: marko-module`, `extra.marko.module: true`, `require-dev` pest + `marko/testing`,
  `markommerce/testing` for the pgsql package).
- Namespaces: `Markommerce\Attribute\` → `packages/attribute/src/`; tests
  `Markommerce\Attribute\Tests\` → `packages/attribute/tests/`. Driver:
  `Markommerce\Attribute\PgSql\` → `packages/attribute-pgsql/src/`.
- `attribute-pgsql/composer.json` requires `markommerce/attribute: self.version` +
  `marko/database-pgsql: self.version`.
- Copy `tests/Pest.php` shape from `packages/config/tests/Pest.php`.
- Add both packages to the root monorepo composer path repositories if required (check root
  `composer.json` for how existing packages are wired).

## Requirements (Test Descriptions)
- [x] `it autoloads a class from the Markommerce\Attribute namespace`
- [x] `it autoloads a class from the Markommerce\Attribute\PgSql namespace`
- [x] `it marks the attribute package as a marko module in composer extra`
- [x] `it marks the attribute-pgsql package as a marko module in composer extra`
- [x] `it declares markommerce/attribute as a dependency of attribute-pgsql`

## Acceptance Criteria
- Both packages autoload under their PSR-4 namespaces.
- `composer test` discovers and runs the (placeholder) test suites for both packages.
- Directory layout matches the `config`/`config-pgsql` convention.

## Implementation Notes
- Created `packages/attribute/` and `packages/attribute-pgsql/` mirroring the `config`/`config-pgsql` layout.
- `packages/attribute/src/AttributeServiceInterface.php` — minimal placeholder interface to satisfy the autoload test (`interface_exists` used since `class_exists` returns false for interfaces).
- `packages/attribute-pgsql/src/PgSqlAttributeRepository.php` — minimal placeholder class for the PgSql namespace autoload test.
- Both packages have `composer.json`, `module.php` (empty bindings array), `tests/Pest.php`, `LICENSE`, `README.md`, and `.gitattributes` matching the package-standard canonical content.
- Root `composer.json` updated: added both packages to `require` block and their test namespaces to `autoload-dev`.
- Ran `composer update markommerce/attribute markommerce/attribute-pgsql` inside the Docker container to symlink packages and update the lock file.
- `composer test` passes with 2062 tests (8 pre-existing notices, 2 pre-existing skipped).
