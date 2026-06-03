# Task 009: catalog — add Product.priceAmount decimal column

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Add a base price amount to the `Product` entity as a nullable `decimal(20,4)` column. Only the amount is stored on the row; the currency is resolved from config by the pricing layer, and per-market overrides are added by `catalog-market`. Keeps `catalog` free of any `money`/pricing dependency.

## Context
- File: `packages/catalog/src/Entity/Product.php`.
- Add a property mapped via the Marko `#[Column]` attribute using a raw decimal type string (the attribute has no precision/scale params):
  ```php
  #[Column(name: 'price_amount', type: 'decimal(20,4)', nullable: true)]
  public ?string $priceAmount = null;
  ```
  Store as `string` to preserve precision (no float). Property name `priceAmount`; column `price_amount`.
- Do NOT add a currency column — currency is config-driven (decided in plan).
- No new dependency added to `catalog`'s `composer.json`.
- Add/extend a test under `packages/catalog/tests/` asserting the entity exposes and round-trips `priceAmount`, including null (no price set).
- Follow the existing `Product` entity column style.

## Requirements (Test Descriptions)
- [x] `it stores a product price amount as a decimal string`
- [x] `it allows a product to have no price amount set`
- [x] `it preserves price amount precision without floating point drift`
- [x] `it maps the price amount property to the price_amount column`

## Acceptance Criteria
- `Product` exposes `priceAmount` mapped to `price_amount` `decimal(20,4)` nullable.
- No float used anywhere for the amount.
- All requirements have passing tests; no coverage decrease.
- Follows standards.

## Implementation Notes
- Added `public ?string $priceAmount = null;` with `#[Column(name: 'price_amount', type: 'decimal(20,4)', nullable: true)]` to `Product`.
- Four new tests added to `packages/catalog/tests/Unit/Entity/ProductTest.php` covering assignment, null default, string precision preservation, and column attribute metadata.
- No new dependencies; no float used.
