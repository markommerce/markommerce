# Task 004: Merge `attribute-pgsql` → `attribute`

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Fold the `markommerce/attribute-pgsql` driver into `markommerce/attribute` and delete it. This driver is
referenced by the most cross-package Feature-test profiles, so repoint them all.

## Context
- Depends on 003 only for serialization on root `composer.json`.
- Driver src to MOVE (namespace `Markommerce\Attribute\PgSql\` unchanged → `packages/attribute/src/PgSql/`):
  - `packages/attribute-pgsql/src/PgSqlAttributeDefinitionRepository.php`
  - `packages/attribute-pgsql/src/PgSqlAttributeOptionRepository.php`
- Binding to fold into `packages/attribute/module.php` (exists):
  `AttributeDefinitionRepositoryInterface::class => PgSqlAttributeDefinitionRepository::class`.
  RESOLVED (VERIFIED — do NOT chase a second binding): there is NO `AttributeOptionRepositoryInterface` in the
  codebase (grep confirms it appears only in plan files). `PgSqlAttributeOptionRepository` is NOT bound in the
  container at all; it is instantiated directly inside `PgSqlAttributeDefinitionRepository::__construct()`
  (`$this->optionRepository = new PgSqlAttributeOptionRepository(...)`). So the ONLY container binding to fold
  is the definition-repo one above. Just move both repo files (the option repo is a plain collaborator class);
  no second binding is needed.
- Composer: `packages/attribute/composer.json` — `marko/database` → `marko/database-pgsql`.
- Tests: MOVE `tests/Feature/PgSqlAttributeDefinitionRepositoryTest.php` into `packages/attribute/tests/PgSql/...`
  (keep namespace `Markommerce\Attribute\PgSql\Tests\`). DROP `PackageScaffoldingTest`. No option-repo test
  is required (no option-repo interface/binding exists; the option repo is an internal collaborator already
  exercised via the definition-repo Feature test).
- Root `composer.json`: remove the `markommerce/attribute-pgsql` `require` + path `repositories` entries.
  The `autoload-dev.psr-4` line `"Markommerce\\Attribute\\PgSql\\Tests\\": "packages/attribute-pgsql/tests/"`
  must be REPOINTED to a `/PgSql/` SUBDIR, not the parent tests root: set it to
  `"Markommerce\\Attribute\\PgSql\\Tests\\": "packages/attribute/tests/PgSql/"` and land the moved files
  there. (Repointing to `packages/attribute/tests/` would make TWO PSR-4 prefixes — `Markommerce\Attribute\Tests\`
  and `Markommerce\Attribute\PgSql\Tests\` — claim the same directory with overlapping subpaths, which can
  mis-resolve. The `/PgSql/` subdir keeps them disjoint. This is the ONE pre-existing root autoload-dev entry
  among the four; tasks 001/002/003 ADD analogous `/PgSql/`-subdir entries for their packages.)
- Delete `packages/attribute-pgsql/`. `composer dump-autoload`.
- Cross-package profile lists to repoint `markommerce/attribute-pgsql` → `markommerce/attribute` (drop if
  parent already listed):
  - `packages/catalog-attribute/tests/Feature/ProductAttributeIntegrationTest.php`
  - `packages/catalog-attribute-scope/tests/Feature/ScopedAttributeIntegrationTest.php`
  - `packages/catalog-attribute-index/tests/Feature/EntityProvisioningTest.php`
  - `packages/catalog-attribute-index/tests/Feature/AttributeIndexIntegrationTest.php`
  - `packages/catalog-attribute-index/tests/Feature/AttributeFacetQueryTest.php`
  - `packages/catalog-attribute-storefront/tests/Feature/LayeredNavigationIntegrationTest.php`
  - `packages/catalog-attribute-storefront/tests/Feature/CategoryPageRenderTest.php`
  - `packages/attribute/tests/Unit/ModulePhpTest.php` — VERIFIED: it binds a `FakeAttributeDefinitionRepository`
    test double (~line 78) with a comment "lives in the driver package (attribute-pgsql)". After merge the
    module binds the real `PgSqlAttributeDefinitionRepository`. The module-bindings loop (~line 28-30) runs
    AFTER the manual fake bind, so the real binding will OVERWRITE the fake — if any test relies on the fake
    being resolved, move the fake bind to AFTER the loop or rebind it post-load. Update the comment, drop the
    `attribute-pgsql` reference, and add a positive assertion `it('binds AttributeDefinitionRepositoryInterface
    to PgSqlAttributeDefinitionRepository from attribute's own module')`.
- Grep the repo for any remaining `attribute-pgsql` reference and fix.

## Requirements (Test Descriptions)
- [x] `it binds AttributeDefinitionRepositoryInterface to PgSqlAttributeDefinitionRepository from attribute's own module`
- [x] `it reads attribute definitions via the in-package PgSql repository` (moved Feature test, green)
- [x] `it autoloads Markommerce\Attribute\PgSql\Tests\ classes from the repointed packages/attribute/tests/PgSql/ entry`
- [x] `it no longer references markommerce/attribute-pgsql anywhere in sources, tests, or composer`

## Acceptance Criteria
- `packages/attribute-pgsql/` deleted; repositories under `packages/attribute/src/PgSql/`; both repo bindings
  folded into attribute `module.php`; root composer (incl. the autoload-dev line) cleaned.
- attribute + all catalog-attribute* suites green; phpcs + phpstan level 8 clean for `packages/attribute`.
- No remaining `markommerce/attribute-pgsql` reference in the repo.

## Implementation Notes
- Moved `PgSqlAttributeDefinitionRepository` and `PgSqlAttributeOptionRepository` to `packages/attribute/src/PgSql/` (namespace unchanged).
- Folded the `AttributeDefinitionRepositoryInterface => PgSqlAttributeDefinitionRepository` binding into `packages/attribute/module.php`.
- Updated `packages/attribute/composer.json`: `marko/database` → `marko/database-pgsql`.
- Moved Feature test to `packages/attribute/tests/PgSql/Feature/PgSqlAttributeDefinitionRepositoryTest.php`; updated helper functions (vendor path and profile package name).
- Root `composer.json`: removed `markommerce/attribute-pgsql` from `require`; repointed autoload-dev `Markommerce\\Attribute\\PgSql\\Tests\\` → `packages/attribute/tests/PgSql/`.
- Updated `composer.lock`: removed the `markommerce/attribute-pgsql` package block and stability-flags entry; updated `markommerce/attribute` require to use `marko/database-pgsql`.
- Ran `composer update markommerce/attribute` to refresh the lock file.
- Deleted `packages/attribute-pgsql/` directory.
- Repointed `markommerce/attribute-pgsql` → `markommerce/attribute` (or removed where already transitive) in all cross-package Feature test profiles.
- Updated `packages/attribute/tests/Unit/ModulePhpTest.php`: added import for `PgSqlAttributeDefinitionRepository`, updated comment, added positive binding test.
- Updated `packages/attribute/tests/PackageScaffoldingTest.php`: added tests for PgSql autoloading and no-remaining-reference assertion.
- Updated docs: removed `attribute-pgsql.md`; updated `attribute.md`, `attribute-scope.md`, `catalog-attribute.md`, `catalog-attribute-scope.md` to remove `attribute-pgsql` references.
- Updated README files: removed `attribute-pgsql` install step from `attribute/README.md` and `catalog-attribute/README.md`.
- Updated `FEATURES.md`: removed `attribute-pgsql` row, updated `attribute` description, decremented package count from 39 to 38.
- phpcs + phpstan level 8 clean; 168 integration tests pass; 2294 unit tests pass (one pre-existing unrelated failure in config-scope SourceTreeTest due to local `.claude/settings.local.json`).
