# Task 009: Create catalog exception classes

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Add catalog domain exceptions extending `Marko\Core\Exceptions\MarkoException`, using named static factory methods that populate `message`, `context`, and `suggestion`.

## Context
- Location: `packages/catalog/src/Exception/`
- Pattern reference: project code-standards.md `ProductNotFoundException` example.
- Exceptions to create:
  - `ProductNotFoundException` — factories: `forId(int $id)`, `forSku(string $sku)`
  - `CategoryNotFoundException` — factory: `forId(int $id)`
  - `DuplicateSkuException` — factory: `forSku(string $sku)`
  - `InvalidProductDataException` — factories: `emptyName()`, `emptySku()`, `negativeBasePrice(int $amount)`, `currencyMismatch(string $expected, string $actual)` (consumed by `ProductService` to keep `MoneyException` from leaking into the domain API)
  - `InvalidCategoryDataException` — factory: `emptyName()` (consumed by `CategoryService`)
- Every factory must produce a `message`, a non-empty `context` (one short sentence describing when/why), and a non-empty `suggestion` (one sentence telling the developer what to verify or fix).

## Requirements (Test Descriptions)
- [ ] `it constructs a ProductNotFoundException for a numeric id with the id in the message`
- [ ] `it constructs a ProductNotFoundException for a sku with the sku in the message`
- [ ] `it constructs a CategoryNotFoundException for a numeric id with the id in the message`
- [ ] `it constructs a DuplicateSkuException with the conflicting sku in the message`
- [ ] `it constructs InvalidProductDataException variants for empty name empty sku and negative base price`
- [ ] `it constructs InvalidProductDataException currencyMismatch carrying both expected and actual currencies in the message`
- [ ] `it constructs InvalidCategoryDataException emptyName with a clear message`
- [ ] `it provides a non-empty context and suggestion on every factory method`
- [ ] `it has every catalog exception extend MarkoException`

## Acceptance Criteria
- All six exception classes live under `Markommerce\Catalog\Exception` namespace, one file per class.
- Tests in `packages/catalog/tests/Unit/Exception/` (one test file per exception class).
- Not `final`.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
