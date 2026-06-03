# Task 014: catalog seeder — assign random prices to seeded products

**Status**: complete
**Depends on**: 009
**Retry count**: 0

## Description
Populate `Product.priceAmount` with a random price for every product created by the catalog seeder, so seeded demo data has realistic prices to resolve and display. Only sets the bare decimal amount string (currency is config-driven); `catalog` gains no `money`/`pricing` dependency.

## Context
- File: `packages/catalog/Seed/CatalogSeeder.php`. The product-creation loop (~lines 72-79) builds `new Product()` and sets `sku`, `name`, `description`, then `productRepository->insertBatch($entities)`. Add a `priceAmount` assignment in that loop.
- `Product.priceAmount` is `?string` mapped to `decimal(20,4)` (task 009). Store a **string**, never a float — no floating-point drift.
- Generate a random price as a decimal string with 2 fraction digits, e.g.:
  ```php
  $product->priceAmount = sprintf('%d.%02d', random_int(1, 999), random_int(0, 99));
  ```
  (Any reasonable range/precision is fine as long as it is a valid `decimal(20,4)` string within range, e.g. `1.00`–`999.99`.) Keep it deterministic-free (`random_int`) — tests assert format/range, not exact values.
- Do NOT add a currency to the seeder — currency comes from config at resolve time.
- No new dependency in `catalog`'s `composer.json`.
- Update the seeder test `packages/catalog/tests/Unit/Seed/CatalogSeederTest.php` (mirror its existing `makeCatalogSeeder()` + `FakeProductRepository` helpers) to assert every seeded product has a non-null `priceAmount` matching a decimal pattern and within the chosen range.

## Requirements (Test Descriptions)
- [x] `it assigns a non null price amount to every seeded product`
- [x] `it assigns price amounts as decimal strings not floats`
- [x] `it assigns price amounts within the expected range`
- [x] `it still seeds the configured number of products with prices`

## Acceptance Criteria
- Every product produced by `CatalogSeeder::run()` has a `priceAmount` that is a non-null decimal string.
- No float is used to build the amount; values are valid `decimal(20,4)` strings.
- `catalog` gains no new dependency.
- All requirements have passing tests; coverage ≥ 80%; existing seeder tests still pass.
- Follows standards (`declare(strict_types=1)`, etc.).

## Implementation Notes
- Added `$product->priceAmount = sprintf('%d.%02d', random_int(1, 999), random_int(0, 99));` in the product-creation loop in `CatalogSeeder::seedProductsAndAssignments()`.
- 4 new tests added to `CatalogSeederTest.php` covering non-null, string type, decimal pattern/range, and total count.
- No new dependencies added to `catalog`'s `composer.json`.
- Requirements 2–4 passed immediately (GREEN without prior RED) because the `sprintf` implementation from requirement 1 already produced the correct type, format, and range. Noted in TDD log.
