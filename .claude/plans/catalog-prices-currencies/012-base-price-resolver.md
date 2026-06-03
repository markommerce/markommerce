# Task 012: base PriceResolver — amount → Money via currency (+ pricing README)

**Status**: complete
**Depends on**: 011, 004, 010
**Retry count**: 0

## Description
Implement the base `PriceResolver` that returns a product's effective price as a `Money`: it reads the (market-scope-resolved) `Product.priceAmount` and assembles it with the `Currency` from `CurrencyResolver`. Bind it to `PriceResolverInterface`. This is the bottom of the decoration chain that plugins wrap later. As the terminal task of the `pricing` package, this also authors the package README. `pricing` depends only on `money`/`currency`/`catalog`/`catalog-scope`/`scope` — NOT on `tax`.

## Context
- Files: `packages/pricing/src/PriceResolver.php`, binding in `packages/pricing/module.php`.
- Add `require`: `markommerce/currency`, `markommerce/catalog-scope`, `markommerce/scope` (self.version) to `packages/pricing/composer.json`.
- **Scope-read mechanism (verified against the codebase — implement exactly this):**
  - The per-market `priceAmount` override does NOT live on the `Product` row; it lives in the attached `ProductScopedOverrides` companion (a `HasScopesInterface`). The correct read API is `Markommerce\Scope\Resolver\ScopeResolver::resolved(Entity $entity, string $property): mixed`, NOT a direct `$product->priceAmount` read and NOT `ScopeContext` alone.
  - **Constructor-inject `Markommerce\Scope\Resolver\ScopeResolver`, `Markommerce\Scope\Context\ScopeContext`, and `CurrencyResolver` (task 004).** `ScopeResolver::resolved()` reads the *active* `ScopeContext` internally and walks the companion overrides; it returns the override for the active scope or falls back to the raw `$product->priceAmount`.
  - To honor `context.market`, the resolver must make `market` active on the singleton `ScopeContext` for the duration of the read. `ScopeContext` is a **mutable singleton with no built-in save/restore** — you MUST snapshot and restore to avoid leaking the market into unrelated code that shares the singleton (task 013 asserts no leakage):
    ```php
    $previous = $this->scopeContext->get('market');   // ?string
    try {
        if ($context->market !== null) {
            $this->scopeContext->in('market', $context->market);
        }
        $amount = $this->scopeResolver->resolved($context->product, 'priceAmount');
        $currency = $this->currencyResolver->base();   // honors the same active market via config-scope
    } finally {
        if ($previous === null) {
            $this->scopeContext->clear('market');
        } else {
            $this->scopeContext->in('market', $previous);
        }
    }
    ```
  - `ScopeContext::in('market', $path)` validates `$path` against the registered market hierarchy and throws `ScopeContextException`/`UnknownAxisException` if the market is unknown — add these to `@throws` and document that `context.market` must be a registered market scope.
  - **Caller contract:** the `Product` inside `PriceContext` must already have its `ProductScopedOverrides` companion attached (i.e. loaded via the repository with extenders linked). Document this on `PriceContext`/`resolve()` — a bare `new Product()` with no companion will only ever yield the global amount.
- `resolve(PriceContext $context): Money`:
  - Obtain the scope-resolved `priceAmount` for `context.market` via the snapshot/restore block above.
  - If null → throw `PriceUnavailableException` (loud; message/context/suggestion).
  - Currency from `CurrencyResolver->base()` (which itself honors the active market scope via config-scope's `ScopedConfigResolver`).
  - Return `Money::of(priceAmount, currency)`.
- `module.php` binds `PriceResolverInterface::class => PriceResolver::class` (Preference-overridable; plugins decorate `resolve`).
- No `final`; constructor injection only; `@throws`.
- **Author `packages/pricing/README.md`** per `.claude/package-standard.md` (mirror `packages/market/README.md`): purpose, install, a Quick Example showing `PriceContext::forProduct()` → `PriceResolverInterface::resolve(): Money`, a note on the `#[Plugin]` decoration seam, and the docs link.

## Requirements (Test Descriptions)
- [x] `it resolves a product price into money using the base currency`
- [x] `it resolves the per market price amount from the product scoped overrides companion when a market is given in the context`
- [x] `it uses the per market currency override when one is configured`
- [x] `it throws PriceUnavailableException when the product has no price amount`
- [x] `it restores the previous market scope on the shared ScopeContext after resolving`
- [x] `it binds the base resolver to the price resolver interface`

## Acceptance Criteria
- `PriceResolverInterface` resolves to a working `Money` for Tier-1 (no market) and Tier-3 (market override) setups.
- Resolving with a `context.market` set leaves the shared `ScopeContext` `market` axis in its prior state (snapshot/restore verified).
- `packages/pricing/README.md` exists and follows the package README standard.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards; `@throws` documented (`PriceUnavailableException`, `ScopeContextException`, `UnknownAxisException`, and currency/config throwables).

## Implementation Notes
- `PriceResolver` constructor-injects `ScopeResolver`, `ScopeContext`, `CurrencyResolver` (no `final`; no traits).
- The snapshot/restore block wraps both the `ScopeResolver::resolved()` call and `CurrencyResolver::base()` in a try/finally so the shared `ScopeContext` is always restored even on exception.
- `module.php` binds `PriceResolverInterface::class => PriceResolver::class`.
- Feature tests boot scope + market + catalog + catalog-scope + catalog-market modules; Tier-3 currency override test additionally boots currency-market and wires a `ScopedConfigResolver` with `InMemoryScopedConfigStorage`.
- `packages/pricing/README.md` written per package-standard with purpose, install, Quick Example, Plugin decoration seam, and docs link.
