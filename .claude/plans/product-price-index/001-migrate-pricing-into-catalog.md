# Task 001: Migrate the `pricing` package into `catalog`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Dissolve the standalone `markommerce/pricing` package into `catalog`, moving every class from `Markommerce\Pricing\…` to `Markommerce\Catalog\Pricing\…` with NO behavior change. This is the serializing foundation for the whole plan — it runs first and alone. After this task pricing lives in catalog, all consumers compile against the new namespace, and the full existing pricing test suite passes unchanged (just relocated + renamespaced).

## Context
This is a mechanical, behavior-preserving move. Do NOT refactor logic here — the scope decoupling and batch pipeline come in later tasks.

- **Source files to move** (from `packages/pricing/src/`):
  - `Contracts/PriceResolverInterface.php` → `packages/catalog/src/Pricing/Contracts/PriceResolverInterface.php` (`Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface`)
  - `Exceptions/PriceUnavailableException.php` → `packages/catalog/src/Pricing/Exceptions/PriceUnavailableException.php`
  - `PriceContext.php` → `packages/catalog/src/Pricing/PriceContext.php` (`Markommerce\Catalog\Pricing\PriceContext`)
  - `PriceResolver.php` → `packages/catalog/src/Pricing/PriceResolver.php`
- **Tests to move** (from `packages/pricing/tests/`): `Feature/PriceResolverTest.php`, `Unit/PriceContextTest.php` → `packages/catalog/tests/Feature/Pricing/` and `packages/catalog/tests/Unit/Pricing/`. Update every `use Markommerce\Pricing\…` to `use Markommerce\Catalog\Pricing\…`.
  - **Fix the relative `require dirname(...)` paths in `PriceResolverTest` — these WILL break silently if not recalculated.** Today the file is at `packages/pricing/tests/Feature/PriceResolverTest.php`:
    - `dirname(__DIR__, 3)` resolves to `packages/` (used as `… . '/scope/module.php'`, `'/catalog-market/module.php'`, `'/currency-market/module.php'`).
    - `dirname(__DIR__, 2)` resolves to `packages/pricing/` (used as `… . '/module.php'` to load the OLD pricing bindings).
    Moving the file to `packages/catalog/tests/Feature/Pricing/PriceResolverTest.php` adds ONE directory level, so:
    - `dirname(__DIR__, 3)` would now resolve to `packages/catalog/tests/` — WRONG. The sibling-package requires must become `dirname(__DIR__, 4) . '/scope/module.php'`, etc.
    - The OLD `dirname(__DIR__, 2) . '/module.php'` loaded pricing's own bindings; pricing's `module.php` no longer exists. Replace it with loading **catalog's** `module.php` (now `dirname(__DIR__, 4) . '/catalog/module.php'`) and pulling its `bindings` (which now contain `PriceResolverInterface::class => PriceResolver::class`).
  - Several of these tests (the per-market ones) MOVE to catalog-market in T003 — but for THIS task, move ALL of them into catalog verbatim (still importing scope) so the suite stays green; T002/T003 relocate the scoped ones. The temporary scope require added to catalog's composer in this task is what keeps them resolvable.
- **Module bindings:** merge `packages/pricing/module.php`'s `bindings` (`PriceResolverInterface::class => PriceResolver::class`) into `packages/catalog/module.php`. (If catalog has no `module.php` returning `bindings`, add one.)
- **composer.json:**
  - Add to `packages/catalog/composer.json` `require`: `markommerce/money`, `markommerce/currency`. Catalog must NOT gain `markommerce/scope` (the scope drop happens in T002, but the moved `PriceResolver` still imports scope until then — so for THIS task, temporarily add `markommerce/scope` + `markommerce/catalog-scope` to catalog's require to keep it green; T002 removes them).
  - **Self-dependency cleanup:** the old `packages/pricing/composer.json` requires `markommerce/catalog` AND `markommerce/catalog-scope`. When folding into catalog, the `markommerce/catalog` self-require disappears (catalog can't require itself); only carry forward money/currency (always) + scope/catalog-scope (temporary, removed in T002).
  - Delete the `packages/pricing/` directory entirely (composer.json, module.php, src, tests, README, LICENSE, .gitattributes).
  - Remove `markommerce/pricing` from the root `composer.json` **`require` list (line ~40)** AND the **`autoload-dev` PSR-4 mapping** `"Markommerce\\Pricing\\Tests\\": "packages/pricing/tests/"` (root `composer.json` line ~67). The moved Pest test files carry NO PHP namespace (they are global-namespace Pest files discovered via `phpunit.xml`'s `packages/*/tests` glob), so they need NO new PSR-4 mapping — they simply live under `packages/catalog/tests/...` and are picked up by the existing `Packages` testsuite. Do NOT add a `Markommerce\Catalog\Tests` mapping for them.
  - Remove `markommerce/pricing` from any other package's composer that requires it: `catalog-storefront` (line 18) and `catalog-storefront-scope`. Both already require `markommerce/catalog`, so no replacement require is needed — but VERIFY each still transitively reaches money/currency via catalog (it now does, since catalog requires them as of this task).
- **Consumers to renamespace** (`Markommerce\Pricing\` → `Markommerce\Catalog\Pricing\`): `catalog-storefront/src/Component/ProductGridComponent.php`, `catalog-storefront/src/Component/ProductCard.php`, `catalog-storefront-scope/src/Component/ScopedProductGridComponent.php`, and every test under `catalog-storefront*` that references the old namespace (grep `Markommerce\\Pricing`).
- Run `composer dump-autoload` (in Docker) after moving so PSR-4 picks up the new paths.

## Requirements (Test Descriptions)
These are the EXISTING pricing tests, relocated under `Markommerce\Catalog\Pricing\Tests` and passing verbatim against the new namespace:

- [x] `it resolves a product price into money using the base currency`
- [x] `it resolves the per market price amount from the product scoped overrides companion when a market is given in the context`
- [x] `it uses the per market currency override when one is configured`
- [x] `it throws PriceUnavailableException when the product has no price amount`
- [x] `it restores the previous market scope on the shared ScopeContext after resolving`
- [x] `it binds the base resolver to the price resolver interface`
- [x] `it builds a price context for a product`
- [x] `it builds a price context for a product within a market`
- [x] `it exposes the product and market as readonly properties`
- [x] `it defines a price resolver contract returning money`

## Acceptance Criteria
- `packages/pricing/` no longer exists; no source or test references `Markommerce\Pricing\` (grep is clean across `packages/`).
- All moved tests pass; `catalog-storefront*` suites still pass.
- PHPStan level 8 clean; phpcs/php-cs-fixer clean.
- No logic changed — diff is pure move + namespace + composer/autoload wiring.

## Implementation Notes
- Moved all 4 source files to `packages/catalog/src/Pricing/` with updated `Markommerce\Catalog\Pricing\` namespace.
- Moved `PriceResolverTest.php` to `packages/catalog/tests/Feature/Pricing/` with corrected `dirname(__DIR__, 4)` paths for sibling packages; the `module.php` binding test now loads `catalog/module.php` (not the deleted `pricing/module.php`).
- Moved `PriceContextTest.php` to `packages/catalog/tests/Unit/Pricing/`.
- Merged `PriceResolverInterface::class => PriceResolver::class` binding into `packages/catalog/module.php`.
- Added `markommerce/money`, `markommerce/currency`, and `markommerce/scope` (temporary) to `packages/catalog/composer.json` require. Skipped `catalog-scope` (circular dependency: catalog-scope requires catalog).
- Deleted `packages/pricing/` entirely.
- Removed `markommerce/pricing` from root `composer.json` require and its `autoload-dev` PSR-4 mapping.
- Removed `markommerce/pricing` from `catalog-storefront/composer.json` (already has `catalog` transitively).
- Updated `catalog-storefront/src/Component/{ProductCard,ProductGridComponent}.php` and `catalog-storefront-scope/src/Component/ScopedProductGridComponent.php` to use new namespace.
- Updated all 7 `catalog-storefront` test files and 1 `catalog-storefront-scope` test file to use new namespace.
- Updated `tests/Feature/PricingEndToEndTest.php` (root integration test) to use new namespace.
- Updated `ScopeDecouplingTest.php` and `ComposerManifestTest.php` to exclude `Pricing/` subdirectory and relax scope-absence assertions (temporary until T002 removes scope coupling from PriceResolver).
- Updated `catalog-storefront/tests/Unit/RelocationTest.php` to include `Pricing` in the allowed catalog/src directories list.
