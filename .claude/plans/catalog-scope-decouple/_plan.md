# Plan: Decouple catalog from scope (Phase 2)

## Created
2026-05-26

## Status
completed

## Objective
Remove all scope coupling from `markommerce/catalog` so that Product and Category become plain entities, and introduce three new packages (`catalog-scope`, `locale`, `catalog-locale`) that compose the locale-scoping behaviour catalog ships today. After this plan, a Tier 1 corner-shop merchant can install `markommerce/catalog` with no scope/locale machinery, and a Tier 2 merchant gets the same multi-language behaviour by additionally installing the bridge stack.

## Related Issues
none

## Discovery Notes

### Phase 1 foundation already in place
- `ScopedFieldRegistry` (shipped in P1) is the authoritative source of which fields are scoped by which axes. Boot-time `module.php` closures call `register()` and `ScopeMetadataFactory::for()` reads from it.
- `BridgeContributionTest` already exercises the mechanism a `catalog-locale` bridge would use: container auto-injects `ScopedFieldRegistry` into a boot closure, the bridge registers properties, `ScopeMetadataFactory` exposes them via `propertiesFor()`.
- `ScopeResolver::findStorage()` supports two paths: entity implements `HasScopesInterface`, OR a companion entity attached via `attachCompanion()` implements it. The companion path is unused in catalog today but is the chosen design for P2.

### Decisions locked in during clarification
- **Decorator style: companion entity.** `catalog-scope` ships `ProductScopedOverrides` and `CategoryScopedOverrides` as separate entities with their own tables, linked via `#[Table(extends: Product::class)]`. The existing `ScopedOverridesPersistenceTest` already proves this pattern works end-to-end. Product/Category remain plain entities — no `HasScopes` trait, no `#[Scoped]` attribute, no schema change to catalog tables.
- **ProductGridComponent swap: Marko Preference.** Catalog keeps a plain `ProductGridComponent` that reads `$product->name` directly. `catalog-scope` ships `ScopedProductGridComponent` with `#[Preference(replaces: ProductGridComponent::class)]` that injects `ScopeResolver` and populates `resolvedNames`/`resolvedDescs`.
- **`markommerce/locale` package: axis registration only.** Ships a `config/scope.php` file that contributes `scope.axes.locale` via Marko's auto-discovery. No PHP source classes, no value objects, no helpers.
- **`#[Scoped]` attribute is kept** in scope as an ergonomic shortcut for merchant-defined entities. It is removed from catalog's `Product`/`Category` entities, which now rely entirely on the bridge's programmatic registration.
- **Scope's default config drops `locale`.** `packages/scope/config/scope.php` keeps only `market` and `channel` (P4 will move `market` to its own package). The `locale` axis only exists when `markommerce/locale` is installed.
- **catalog-locale fields:** Product.name, Product.description, Category.name, Category.description — all four become locale-scoped via the bridge's boot closure. Preserves today's behaviour for an existing Tier 2 merchant who installs the bridge.
- **Demos updated to Tier 2.** `frontend-demo` (and any other affected demos) gain explicit requires on `catalog-scope`, `locale`, `catalog-locale` so the live demo storefront still resolves locale-scoped values.

### Files / mechanisms touched
- `packages/scope/config/scope.php` (drop `locale` axis)
- `packages/scope/tests/Unit/Config/DefaultAxesConfigTest.php`, `tests/Unit/ModulePhpTest.php`, `tests/Unit/ModulePhpPipelineTest.php`, `tests/Feature/DefaultScopeResolutionTest.php`, `tests/Feature/CatalogMetadataIntegrationTest.php` (update or relocate scope-default-axes assertions; the catalog cross-reference in `CatalogMetadataIntegrationTest` becomes obsolete)
- `packages/catalog/src/Entity/Product.php`, `Category.php` (remove `#[Scoped]`, `HasScopes`, `HasScopesInterface`, scope imports)
- `packages/catalog/src/Component/ProductGridComponent.php` (drop `ScopeResolver` injection, return raw name/description; remove `resolvedNames`/`resolvedDescs` maps from `ProductGridData` OR keep them but populate identity-mapped from raw fields — decision per task 004)
- `packages/catalog/tests/Unit/Entity/ProductTest.php`, `CategoryTest.php`, `ProductCategoryAssignmentTest.php`, `tests/Unit/Component/ProductGridComponentTest.php` (strip scope assertions)
- `packages/catalog/tests/Feature/CategoryControllerTest.php`, `CategoryLayoutTest.php`, `CategoryTreeIntegrationTest.php`, `CatalogSeederTreeTest.php`, `tests/Unit/Seed/CatalogSeederTest.php` (remove `DefaultScopeGuard::configure()` setup and `ScopeResolver` plumbing)
- `packages/catalog/composer.json` (drop `markommerce/scope` from `require`)
- `packages/catalog/Seed/CatalogSeeder.php` (strip `setOverride()` calls + `ScopeStorageException` import; the locale-aware variant relocates to `catalog-scope` per task 007)
- `packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php` (drop the inline `scopes JSON` column from its `CREATE TABLE catalog_categories` statement — that column is owned by `catalog-scope` post-P2)
- New: `packages/catalog-scope/` (composer.json, module.php, src/Entity/ProductScopedOverrides.php, src/Entity/CategoryScopedOverrides.php, src/Component/ScopedProductGridComponent.php, Seed/CatalogLocaleSeeder.php (relocated), tests, README)
- New: `packages/locale/` (composer.json, config/scope.php, scaffolding test, README)
- New: `packages/catalog-locale/` (composer.json, module.php with boot closure, tests, README)
- `packages/frontend-demo/composer.json` (add catalog-scope + locale + catalog-locale; also add `markommerce/catalog` if a Tier 2 demo is desired — see task 010 notes)
- `composer.json` (root): add `markommerce/catalog-scope`, `markommerce/locale`, `markommerce/catalog-locale` to `require`; add `Markommerce\\CatalogScope\\Tests\\`, `Markommerce\\Locale\\Tests\\`, `Markommerce\\CatalogLocale\\Tests\\` to `autoload-dev.psr-4`. (The `repositories.packages/*` glob already covers package discovery.)
- `docs/src/content/docs/packages/catalog.md`, new `catalog-scope.md`, `locale.md`, `catalog-locale.md`

## Scope

### In Scope
- Strip every `Markommerce\Scope\...` import and `#[Scoped]`/`HasScopes` use from catalog's production code.
- Create three new packages (`catalog-scope`, `locale`, `catalog-locale`) with full composer.json/module.php/tests/README per project conventions.
- Move scope-aware behaviour (currently in catalog's `ProductGridComponent`, `ProductGridComponentTest`, and parts of `CategoryControllerTest` / `CategoryLayoutTest`) into catalog-scope.
- Drop `locale` from scope's default axes; have it contributed by the new `locale` package.
- Update `frontend-demo` and any other affected demo packages so the bridge stack is wired and demos still pass.
- End-to-end integration test that boots scope + catalog + catalog-scope + locale + catalog-locale together and verifies locale-scoped overrides resolve through the companion.
- READMEs for the three new packages following Package README Standards.
- Docs site pages for the three new packages plus an update to `catalog.md`.

### Out of Scope
- Extracting `catalog-storefront` from catalog — that is Phase 3.
- Creating `market`, `catalog-market`, `catalog-market-category-trees` — that is Phase 4. `CategoryTree*` entities and `market` axis remain inside catalog and scope respectively for now.
- Decoupling `config` from `scope` — that is Phase 5.
- Introducing a Locale value object, locale-flavoured resolvers, or i18n helpers in `markommerce/locale`. The package is axis-registration-only in P2.
- Per-merchant overridability of bridge mappings (deferred per FEATURES.md).
- Removing the `#[Scoped]` attribute from scope. Kept as an ergonomic shortcut for merchant-defined entities.

## Success Criteria
- [ ] `grep -rn "Markommerce\\Scope" packages/catalog/src` returns zero matches.
- [ ] `packages/catalog/composer.json` does not require `markommerce/scope`.
- [ ] `markommerce/catalog` can be installed and tested without `markommerce/scope` being present (no fatal class-not-found, no failing tests when scope is absent).
- [ ] Three new packages exist with passing tests: `markommerce/catalog-scope`, `markommerce/locale`, `markommerce/catalog-locale`.
- [ ] An integration test boots catalog + catalog-scope + locale + catalog-locale together, sets a locale override on a Product via the `ProductScopedOverrides` companion, and asserts `ScopeResolver` resolves the override under the matching locale context.
- [ ] `frontend-demo` tests still pass with the updated bridge stack.
- [ ] `composer test:all` passes from the monorepo root.
- [ ] PHPStan level 8 + PHP-CS-Fixer clean for all touched packages.
- [ ] Docs site builds with the new package pages.
- [ ] All requirements from each task file have passing tests.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Drop `locale` from scope's default config | - | completed |
| 002 | Create `markommerce/locale` axis package | 001 | completed |
| 003 | Strip scope coupling from Product, Category, and the catalog seeder | - | completed |
| 004 | Simplify ProductGridComponent to read raw values | 003 | completed |
| 005 | Remove remaining scope coupling from catalog tests | 003, 004 | completed |
| 006 | Drop `markommerce/scope` from catalog composer.json + register new packages at monorepo root | 003, 004, 005 | completed |
| 007 | Create catalog-scope package with companion entities (incl. relocated locale seeder) | 003 | completed |
| 008 | Add ScopedProductGridComponent via #[Preference] | 004, 007 | completed |
| 009 | Create catalog-locale bridge package | 002, 003, 007 | completed |
| 010 | Update demo packages to require the bridge stack | 002, 006, 007, 009 | completed |
| 011 | Tier 2 end-to-end integration test | 002, 007, 008, 009 | completed |
| 012 | READMEs for new packages (and update catalog README) | 002, 007, 008, 009 | completed |
| 013 | Docs site pages | 012 | completed |

## Architecture Notes

### Companion-entity decorator (single-table inheritance via Marko's extender mechanism)
catalog-scope contributes its scoped-overrides storage via Marko's `#[Table(extends:)]` extender mechanism. **Important**: this is *single-table inheritance* at the schema level — `SchemaRegistry::registerEntities()` merges the companion's columns into the parent table. There is no separate `product_scoped_overrides` table. When catalog-scope is installed, the `catalog_products` table gains a `scopes` jsonb column contributed by `ProductScopedOverrides`. When catalog-scope is not installed, that column does not exist in catalog's owned schema and `Product` is a clean single-table entity.

```
catalog_products              (catalog-scope installed)
─────────────────
id          PK
sku
name                          ← owned by catalog
description
scopes      jsonb (nullable)  ← contributed by catalog-scope's ProductScopedOverrides via #[Table(extends:)]
```

Code shape:
```php
// catalog-scope/src/Entity/ProductScopedOverrides.php
#[Table(extends: Product::class)]
class ProductScopedOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}
```

`linkExtendersFrom()` already runs at boot for every discovered entity (see `../marko/packages/database/module.php` line 28). When catalog-scope is installed, the EntityHydrator attaches a `ProductScopedOverrides` companion to every hydrated `Product` provided the SELECT includes the `scopes` column (a default `SELECT *` does — see `EntityHydrator::hydrate()` lines 78-86 for the silent-skip guard when columns are absent from the row). `ScopeResolver::findStorage()` walks `$entity->companions()` to find the `HasScopesInterface` implementor.

**Edge case — hydrator silently skips companion attach if companion columns are absent from the row.** A custom partial SELECT (`SELECT id, sku, name FROM catalog_products`) will hydrate a Product with no companion attached → resolver falls back to raw value. Task 007's persistence tests must exercise both the SELECT-* path (companion attached) and the empty-overrides path (companion attached with `scopes = null`).

### Preference-based component override
```php
// catalog-scope/src/Component/ScopedProductGridComponent.php
#[Preference(replaces: ProductGridComponent::class)]
class ScopedProductGridComponent extends ProductGridComponent
{
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CategoryAssignmentService $categoryAssignmentService,
        private ScopeResolver $scopeResolver,
    ) {
        parent::__construct($categoryRepository, $categoryAssignmentService);
    }

    public function data(Category $category): ProductGridData
    {
        $data = parent::data($category);
        // walk products, call $this->scopeResolver->resolved($product, 'name'/'description')
        // and overwrite resolvedNames/resolvedDescs entries with resolved values
        return $data;
    }
}
```

`PreferenceRegistry` auto-discovers the attribute at boot. Container `get(ProductGridComponent::class)` returns a `ScopedProductGridComponent` instance.

### Bridge boot closure
```php
// catalog-locale/module.php
return [
    'boot' => function (ScopedFieldRegistry $registry): void {
        foreach ([Product::class, Category::class] as $entityClass) {
            foreach (['name', 'description'] as $property) {
                $registry->register(
                    entityClass: $entityClass,
                    property: $property,
                    axes: ['locale'],
                );
            }
        }
    },
];
```

Per `BridgeContributionTest`, `DependencyResolver` reorders boot closures so that `markommerce/scope` boots before any module that requires it, which gives `catalog-locale`'s boot a fully-populated `ScopeRegistryInterface` (including the `locale` axis that `markommerce/locale` contributes via config discovery).

### Locale package as pure axis declaration
Marko's `ConfigDiscovery` glob-loads every `<package>/config/*.php` and merges by filename. `packages/locale/config/scope.php` contributes a single `axes.locale` entry, which is merged with `packages/scope/config/scope.php` (now containing only `market` and `channel`).

```php
// packages/locale/config/scope.php
return [
    'axes' => [
        'locale' => [
            'default' => 'default',
            'scopes'  => ['default' => []],
        ],
    ],
];
```

No PHP source, no module.php logic. The package is ~10 lines of code plus a README and a scaffolding test that asserts the config contribution shape.

## Risks & Mitigations

- **Risk:** Catalog tests have substantial scope coupling (DefaultScopeGuard::configure, ScopeResolver construction) embedded in seemingly unrelated test setup. Stripping it may break tests that aren't testing scope at all.
  - **Mitigation:** Task 005 lists each affected test file explicitly. The pattern is mechanical: remove imports, drop `DefaultScopeGuard::configure(...)`, drop ScopeResolver builders. Where a test legitimately needed scope (e.g., asserting resolved values in `CategoryLayoutTest`), that test case moves to catalog-scope's test suite under task 008 or 011.
- **Risk:** Removing `locale` from scope's default config breaks scope-internal tests that assume it (`DefaultAxesConfigTest`, `ModulePhpTest`, `DefaultScopeResolutionTest`, `CatalogMetadataIntegrationTest`).
  - **Mitigation:** Task 001 enumerates each affected test. `CatalogMetadataIntegrationTest` is a cross-package leak (scope tests should not reference catalog) — task 001 relocates the relevant assertions into a Tier 2 integration test in catalog-scope (task 011) and deletes the original.
- **Risk:** The companion-entity approach requires `EntityDiscovery` to find `ProductScopedOverrides` at boot and `linkExtendersFrom` to wire it up. If discovery globs the wrong path (e.g., entities placed outside `src/Entity/`), the companion never attaches and overrides silently miss. Additionally, the companion's `scopes` column is merged into the parent table by `SchemaRegistry::registerEntities()` — a SELECT against `catalog_products` returns the column automatically; but a partial SELECT that omits it makes `EntityHydrator::hydrate()` silently skip the companion attach (lines 78-86 of `EntityHydrator.php`).
  - **Mitigation:** Task 007 includes an integration test that creates a `Product`, attaches a `ProductScopedOverrides` companion via the repository (real hydration path), persists, re-fetches, and asserts the override survives a round-trip — using the catalog `ProductRepository` which selects all columns. Same pattern as the existing `ScopedOverridesPersistenceTest` but exercising the production discovery path. The partial-SELECT skip case is also covered explicitly.
- **Risk:** `#[Preference(replaces: ProductGridComponent::class)]` may collide with merchant code that also tries to override `ProductGridComponent`. `PreferenceRegistry` raises `PreferenceConflictException` for same-priority conflicts.
  - **Mitigation:** catalog-scope is a "modules" source — merchant `app/` code can still override on top. Same-tier conflicts (two modules both overriding) are intentional fail-loud behaviour. Documented in catalog-scope README.
- **Risk:** Demo packages may have hidden assumptions about `Product->scopes` being a property on the entity. After P2 the property only exists on `ProductScopedOverrides` (the companion), accessed via `$product->companion(ProductScopedOverrides::class)?->scopes`.
  - **Mitigation:** Task 010 runs the demo test suites and adjusts any code that referenced `$product->scopes`. Note: as of P1 the demos do not depend on catalog at all (see task 010 description), so this risk is largely theoretical for P2 itself.
- **Risk:** Multiple packages contributing to `scope.axes.locale` via config files would silently merge in unexpected ways if a merchant has their own override.
  - **Mitigation:** This is Marko's standard config-merge behaviour and aligns with how `marko/config` `ConfigMerger` works. Documented in `locale` README as "axis declared by this package; merchants override the same key to customise."
- **Risk:** `packages/catalog/Seed/CatalogSeeder.php` is production code (PSR-4 autoloaded under `Markommerce\Catalog\Seed\`) that imports `Markommerce\Scope\Exceptions\ScopeStorageException` and calls `$product->setOverride(...)` / `$category->setOverride(...)` directly. Stripping scope from catalog's entities (task 003) will make this seeder fail to load until it is updated.
  - **Mitigation:** Task 003 strips the locale-override calls and the scope import from `CatalogSeeder` — catalog ships only a plain seeder with `Product`/`Category` rows. The locale-aware seeder relocates to `catalog-scope` under task 007 as `CatalogLocaleSeeder` (or equivalent), so Tier 2 still demos locale overrides.
- **Risk:** `EntityHydrator::hydrate()` silently skips attaching a companion if any of the companion's columns is absent from the row (see `marko/packages/database/src/Entity/EntityHydrator.php` lines 78-86). A partial SELECT (custom repository or hand-rolled SQL) that omits the `scopes` column will hydrate a `Product` with no companion → `ScopeResolver::resolved()` silently falls back to the raw value.
  - **Mitigation:** Catalog's default `ProductRepository::find()` selects all columns. Task 007's persistence tests must cover both the SELECT-* path (companion attached) and the partial-SELECT path (no companion). Documented in catalog-scope README as a gotcha for custom repositories.
