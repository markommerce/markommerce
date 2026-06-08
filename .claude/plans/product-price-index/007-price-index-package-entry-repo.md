# Task 007: catalog-price-index — package + `ProductPriceIndexEntry` (HasScopes) + migration + repo

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the new `markommerce/catalog-price-index` package with its storage layer: a `ProductPriceIndexEntry` entity that uses `HasScopes` (one row per product, a base `amount` + `currency_code`, and a `scopes` JSON column for per-market overrides), a playground migration for its table, and a repository that does a single bulk multi-row upsert keyed on `product_id`. No indexer logic yet (T008) — just the storage substrate.

## Context
- **Package scaffold** mirrors a thin module (`catalog-market` is a good template): `composer.json` (`markommerce/catalog-price-index`, type `marko-module`, PSR-4 `Markommerce\CatalogPriceIndex\`, require `marko/core`, `marko/database`, `markommerce/catalog`, `markommerce/scope`, `markommerce/money`, **`markommerce/currency`**), `module.php`, `src/`, `tests/`, `.gitattributes`, `LICENSE`. The README is T011.
  - `markommerce/currency` is required because T008's `PriceIndexer` injects `CurrencyResolver` (for the row `currency_code`); declare it now so the package is self-consistent. Also register the package's `autoload-dev` PSR-4 (`Markommerce\\CatalogPriceIndex\\Tests\\`) and ADD that Tests mapping to the ROOT `composer.json` `autoload-dev` block (mirroring every other package there) — otherwise the new package's namespaced test helpers won't autoload. Add the new package to the root `composer.json` `require` list too.
- **Entity** `packages/catalog-price-index/src/Entity/ProductPriceIndexEntry.php` — model on `ProductScopedOverrides` for the HasScopes wiring:
  ```php
  #[Table('catalog_product_price_index')]
  class ProductPriceIndexEntry extends Entity implements HasScopesInterface
  {
      use HasScopes;  // framework-sanctioned scope-storage trait → adds the `scopes` json column

      #[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;
      #[Column(name: 'product_id', unique: true)] public int $productId = 0;
      #[Column(type: 'decimal(20,4)', nullable: true)] public ?string $amount = null;
      #[Column(name: 'currency_code', length: 3)] public string $currencyCode = '';
      // HasScopes contributes: #[Column(name:'scopes', type:'json', nullable:true)] public ?array $scopes
  }
  ```
  (`use HasScopes` is an accepted exception to the no-traits rule — it is the framework's scope-storage mechanism, exactly as `ProductScopedOverrides` uses it.)
- **Migration** in the playground (`/home/michal/www/marko/playground/database/migrations/`). **Match the EXACT existing convention** seen in `20260603152913_create_catalog_products.php`: a file `return new class extends Migration { public function up(ConnectionInterface $connection): void { $this->execute($connection, <<<'SQL' … SQL); } public function down(...) { … DROP TABLE … } };` — raw Postgres SQL via `$this->execute`, NOT a schema-builder. Concretely:
  ```sql
  CREATE TABLE "catalog_product_price_index" (
      "id" SERIAL PRIMARY KEY,
      "product_id" INTEGER NOT NULL UNIQUE,
      "amount" DECIMAL(20,4),
      "currency_code" VARCHAR(3) NOT NULL DEFAULT '',
      "scopes" JSONB
  );
  ```
  - **No FK:** the existing `catalog_products` table declares NO foreign keys; follow that — `product_id` is a plain `INTEGER NOT NULL UNIQUE` (the UNIQUE is the `ON CONFLICT` target). Do not invent an FK convention that doesn't exist in the project.
  - **`scopes` is `JSONB`** (matches `catalog_products`), nullable. **No timestamps** (the catalog tables have none).
  - Name the migration file with a timestamp prefix greater than the existing ones (e.g. `2026…_create_catalog_product_price_index.php`).
- **Repository** `packages/catalog-price-index/src/Repositories/ProductPriceIndexRepository.php` (+ interface in `src/Contracts/`): a `RepositoryQueryBuilder`-backed repo with:
  - `upsertMany(list<ProductPriceIndexEntry> $entries): void` — ONE bulk statement `INSERT INTO "catalog_product_price_index" (product_id, amount, currency_code, scopes) VALUES (?, ?, ?, ?::jsonb), (…) ON CONFLICT (product_id) DO UPDATE SET amount=EXCLUDED.amount, currency_code=EXCLUDED.currency_code, scopes=EXCLUDED.scopes`. Mirror `PgsqlScopedConfigStorage::saveOverride` (`packages/config-scope-pgsql/src/PgsqlScopedConfigStorage.php`) for the bound-parameter style: each row contributes 4 placeholders, the `scopes` placeholder gets the **`::jsonb` cast** and is bound as `json_encode($entry->scopes)`. When `$entry->scopes` is `null`, bind SQL `NULL` (not the string `"null"`) so the column stays NULL for base-only rows — branch the placeholder/param accordingly, or bind `null` and let `?::jsonb` coerce (verify which the driver accepts; prefer an explicit `NULL` literal when scopes is null). Build the `VALUES (…),(…)` group string dynamically from the entry count and flatten the bound params in the same order. Empty input = no-op (return early, emit NO SQL — asserted by the empty-list test).
  - `findByProductId(int $productId): ?ProductPriceIndexEntry`
  - `truncate(): void` (used by full rebuild)
- Bind the repo interface in `module.php`. No `boot` needed yet.

## Requirements (Test Descriptions)
- [x] `it stores scope overrides on the entry via the scopes column`
- [x] `it exposes product id amount and currency code columns`
- [x] `it inserts new index entries in a single bulk statement`
- [x] `it updates existing entries on product id conflict`
- [x] `it persists per market amounts in the scopes json`
- [x] `it does nothing when upserting an empty list`
- [x] `it finds an index entry by product id`
- [x] `it returns null when no entry exists for a product id`
- [x] `it removes all entries on truncate`

## Acceptance Criteria
- `upsertMany` issues exactly one SQL statement for N entries (asserted via a connection spy / query count).
- The entry round-trips its `scopes` JSON through the repository.
- Migration applies cleanly in the playground.
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
- Package scaffold created with `composer.json`, `module.php`, `LICENSE`, `.gitattributes`, and placeholder `README.md` (full README deferred to T011).
- `ProductPriceIndexEntry` entity: `#[Table('catalog_product_price_index')]`, `id` (auto-increment PK), `productId` (UNIQUE), `amount` (nullable decimal string), `currencyCode` (VARCHAR 3), and `scopes` (from `HasScopes` trait).
- `ProductPriceIndexRepository` uses direct `$this->connection->execute()` for the bulk upsert (not the base `save()` path). Null scopes use an explicit `NULL` SQL literal; non-null scopes use the `?::jsonb` cast + `json_encode()`.
- SpyConnection in tests records all `execute()` calls — used to assert exactly one SQL statement for N entries.
- PHPStan config excludes `packages/*/tests` so test type annotations don't need to be level-8 clean.
- Migration `20260608120000_create_catalog_product_price_index.php` created in playground with JSONB `scopes`, no FK on `product_id`.
