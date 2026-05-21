# Task 012: CatalogSeeder

**Status**: completed
**Depends on**: 007, 008, 009
**Retry count**: 0

## Description
Create `CatalogSeeder` — a discoverable seeder that generates fake categories, fake products (each with a unique SKU), product-to-category assignments, and locale-scoped name/description overrides for testing.

## Context
- Create at `packages/catalog/Seed/CatalogSeeder.php` — NOT `src/Seed/`. `Marko\Database\Seed\SeederDiscovery::discoverInVendor()` globs `vendor/*/*/Seed` (a *sibling* of `src/`, not a child of it). Because the monorepo mounts `packages/*` as composer `path` repositories, `vendor/markommerce/catalog` symlinks to `packages/catalog`, so the `Seed/` directory MUST be at the package root: `packages/catalog/Seed/`. A seeder placed under `src/Seed/` is silently never discovered. (`marko/database` `EntityDiscovery` by contrast globs `src/Entity` — the two discovery mechanisms use different conventions; do not assume symmetry.)
- The seeder class lives outside `src/`, so it is NOT covered by the `Markommerce\Catalog\ → src/` PSR-4 map. Task 001 adds a dedicated `Markommerce\Catalog\Seed\ → Seed/` PSR-4 entry; this seeder's namespace MUST be `Markommerce\Catalog\Seed`. `marko/database`'s `module.php` instantiates each discovered seeder via `$container->get($seederClass)`, so the class must be autoloadable (not just discoverable by file-parse).
- Implements `Marko\Database\Seed\SeederInterface` (`run(): void`) and carries the `#[Marko\Database\Seed\Seeder(name: 'catalog')]` class attribute.
- Constructor-injects the catalog repositories / services needed to persist data.
- `run()` generates:
  - A fixed number of categories (e.g. 5) with base `name`/`description`.
  - A fixed number of products (e.g. 30), each with a generated unique SKU and base `name`/`description`.
  - Random product-to-category assignments (each product in 1-2 categories).
- Fake data: generate plain deterministic-ish strings (e.g. `"Category {$n}"`, `"SKU-" . str_pad(...)`) — do NOT add a faker library dependency unless one is already required by the monorepo.
- Define the seed counts as typed class constants (`private const int CATEGORY_COUNT = 5;` etc.).

### CRITICAL — how to write locale overrides
The seeder writes locale-scoped overrides for `name` and `description` on *some* seeded products and categories. It MUST do this by calling the entity's own `HasScopes` trait method directly:

```php
$product->setOverride('locale:de', 'name', 'German name');
$product->setOverride('locale:fr', 'name', 'French name');
```

It MUST NOT use `ScopeResolver::setOverride()`. `ScopeResolver::setOverride()` runs `ScopeSignatureValidator`, which rejects any locale path not present in `config/scope.php`'s hierarchy — and the locales used here (`de`, `fr`) are intentionally NOT registered. `HasScopes::setOverride()` only checks `DefaultScopeGuard` (rejects the axis *default* path), which non-default codes pass.

Use the locale codes `de` and `fr`. The overrides must be present in the entity's `scopes` array at the moment a `save()`/`insert()` writes the row, so they serialise into the `scopes` column.

**Persistence ordering — important.** `ProductService::createProduct()` (task 008) builds a `Product`, calls `save()` internally, and returns the persisted entity. If the seeder sets overrides on the *returned* product, the row is already inserted with `scopes = NULL` — a second `save()` would be needed (an UPDATE) to persist the overrides. Choose ONE consistent approach and state it in the implementation notes:
  - (a) Build `Product`/`Category` entities directly, set base properties AND `setOverride()` calls, then persist once via the repository's `save()`. This keeps overrides in a single INSERT. The SKU uniqueness check is then NOT exercised by the seeder (acceptable — the seeder generates guaranteed-unique SKUs).
  - (b) Use `ProductService::createProduct()` for the SKU-checked insert, then call `setOverride()` and a second repository `save()` (an UPDATE) for the overrides.
Approach (a) is simpler and is the recommended default. Whichever is chosen, the seeder's constructor injects exactly the dependencies that approach needs (repositories for (a); services + repositories for (b)).

### Test notes
- `HasScopes::setOverride()` calls `DefaultScopeGuard::assertWritable()`. Seeder tests must configure the guard first — call `Markommerce\Scope\Storage\DefaultScopeGuard::configure(['locale' => 'default'])` in test setup and `DefaultScopeGuard::reset()` afterward.
- Use the task-006 in-memory fakes (or a fake connection) so the seeder test does not need a live database.

## Requirements (Test Descriptions)
- [x] `it is annotated with the Seeder attribute named catalog`
- [x] `it seeds the configured number of categories`
- [x] `it seeds the configured number of products`
- [x] `it gives every seeded product a unique sku`
- [x] `it assigns seeded products to seeded categories`
- [x] `it writes locale-scoped name overrides on seeded products using the de and fr locales`
- [x] `it writes locale-scoped overrides on seeded categories`

## Acceptance Criteria
- All requirements have passing tests
- Seeder is discoverable via the `#[Seeder]` attribute
- Overrides are written via `HasScopes::setOverride()`, never `ScopeResolver::setOverride()`
- Code follows code standards

## Implementation Notes
- Used approach (a): built `Product`/`Category` entities directly, set base properties and `setOverride()` calls, then persisted once via `repository->save()`. Overrides are written before any `save()` call so they are included in the single INSERT.
- All 30 products and all 5 categories get locale overrides for `de` and `fr` on both `name` and `description`.
- Product-to-category assignment uses a deterministic round-robin with every even-indexed product also assigned to the next category (1-2 categories per product).
- Tests configure `DefaultScopeGuard` with `['locale' => 'default']` in `beforeEach` and reset in `afterEach` so `setOverride()` calls for `locale:de` and `locale:fr` are accepted (not the default scope).
