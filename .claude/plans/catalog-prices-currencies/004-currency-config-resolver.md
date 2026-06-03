# Task 004: currency package — currency/base config + CurrencyResolver

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Create the `markommerce/currency` package: a global `currency/base` config key with a sensible default, and a `CurrencyResolver` that turns the resolved code into a `Currency` via the registry. This makes the base currency configurable for shops that use no markets at all.

## Context
- New package `packages/currency/` (`src/`, `tests/`, `composer.json`, `module.php`). PSR-4 `Markommerce\Currency\`. Register in root `composer.json` (require + `autoload-dev` `Markommerce\Currency\Tests\`).
- `require`: `php ^8.5`, `markommerce/core`, `markommerce/money` (self.version), `markommerce/config` (self.version).
- Config class `packages/currency/src/Config/CurrencyConfig.php` with `#[Config(key: 'currency/base')] public string $base = 'USD';` — follow the `#[Config]` usage in `packages/config` (attribute + inline default extracted by the config registry). Declare the property WITHOUT any scope attribute so the key stays market-agnostic; the market axis is added later by the `currency-market` bridge.
- `CurrencyResolver` (`src/CurrencyResolver.php`): constructor-injects `Markommerce\Config\ConfigResolver` (there is no narrower interface; the class is the injection point) and `CurrencyRegistryInterface`. Method `base(): Currency` reads the code via **`$configResolver->resolved(CurrencyConfig::class, 'base')`** (the typed scalar accessor — NOT the proxy object `get()`), then maps it through the registry. Because config-scope binds `ScopedConfigResolver` (which `extends ConfigResolver` and overrides `resolved()` to apply scope) via `#[Preference(replaces: ConfigResolver::class)]`, injecting the base `ConfigResolver` type automatically receives the scoped instance when config-scope is loaded — `base()` honors the active scope with NO code change here.
- `resolved()` may throw `ConfigNotFoundException|InvalidConfigValueException|SecretCipherException` — propagate via `@throws` on `base()` alongside `UnknownCurrencyException`.
- An unknown configured code surfaces the registry's `UnknownCurrencyException` (add `@throws`).
- No `final`; constructor injection only.
- **Author `packages/currency/README.md`** per `.claude/package-standard.md` (mirror `packages/market/README.md`): purpose, install, a Quick Example showing the `currency/base` config + `CurrencyResolver->base()` returning a `Currency`, and the docs link.

## Requirements (Test Descriptions)
- [x] `it defaults the base currency code to a sensible iso default`
- [x] `it resolves the configured base code into a currency value object`
- [x] `it returns the overridden base currency when config provides a different code`
- [x] `it throws UnknownCurrencyException when the configured code is unknown`
- [x] `it reads the base code through the injected config resolver`

## Acceptance Criteria
- Package registered in root `composer.json`.
- `currency/base` default is discoverable through the config registry.
- `packages/currency/README.md` exists and follows the package README standard.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards; `@throws` on `base()`.

## Implementation Notes
- `CurrencyConfig` placed in `src/Config/CurrencyConfig.php` with `#[Config(key: 'currency/base')] public string $base = 'USD';` — no scope attribute, market-agnostic.
- `CurrencyResolver` constructor-injects `ConfigResolver` and `CurrencyRegistryInterface`; `base()` calls `resolved(CurrencyConfig::class, 'base')` and maps through `$currencyRegistry->get()`.
- `@throws` on `base()` covers all four exception types: `ConfigNotFoundException|InvalidConfigValueException|SecretCipherException|UnknownCurrencyException`.
- Tests mirror the tax package pattern: `makeCurrencyConfigResolver()` helper builds a real `ConfigResolver` with `InMemoryConfigStorage`; fake-resolver test uses an anonymous subclass that overrides `resolved()`.
- Requirements 3, 4, and 5 passed immediately (implementation was already correct from requirements 1 and 2).
- `packages/currency/README.md` authored following market README structure.
- Linting (php-cs-fixer, phpcs) and PHPStan level 8 all pass with no errors.
