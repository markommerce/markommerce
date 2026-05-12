# Task 018: Write READMEs for markommerce/catalog, markommerce/money, and markommerce/money-moneyphp

**Status**: completed
**Depends on**: 017
**Retry count**: 0

## Description
Author READMEs for the three new packages introduced by this plan. Follow the project's package README conventions (mirror `marko/admin-auth/README.md` style — short, installation, quick example, link to docs).

## Context
- Files to create:
  - `packages/catalog/README.md`
  - `packages/money/README.md`
  - `packages/money-moneyphp/README.md`
- `markommerce/core` is intentionally **not** documented in this task — it remains an empty placeholder. Adding a README to it can wait until it actually exposes something.
- Reference style: `marko/admin-auth/README.md`.

### `markommerce/money` README must cover
- Package purpose: defines the public Money contract (interface-only — no implementation, no moneyphp). All commerce modules that handle prices depend on this package; they construct Money instances via the bound `MoneyFactoryInterface` instead of `new`ing a concrete class.
- Installation: `composer require markommerce/money`.
- What it exposes: `MoneyInterface`, `MoneyFactoryInterface`, `CurrencyConfigInterface`, `MoneyException`. Mention that `Money::multiply` takes a numeric **string** for precision.
- "You also need a driver" paragraph: "Install one driver package alongside this one. The default is `markommerce/money-moneyphp`."
- No code-example that constructs `Money` directly (this package has no concrete class). Show injecting `MoneyFactoryInterface` and calling `create(1000)` / `create(1000, 'EUR')` instead.

### `markommerce/money-moneyphp` README must cover
- Package purpose: default driver for `markommerce/money`, backed by `moneyphp/money`. Provides `Money` (concrete `MoneyInterface` impl), `CurrencyConfig` (returns USD), `MoneyFactory`, and registers both `CurrencyConfigInterface` and `MoneyFactoryInterface` bindings in its `module.php`.
- Installation: `composer require markommerce/money-moneyphp`.
- System requirement: `ext-intl` is required at this layer for `Money::format` (declared in composer.json, no fallback path).
- Rebinding pointer: "To swap the default currency or the Money implementation, override `CurrencyConfigInterface` or `MoneyFactoryInterface` via a Marko Preference in your application's `module.php`."
- Quick example: pulling `MoneyFactoryInterface` from the container, calling `create(1000)`, `add`, `multiply("1.05")`, `format()`.

### `markommerce/catalog` README must cover
- Package purpose (Product / Category aggregates + assignment + price resolution).
- Installation: `composer require markommerce/catalog`. Note: catalog depends on the `markommerce/money` interface package only; the consuming application also installs `markommerce/money-moneyphp` (or another driver).
- Quick example: inject `MoneyFactoryInterface` to build a `MoneyInterface`, call `ProductServiceInterface::create($sku, $name, $basePrice)`, fetch by SKU, assign to a category via `CategoryAssignmentServiceInterface`, then resolve display price via `ProductPriceServiceInterface::getBasePrice($product)`. Show the service-not-repository convention explicitly.
- Service-vs-repository convention paragraph: "Other markommerce modules should depend on `*ServiceInterface` for use cases. Repositories remain public for advanced/internal callers and tests, but are not the recommended cross-module API."
- Schema convention paragraph: "Schema for `products`, `categories`, and `product_categories` is declared on the entity classes via `#[Table]` / `#[Column]` / `#[Index]` / `#[ForeignKey]` attributes. Run Marko's `db:migrate` in the consuming application to generate and apply migrations; this package ships no SQL files."
- `ProductPriceService` paragraph: "Products store only a minor-unit amount (`basePriceAmount`); they do not store currency and have no Money accessor. Use `ProductPriceServiceInterface::getBasePrice(Product)` to resolve a `MoneyInterface`. This is the seam where future discount / tax / store-currency logic will plug in via Marko Preferences."
- Multi-store note: "`Product::$name`, `Product`'s effective currency (resolved by `ProductPriceService`), and `Category::$name` are global today; the future stores/config module will scope them per store. Look for `@todo multi-store` docblocks in the source to find every site that will change."

## Requirements (Test Descriptions)
- [ ] `it provides a markommerce/money README documenting the interface package and pointing to the default driver`
- [ ] `it provides a markommerce/money-moneyphp README documenting the moneyphp-backed driver and the rebinding seam`
- [ ] `it provides a markommerce/catalog README with installation usage and the service-over-repository convention`
- [ ] `it documents the entity-driven schema approach (no SQL files in the catalog package) in the catalog README`
- [ ] `it documents the ProductPriceService boundary and the multi-store refactor markers in the catalog README`

## Acceptance Criteria
- All three READMEs exist and are accurate against the code as merged at this point.
- No code examples reference symbols that don't exist in the merged codebase. In particular, no example in any README directly instantiates `Markommerce\Money\Moneyphp\Money` outside the money-moneyphp README itself — every other example uses `MoneyFactoryInterface`.
- Tests for this task are content-existence assertions (file exists, contains expected headings/strings) in `packages/catalog/tests/Unit/ReadmeTest.php`, `packages/money/tests/Unit/ReadmeTest.php`, and `packages/money-moneyphp/tests/Unit/ReadmeTest.php` — keep them light.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
