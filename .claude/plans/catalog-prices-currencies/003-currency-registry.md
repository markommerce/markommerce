# Task 003: CurrencyRegistryInterface + default registry (+ money README)

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Provide a swappable registry that resolves an ISO 4217 code to a `Currency` value object, with a default implementation seeded with a common subset of currencies. This is how the rest of the system turns a configured currency code into a `Currency`. As the terminal task of the `money` package (after `Currency` in 001 and `Money`/`RoundingMode` in 002), this task also authors the package README.

## Context
- Files: `packages/money/src/Contracts/CurrencyRegistryInterface.php`, `packages/money/src/DefaultCurrencyRegistry.php`, `packages/money/module.php`.
- `CurrencyRegistryInterface`: `get(string $code): Currency` (loud `UnknownCurrencyException extends MarkoException` on miss), `has(string $code): bool`, `all(): array<string, Currency>`.
- Interface parameter naming follows convention (camelCase of interface minus `Interface`) where a registry instance is injected elsewhere.
- `DefaultCurrencyRegistry` seeds a reasonable ISO 4217 subset (at minimum USD, EUR, GBP, JPY, PLN, CHF) with correct scale/symbol/name; JPY must have scale 0 to prove non-2dp handling.
- `module.php` binds `CurrencyRegistryInterface::class => DefaultCurrencyRegistry::class` (Preference-overridable). Mirror the `bindings` style from `packages/catalog/module.php`.
- No `final`; the default registry is overridable via Marko Preferences.
- **Author `packages/money/README.md`** per `.claude/package-standard.md`, mirroring the slim format of `packages/market/README.md` (title + one-line purpose, Installation, Quick Example, Documentation link). Cover `Money` (BigDecimal arithmetic + `RoundingMode`), `Currency`, and the `CurrencyRegistryInterface` with accurate examples matching the final APIs from tasks 001/002/003.

## Requirements (Test Descriptions)
- [x] `it returns a currency value object for a known iso code`
- [x] `it reports whether a currency code is known`
- [x] `it throws UnknownCurrencyException for an unknown code`
- [x] `it returns all seeded currencies keyed by code`
- [x] `it seeds JPY with a scale of zero`
- [x] `it binds the default registry to the interface in module.php`

## Acceptance Criteria
- All requirements have passing tests; coverage ≥ 80%.
- `module.php` binding is present and resolvable.
- `packages/money/README.md` exists and follows the package README standard with accurate `Money`/`Currency`/registry examples.
- Code follows standards; `@throws` on `get`.

## Implementation Notes
- Created `src/Contracts/CurrencyRegistryInterface.php` with `get`, `has`, and `all` methods.
- Created `src/Exceptions/UnknownCurrencyException.php` extending `MarkoException` with `forCode` factory.
- Created `src/DefaultCurrencyRegistry.php` seeding USD, EUR, GBP, JPY (scale 0), PLN, CHF.
- Created `module.php` binding `CurrencyRegistryInterface::class` to `DefaultCurrencyRegistry::class`.
- Created `packages/money/README.md` covering Money, Currency, and CurrencyRegistryInterface.
- All 6 tests in `tests/Unit/CurrencyRegistryTest.php` pass; full suite 22/22 green.
