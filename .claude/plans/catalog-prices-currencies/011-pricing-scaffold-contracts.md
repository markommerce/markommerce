# Task 011: pricing scaffold — PriceContext + PriceResolverInterface

**Status**: complete
**Depends on**: 002, 009
**Retry count**: 0

## Description
Create the `markommerce/pricing` package contracts: the `PriceContext` value object describing what is being priced, and `PriceResolverInterface` — the extension seam that future sale/tier/group-price plugins will decorate. No concrete resolution logic yet (that is task 012).

## Context
- New package `packages/pricing/` (`src/Contracts/`, `src/`, `tests/`, `composer.json`, `module.php`). PSR-4 `Markommerce\Pricing\`. Register in root `composer.json` (require + `autoload-dev` `Markommerce\Pricing\Tests\`).
- `require`: `php ^8.5`, `markommerce/core`, `markommerce/money`, `markommerce/catalog` (self.version). (Currency + scope wiring is added in 012; `pricing` does NOT depend on `tax` — tax mode is a separate, independently-resolved concern.)
- `PriceContext` (`src/PriceContext.php`): `readonly class` with `Product $product` and `?string $market = null` to start. Designed for extension — document that `qty`, `customerGroup`, `date` are intended future fields. Provide a clear named constructor (e.g. `PriceContext::forProduct(Product $product, ?string $market = null)`).
- **Document the `Product` companion contract:** for per-market price resolution (task 012) to work, the `Product` passed here must carry its `ProductScopedOverrides` companion (i.e. it was loaded via the repository with extenders linked). A bare `new Product()` resolves only the global `priceAmount`. State this in the `PriceContext` docblock so callers know the precondition.
- `PriceResolverInterface` (`src/Contracts/PriceResolverInterface.php`): `resolve(PriceContext $context): Money` with a `@throws` for an unavailable price. Document that implementations are decorated via Marko `#[Plugin]`s (the seam) — do NOT build any plugin.
- Define `PriceUnavailableException extends MarkoException` (`src/Exceptions/`) for products with no resolvable price.
- No `final`; readonly where immutable; constructor injection only.

## Requirements (Test Descriptions)
- [x] `it builds a price context for a product`
- [x] `it builds a price context for a product within a market`
- [x] `it exposes the product and market as readonly properties`
- [x] `it defines a price resolver contract returning money`

## Acceptance Criteria
- Package registered in root `composer.json`.
- `PriceContext` and `PriceResolverInterface` exist with the documented extension seam.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards.

## Implementation Notes
- `PriceContext` is a `readonly class` with `private` constructor; named ctor `forProduct(Product $product, ?string $market = null)` is the only entry point.
- `PriceResolverInterface` (`src/Contracts/`) declares `resolve(PriceContext): Money` with `@throws PriceUnavailableException`; docblock calls out the Marko `#[Plugin]` decoration seam.
- `PriceUnavailableException` (`src/Exceptions/`) extends `MarkoException` with `forContext(PriceContext)` static factory.
- PHPStan level 8: no errors. php-cs-fixer: no remaining issues.
