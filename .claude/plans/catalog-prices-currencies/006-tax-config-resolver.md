# Task 006: tax package — tax mode config + TaxModeResolver

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the `markommerce/tax` package providing the global tax-mode setting (whether stored/displayed prices include tax) and a resolver for it. This is the inclusive/exclusive *mode* only — no rate computation.

## Context
- New package `packages/tax/` (`src/`, `tests/`, `composer.json`, `module.php`). PSR-4 `Markommerce\Tax\`. Register in root `composer.json` (require + `autoload-dev` `Markommerce\Tax\Tests\`).
- `require`: `php ^8.5`, `markommerce/core`, `markommerce/config` (self.version). (No `money` dependency — this is a mode flag.)
- Config class `packages/tax/src/Config/TaxConfig.php` with `#[Config(key: 'tax/prices_include_tax')] public bool $pricesIncludeTax = false;` — no scope attribute (market axis added later by `tax-market`).
- Provide a `TaxMode` backed enum (`Inclusive`/`Exclusive`) for type-safe consumption.
- `TaxModeResolver` (`src/TaxModeResolver.php`): constructor-injects `Markommerce\Config\ConfigResolver`; `mode(): TaxMode` reads the flag via **`$configResolver->resolved(TaxConfig::class, 'pricesIncludeTax')`** (typed scalar accessor, NOT the proxy `get()`) and maps the `bool` to the enum (`true`→`Inclusive`, `false`→`Exclusive`). Honors the active scope automatically when config-scope's `ScopedConfigResolver` is bound via Preference (it `extends ConfigResolver`, so the injected base type receives the scoped instance).
- `mode()` propagates `ConfigNotFoundException|InvalidConfigValueException|SecretCipherException` from `resolved()` — add `@throws`.
- No `final`; constructor injection only.
- **Author `packages/tax/README.md`** per `.claude/package-standard.md` (mirror `packages/market/README.md`): purpose (configurable tax-inclusive/exclusive mode), install, a Quick Example showing `tax/prices_include_tax` config + `TaxModeResolver->mode()` returning a `TaxMode`, and the docs link. Note this is the *mode* only — no rate computation.

## Requirements (Test Descriptions)
- [x] `it defaults prices include tax to false`
- [x] `it resolves the exclusive mode when prices do not include tax`
- [x] `it resolves the inclusive mode when prices include tax`
- [x] `it reads the tax mode through the injected config resolver`

## Acceptance Criteria
- Package registered in root `composer.json`.
- `tax/prices_include_tax` default discoverable through the config registry.
- `packages/tax/README.md` exists and follows the package README standard.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards.

## Implementation Notes
- Created `TaxConfig` class in `src/Config/TaxConfig.php` with `#[Config(key: 'tax/prices_include_tax')] public bool $pricesIncludeTax = false;`
- Created `TaxMode` unit enum in `src/TaxMode.php` with `Inclusive` and `Exclusive` cases
- Created `TaxModeResolver` in `src/TaxModeResolver.php` injecting `ConfigResolver`; `mode()` calls `$configResolver->resolved(TaxConfig::class, 'pricesIncludeTax')` and maps `true`→`Inclusive`, `false`→`Exclusive`
- Added `@throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException` to `mode()` per standard
- Tests use `InMemoryConfigStorage` + `ConfigRegistryBuilder` directly (no proxy generation needed — uses `resolved()` not `get()`)
- Test for "reads through injected resolver" uses an anonymous class extending `ConfigResolver` that overrides `resolved()` to capture the call
- `packages/tax/README.md` authored per package-standard.md, mirroring `packages/market/README.md` structure
- PHPStan level 8, php-cs-fixer, and phpcs all pass clean
