# Task 005: Scaffold the markommerce/catalog package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the empty `markommerce/catalog` package skeleton — composer.json, directory structure, and autoload registration — ready for entities, repositories, services, and events to land in later tasks. This task explicitly does **not** add `module.php` (created in task 017 once there are bindings to register).

## Context
- Working directory: `packages/catalog/`
- Mirror the structure of `marko/admin-auth` and the markommerce architecture doc:
  ```
  packages/catalog/
    composer.json
    src/
      Entity/
      Repository/
      Service/
      Exception/
      Event/
    database/migrations/
    tests/Unit/
    tests/Feature/
  ```
- `composer.json` must:
  - declare `"name": "markommerce/catalog"`, `"type": "marko-module"` (required — matches admin-auth precedent), MIT license
  - require `php: ^8.5`, `markommerce/money: self.version` (interface package only — **never** `markommerce/money-moneyphp`, the driver), `marko/database: self.version`, `marko/core: self.version`
  - require-dev `pestphp/pest: ^4.0`, `marko/testing: self.version`. **No `marko/database-mysql`** here — the schema integration test that needs it lives at the monorepo root (`tests/Feature/Schema/CatalogSchemaIntegrationTest.php`, per task 011), not in this package. Catalog stays DB-driver-free at every level.
  - autoload `Markommerce\Catalog\` → `src/`
  - autoload-dev `Markommerce\Catalog\Tests\` → `tests/` (this PSR-4 entry covers `tests/Unit/`, `tests/Feature/`, AND `tests/Support/`)
  - declare `"extra": { "marko": { "module": true } }`
  - allow `pestphp/pest-plugin` in `config.allow-plugins`
- **Architecture rule**: this package depends only on the `markommerce/money` interface package. Per the architecture doc, "Modules must never depend on another module's implementation (driver) package." The `markommerce/money-moneyphp` driver is wired into the application by the root metapackage (task 003), not here.
- The root `markommerce/markommerce` composer.json must add `markommerce/catalog: self.version` to its `require` block so the package is wired into the monorepo install.
- No SQL migration files in this package — the schema lives on the entities via attributes (tasks 006–008) and is generated/applied by Marko's `db:migrate` in the consuming application. The `database/migrations/` directory is **not** part of this package's scaffold.
- Directory scaffold to create:
  ```
  packages/catalog/
    composer.json
    src/
      Entity/
      Repository/
      Service/
      Exception/
      Event/
    tests/Unit/
    tests/Feature/
    tests/Support/        # Shared fakes used by service tests; created incrementally by the tasks that first need them:
                          #   FakeConnection           — task 011 (Product repository unit tests)
                          #   FakeMoney + FakeMoneyFactory — task 013 (ProductPriceService tests)
                          #   FakeCategoryRepository   — task 014 (CategoryService tests)
                          #   FakeProductRepository + FakeCurrencyConfig — task 015 (ProductService tests)
                          #   RecordingEventDispatcher — first service task that needs it (likely 014)
                          # Tasks 016 and later reuse, never duplicate.
  ```
- No `module.php` in this task — the file is created later when there are real bindings (task 017). Marko's discovery is keyed on the `extra.marko.module` flag, not on the file's existence.

## Requirements (Test Descriptions)
- [ ] `it creates a composer.json for markommerce/catalog with type marko-module and the marko module flag`
- [ ] `it declares the catalog autoload namespace as Markommerce\Catalog`
- [ ] `it declares the test autoload namespace as Markommerce\Catalog\Tests covering tests/`
- [ ] `it requires php 8.5, markommerce/money interface package, marko/database, and marko/core`
- [ ] `it does not require markommerce/money-moneyphp directly per the architecture interface/driver rule`
- [ ] `it requires pestphp/pest and marko/testing as dev dependencies and does not require marko/database-mysql`
- [ ] `it is wired into the root composer.json require block`
- [ ] `it creates the required src tests Unit Feature and Support directory structure`
- [ ] `it does not create a database migrations directory because schema is declared via entity attributes`

## Acceptance Criteria
- `composer install` inside the Docker container resolves with no errors and produces a `vendor/markommerce/catalog` symlink.
- `composer dump-autoload` produces no warnings about the new package.
- `phpunit.xml` at the repo root already picks up `packages/*/tests` (verify, no change expected); if not, this task must add the testsuite entry.
- Tests for this task assert the JSON shape of the new `composer.json` (read with `json_decode`) and that every directory listed above exists — they belong in `packages/catalog/tests/Unit/PackageStructureTest.php`.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
