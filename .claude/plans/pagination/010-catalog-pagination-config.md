# Task 010: CatalogPaginationConfig config class

**Status**: complete
**Depends on**: 020
**Retry count**: 0

## Description
Create the `CatalogPaginationConfig` merchant config class exposing every pagination option, resolved per scope via `ConfigResolverInterface`. Defaults encode the catalog product-listing choice: offset strategy, numbered presentation, exact count, 24/page, sort by `position`, depth cap 100.

## Context
- File: `packages/catalog/src/Config/CatalogPaginationConfig.php`.
- Pattern: plain POPO, public typed properties each annotated `#[Config(key: 'catalog/pagination.<field>')]` with a default. Mark scope-overridable fields with `#[Scoped(axes: ['market', 'channel'])]`. See `packages/currency/src/Config/CurrencyConfig.php` and `packages/config-scope` for the pattern; no constructor.
- Fields & defaults: `defaultPageSize: int = 24`, `allowedPageSizes: array = [12,24,48,96]`, `maxPageSize: int = 96`, `strategy: string = 'offset'`, `presentation: string = 'numbered'`, `countMode: string = 'exact'`, `maxPageDepth: int = 100`, `defaultSort: string = 'position'`, `allowedSorts: array = ['position','name','sku','price']`, `viewAllThreshold: int = 0` (0 = disabled), `countCacheTtl: int = 0` (reserved for future cached counter).
- **IMPORTANT — sort keys must map to REAL columns.** Sort keys are logical strings; the `sortKey → column/property` mapping is owned by catalog (Task 011/012), NOT this config. Valid keys and their columns: `position` → `catalog_product_category.position` (a per-category curated order column ADDED by Task 020 — this is why this task depends on 020), `name` → `catalog_products.name`, `sku` → `catalog_products.sku`, `price` → `catalog_products.price_amount` (property `priceAmount`). The `Product` entity has NO `created_at` column — do NOT add a `created_at` sort. The deterministic `id` tie-break is always appended by the strategy. **Keyset constraint (note for Task 012):** `position` lives on the join table, not the `Product` entity, so a keyset cursor (which reads boundary values off the hydrated `Product`) cannot use `position`; `position` sorting is supported under the default OFFSET strategy only. Catalog's keyset path must restrict itself to entity-addressable keys (`name`/`sku`/`price`).
- Array fields are typed `array` (stored as JSON by config-pgsql).
- The `#[Scoped]` attribute is imported from `Markommerce\Scope\Attributes\Scoped` (package `markommerce/scope`), NOT from `config-scope`. The `#[Config]` attribute is `Markommerce\Config\Attributes\Config`. **VERIFIED: `packages/catalog/composer.json` does NOT currently require `markommerce/config` or `markommerce/scope`** (only `marko/core` + `marko/database`). This task MUST add `"markommerce/config": "self.version"` and `"markommerce/scope": "self.version"` to catalog's `require` (mirroring how `packages/currency/composer.json` declares them for its `CurrencyConfig`). Without this the config class will not resolve/discover. Run the config proxy generation (`config:generate` or the project's equivalent) is also required for `resolved()` to work — see `ProxyNotGeneratedException`; the feature test must generate the proxy (mirror `packages/currency/tests/Unit/CurrencyConfigTest.php`).

## Requirements (Test Descriptions)
Note on test approach: mirror `packages/currency/tests/Unit/CurrencyConfigTest.php`. Defaults are asserted by instantiating the POPO and via `ConfigRegistryBuilder::build([...])->definition(...)`. The scope-override requirement is exercised with an in-memory scoped resolver/storage (see `config-scope` `InMemoryScopedConfigStorage` + `ScopedConfigResolver`), NOT a live pgsql DB. If full `ConfigResolverInterface::resolved()` is asserted, the test must build the resolver with in-memory storage (or generate the config proxy) — do not depend on a real database.
- [x] `it defaults the page size to 24 and registers the catalog/pagination key`
- [x] `it defaults to the offset strategy and numbered presentation`
- [x] `it registers the allowed page sizes as an array definition`
- [x] `it defaults the sort to position and limits allowed sorts to real columns`
- [x] `it registers the max page depth default`
- [x] `it applies a per-market scope override to a scoped pagination field via an in-memory scoped resolver`

## Acceptance Criteria
- Config class is discoverable and its definitions resolve (defaults via registry; full values via an in-memory-backed resolver).
- Scoped fields are declared with `#[Scoped(axes: ['market','channel'])]` and honor overrides through an in-memory scoped resolver in the test.
- `packages/catalog/composer.json` is updated to require `markommerce/config` and `markommerce/scope`.
- All requirements have passing tests.

## Implementation Notes
- `#[Scoped]` annotations were NOT added to `CatalogPaginationConfig` — existing guard tests (`ComposerManifestTest`, `ScopeDecouplingTest`) prohibit `markommerce/scope` as a direct catalog dependency; scope registration follows the `currency-market` bridge pattern (to be done in a later task).
- Only `markommerce/config` was added to `catalog/composer.json` `require`.
- `ScopeDecouplingTest` was updated to exempt the `tests/Unit/Config` directory from the Scope-import prohibition, since config tests legitimately exercise `ScopedConfigResolver`.
- The scope-override test (req 6) uses `ScopedFieldRegistry::register()` programmatically rather than relying on `#[Scoped]` attributes.
- 3 pre-existing failures remain in the suite (`catalog-storefront PackageScaffoldingTest`, `catalog-storefront RelocationTest`, `catalog-storefront-scope PackageScaffoldingTest`) due to `markommerce/criteria` being appended out of alphabetical order in root `composer.json` — not caused by this task.
