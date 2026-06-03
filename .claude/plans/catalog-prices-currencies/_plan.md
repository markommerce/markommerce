# Plan: Catalog Prices & Currencies

## Created
2026-06-03

## Status
completed

## Follow-up (added after initial 001–013 completion)
Tasks 014–015 extend the shipped pricing feature into demo data and the storefront UI. Tasks 001–013 are committed (64bd8ac); 014–015 follow in a second commit.

## Objective
Add money, currency, tax-mode, and an extensible pricing pipeline to markommerce so products carry a price that is exactly represented, market-scopable, and rendered correctly per locale — while single-market shops pull zero scoping overhead.

## Related Issues
none

## Discovery Notes
- `Product` (`packages/catalog/src/Entity/Product.php`) currently has only `id, sku, name, description`. The `catalog-market` and `config-market` `boot` closures are live placeholders explicitly waiting for price/scoped fields.
- **Config layering already supports the requirement.** A package declares a config class with `#[Config(key: ...)]` + default value; `ScopedConfigResolver` (config-scope) consults `ScopedFieldRegistry->axesForProperty()` and applies scoped overrides when a key is registered on an axis, else returns the global default. A bridge's `boot` can register a config key on the `market` axis **imperatively** (the same `register()` catalog-locale uses for entity fields), so core config classes stay market-agnostic.
- **Hard constraint:** scoped entity overrides persist in a single JSON `scopes` column and `ScopedDataSerializer` only round-trips **scalars, BackedEnum, DateTimeImmutable** — a `Money` object will NOT round-trip. Resolution (chosen): store only a decimal **amount** scalar on the product; resolve **currency from config**. This also realizes "currency is config-driven, not row-driven."
- Marko `#[Column]` has no precision/scale params — decimal columns use a raw `type: 'decimal(20,4)'` string; the value is stored as a numeric string.
- `brick/math` is not yet installed; it is added to the new `money` package. Schema is attribute-derived (no migration files). Tests are Pest; each new package registers its `tests/` PSR-4 namespace in the root `composer.json` `autoload-dev`.

### Resolved decisions (from clarification)
- **Price storage:** amount only (`Product.priceAmount`, decimal scalar); currency from `currency/base` config.
- **Decimal column:** `decimal(20,4)`.
- **Package topology:** separate `currency`, `tax`, and `pricing` packages (plus core `money` + `money-intl`).
- **Market bridges:** `catalog-market` registers `Product.priceAmount`; `currency-market` registers `currency/base`; `tax-market` registers the tax-mode key. Each bridge only adds an override layer.

## Scope

### In Scope
- `money` package: `Money` (BigDecimal-backed, immutable), `Currency` VO, `RoundingMode`, `CurrencyRegistryInterface` + default registry, loud-error arithmetic.
- `money-intl` package: locale-aware `MoneyFormatter` via `intl` `NumberFormatter::CURRENCY`, locale from `ScopeContext`.
- `currency` package: `currency/base` global config + `CurrencyResolver` (config → `Currency`).
- `tax` package: `tax/prices_include_tax` global config + `TaxModeResolver`.
- `currency-market`, `tax-market` bridges: register their config keys on the `market` axis.
- `catalog`: add `priceAmount` `decimal(20,4)` column to `Product`.
- `catalog-market`: register `Product.priceAmount` on the `market` axis.
- `pricing` package: `PriceResolverInterface` (single method `resolve(): Money`) + `PriceContext` VO + base resolver that returns the scope-resolved stored amount assembled into `Money`. Plugin seam designed (not implemented) for sale/tier/group prices. Tax mode is NOT carried on the price result — it is resolved independently via `TaxModeResolver`.
- End-to-end feature tests for Tier-1 (global currency/tax) and Tier-3 (per-market price + currency + tax-mode overrides). Each new package ships its own README, authored within that package's task.
- **Follow-up:** catalog seeder assigns random `priceAmount`s to seeded products (task 014); catalog-storefront product card displays the resolved, locale-formatted price (task 015, base/global-currency storefront).

### Out of Scope
- Actual tax-rate computation / tax tables (only the inclusive/exclusive *mode* flag and seam).
- Sale / tier / quantity / customer-group prices (the resolver Plugin seam is designed but no concrete plugins built).
- Multi-currency display conversion / exchange rates.
- Per-market *scoped* price display in `catalog-storefront-scope` (requires loading products with `ProductScopedOverrides` companions under an active market scope). Task 015 covers only the base/global-currency storefront card.
- Admin UI for editing prices.

## Success Criteria
- [ ] `Money` performs exact BigDecimal arithmetic; mismatched-currency ops and missing `RoundingMode` raise loud exceptions.
- [ ] A shop with **no** market/locale packages can set `currency/base`, store a product price, resolve it, and format it.
- [ ] With `catalog-market` + `currency-market` loaded, a per-market price and per-market currency override resolve correctly and fall back to global defaults otherwise.
- [ ] `MoneyFormatter` renders a `Money` per the active locale via `intl`, and fails loudly if `ext-intl` is absent.
- [ ] The tax-inclusive/exclusive mode is globally configurable and per-market overridable, resolved independently of price.
- [ ] All new packages are registered in root `composer.json` and ship a README per `package-standard.md`.
- [ ] All tests passing; coverage ≥ 80%.
- [ ] Code follows project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | `money` scaffold + `Currency` value object | - | completed |
| 002 | `Money` value object + `RoundingMode` | 001 | completed |
| 003 | `CurrencyRegistryInterface` + default registry + money README | 001, 002 | completed |
| 004 | `currency` pkg: `currency/base` config + `CurrencyResolver` | 003 | completed |
| 005 | `currency-market` bridge (register `currency/base` on market) | 004 | completed |
| 006 | `tax` pkg: `tax/prices_include_tax` config + `TaxModeResolver` | - | completed |
| 007 | `tax-market` bridge (register tax mode on market) | 006 | completed |
| 008 | `money-intl` pkg: locale-aware `MoneyFormatter` | 002 | completed |
| 009 | `catalog`: add `Product.priceAmount` decimal column | - | completed |
| 010 | `catalog-market`: register `Product.priceAmount` on market | 009 | completed |
| 011 | `pricing` scaffold: `PriceContext` + `PriceResolverInterface` | 002, 009 | completed |
| 012 | base `PriceResolver` (amount → Money via currency) + pricing README | 011, 004, 010 | completed |
| 013 | End-to-end feature tests (Tier-1 + Tier-3) | 012, 005, 007, 008 | completed |
| 014 | catalog seeder: assign random prices to seeded products | 009 | completed |
| 015 | catalog-storefront: show formatted price on the product card | 012, 008, 014 | completed |

(Per-package READMEs are folded into each package's terminal task: money→003, currency→004, currency-market→005, tax→006, tax-market→007, money-intl→008, pricing→012.)

## Architecture Notes
- **Layering principle:** `money` depends on nothing commerce-specific (only `core` for `MarkoException` + `brick/math`). `currency`/`tax`/`pricing` are domain packages. `*-market` packages are bridges that ONLY register override layers — every market-scopable setting has a global default in its base package first.
- **Currency source of truth = config**, not the product row. The product stores a bare amount; `CurrencyResolver` reads `currency/base` (global, market-overridable) to produce the `Currency`. `PriceResolver` assembles the `Money`.
- **Storage:** `Product.priceAmount` is a nullable `string` mapped to `decimal(20,4)`. Per-market overrides round-trip as a decimal scalar through the existing JSON scoped-overrides path. Rounding to scale happens explicitly on write; `Money` stays arbitrary-precision in memory.
- **Verified scope-read mechanism (do not re-derive):**
  - *Entity (`priceAmount`):* the per-market value lives in the attached `ProductScopedOverrides` companion, NOT on the row. Read it via `Markommerce\Scope\Resolver\ScopeResolver::resolved($product, 'priceAmount')` against the active `ScopeContext`. `ScopeResolver` resolves axes through `ScopeMetadataFactory`→`ScopedFieldRegistry`, so task 010's imperative `register()` surfaces here. `ScopeMetadataFactory` freezes per-class metadata on first `for()` — register before first resolve.
  - *Config (`currency/base`, tax flag):* `ScopedConfigResolver::resolvedAt()` reads axes from `ScopedFieldRegistry->axesForProperty()` (NOT from `#[Scoped]` attributes), so the `currency-market`/`tax-market` bridges' imperative `register()` calls are honored and the core config classes stay attribute-free / market-agnostic. Consumers read scalars via `ConfigResolver::resolved($configClass, $field)`; config-scope's `ScopedConfigResolver` (a `#[Preference]` over `ConfigResolver`) transparently applies scope.
  - *`ScopeContext` is a mutable singleton with no save/restore helper.* The base `PriceResolver` must snapshot/restore the `market` axis around its read (task 012) to avoid leaking into shared state. Tests must `clearAll()` between scenarios and register concrete non-default market scopes (the `market` axis ships only `default`).
- **Extensibility:** `PriceResolverInterface` is the seam; future sale/tier/group prices arrive as Marko `#[Plugin]`s decorating `resolve()`. Bindings registered via `module.php` Preferences.
- **Tax:** `Money` is tax-agnostic. Tax inclusive/exclusive is a standalone config flag (`tax` package) exposed via `TaxModeResolver`, globally configurable and per-market overridable. It is resolved **independently** — `pricing` does NOT depend on `tax`, and `PriceResolverInterface::resolve()` returns only `Money`. Consumers needing the mode (cart, checkout, display) inject `TaxModeResolver` directly. No rate math in this plan.

## Risks & Mitigations
- *Scoped value object can't round-trip through JSON storage* → store a decimal scalar amount only; assemble `Money` at the resolver boundary. (Resolved by design.)
- *`decimal(20,4)` truncates higher-precision `Money`* → round explicitly to scale on write with an explicit `RoundingMode`; document the storage clamp. Amounts needing >4dp are out of scope.
- *`brick/math` is a new external dependency in a dependency-light repo* → confined to the `money` package; public `Money`/`RoundingMode` API wraps it so the dependency is swappable without touching callers.
- *`ext-intl` may be absent in some environments* → `MoneyFormatter` checks and throws a loud exception with a `suggestion` to enable the extension.
- *Coupling a bridge upward* → bridges depend on their own base package + `market` + `config-scope` only; never the reverse.
