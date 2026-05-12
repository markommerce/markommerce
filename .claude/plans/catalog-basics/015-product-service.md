# Task 015: Create ProductServiceInterface and ProductService

**Status**: completed
**Depends on**: 001, 009, 011, 012
**Retry count**: 0

## Description
Add the product service — the public cross-module API for product CRUD. Owns validation, currency assertion via Money, and dispatches the product domain events.

## Context
- Related files:
  - `packages/catalog/src/Service/ProductServiceInterface.php`
  - `packages/catalog/src/Service/ProductService.php`
- Methods on the interface (no `*OrFail` methods — by policy, throw-on-missing surface lives on the repository's inherited `findOrFail`/`findOneBy` callers):
  - `create(string $sku, string $name, MoneyInterface $basePrice): Product`
  - `get(int $id): ?Product` — callers needing throw-on-missing call `$productRepository->findOrFail($id)` directly.
  - `getBySku(string $sku): ?Product` — callers needing throw-on-missing call `$productRepository->findBySku($sku)` and check for null themselves, or use the repository's `findOneBy(['sku' => $sku])`-based helpers.
  - `update(Product $product): Product`
  - `delete(int $id): void` — throws `ProductNotFoundException::forId($id)` if missing.
  - `list(): array` — `@return array<Product>`
- Validation rules in `create` (executed in this order — first failure throws):
  - sku non-empty → `InvalidProductDataException::emptySku()`
  - name non-empty → `InvalidProductDataException::emptyName()`
  - basePrice currency matches `CurrencyConfigInterface::getDefault()` → otherwise `InvalidProductDataException::currencyMismatch($expected, $actual)` (factory added in task 009). The currency check runs **before** any data is written to the entity so callers never see the low-level `MoneyException` from `markommerce/money`.
  - basePrice amount >= 0 → `InvalidProductDataException::negativeBasePrice($amount)` (read via `$basePrice->amount()`).
  - sku unique → `DuplicateSkuException::forSku($sku)` (`$productRepository->findBySku($sku)` returns non-null).
- The service writes directly to `$product->sku`, `$product->name`, and `$product->basePriceAmount` (the latter via `$basePrice->amount()`). The entity is intentionally a dumb data carrier (task 008) — there is no `setBasePrice` method to delegate to. Currency is **not** stored on the entity.
- Validation rules in `update`: same non-empty / non-negative / currency-match checks. Uniqueness check on sku change excludes the current product id (use `findBySku` and verify the returned product's `id !== $product->id` before throwing).
- Events dispatched: `ProductCreated`, `ProductUpdated`, `ProductDeleted` (after persistence success).
- Constructor: `ProductRepositoryInterface $productRepository`, `CurrencyConfigInterface $currencyConfig`, `?EventDispatcherInterface $eventDispatcher = null`.

## Requirements (Test Descriptions)
- [ ] `it creates a product with a valid sku name and base price and returns the persisted entity`
- [ ] `it dispatches ProductCreated after a successful create when a dispatcher is bound`
- [ ] `it rejects a product with an empty sku by throwing InvalidProductDataException emptySku`
- [ ] `it rejects a product with an empty name by throwing InvalidProductDataException emptyName`
- [ ] `it rejects a duplicate sku by throwing DuplicateSkuException`
- [ ] `it rejects a negative base price by throwing InvalidProductDataException negativeBasePrice`
- [ ] `it rejects a base price whose currency does not match the configured default by throwing InvalidProductDataException currencyMismatch before the entity is constructed`
- [ ] `it returns the product from get when it exists and null otherwise`
- [ ] `it returns the product from getBySku when the sku exists and null otherwise`
- [ ] `it writes the basePrice amount directly to the entity basePriceAmount without invoking any entity-level Money method`
- [ ] `it updates an existing product and dispatches ProductUpdated`
- [ ] `it rejects an update that introduces a duplicate sku owned by a different product`
- [ ] `it deletes an existing product and dispatches ProductDeleted`
- [ ] `it throws ProductNotFoundException from delete when missing`
- [ ] `it lists all products via the repository`
- [ ] `it works without an event dispatcher by skipping dispatch calls silently`

## Acceptance Criteria
- Tests use a hand-written `FakeProductRepository`, `FakeCurrencyConfig`, and `RecordingEventDispatcher` — no PHPUnit mocks.
- Service constructor parameter names follow the project rule (parameter name = interface name minus `Interface`, camelCase).
- Not `final`.
- `@throws` PHPDoc tags on every method that can throw.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
