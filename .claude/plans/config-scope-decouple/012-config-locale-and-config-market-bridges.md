# Task 012: Scaffold markommerce/config-locale and markommerce/config-market (no-op bridge placeholders)

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Create two new no-op bridge packages mirroring `packages/catalog-market/` shape (where catalog-market ships as a placeholder per P4): `markommerce/config-locale` (depends on `config-scope` + `locale`) and `markommerce/config-market` (depends on `config-scope` + `market`). Both ship a `module.php` with a `require` block and an empty `boot` closure carrying a documenting docblock that explains the placeholder status. Both ship the canonical Markommerce scaffolding (`composer.json`, `README.md`, `LICENSE`, `.gitattributes`, `tests/Pest.php`, `tests/PackageScaffoldingTest.php`, `tests/ReadmeTest.php`, `tests/Unit/AutoloadableClassesTest.php`, `tests/Unit/BootClosureTest.php`, `tests/Unit/ComposerDepsTest.php`).

## Context
- Related files (new):
  - `packages/config-locale/composer.json`
  - `packages/config-locale/module.php`
  - `packages/config-locale/README.md`
  - `packages/config-locale/LICENSE`
  - `packages/config-locale/.gitattributes`
  - `packages/config-locale/src/.gitkeep`
  - `packages/config-locale/tests/Pest.php`
  - `packages/config-locale/tests/PackageScaffoldingTest.php`
  - `packages/config-locale/tests/ReadmeTest.php`
  - `packages/config-locale/tests/Unit/AutoloadableClassesTest.php`
  - `packages/config-locale/tests/Unit/BootClosureTest.php`
  - `packages/config-locale/tests/Unit/ComposerDepsTest.php`
  - Same set under `packages/config-market/`.
- Related (read-only):
  - `packages/catalog-market/module.php` (placeholder boot closure pattern)
  - `packages/catalog-market/tests/Unit/BootClosureTest.php` (boot-closure invocation test pattern)
  - `packages/catalog-locale/module.php` (require + boot shape, even though that one has real registrations)
- Patterns to follow: `catalog-market`'s `module.php` ships with an empty boot closure inside a documented docblock. Mirror exactly. `BootClosureTest` invokes the closure with a `ScopedFieldRegistry` and asserts no entity class has scoped properties afterwards.

## Requirements (Test Descriptions)
- [ ] `it declares config-locale's name as markommerce/config-locale with type marko-module in composer.json`
- [ ] `it requires markommerce/config-scope and markommerce/locale as self.version dependencies in config-locale's composer.json`
- [ ] `it declares the Markommerce\\ConfigLocale\\ namespace mapped to src/ in config-locale's autoload psr-4`
- [ ] `it exposes a module.php that returns an array with require + a callable boot closure in config-locale`
- [ ] `it leaves the ScopedFieldRegistry empty when config-locale's boot closure runs against an empty registry (no-op)`
- [ ] `it ships a README.md for config-locale documenting the placeholder status`
- [ ] `it declares config-market's name as markommerce/config-market with type marko-module in composer.json`
- [ ] `it requires markommerce/config-scope and markommerce/market as self.version dependencies in config-market's composer.json`
- [ ] `it declares the Markommerce\\ConfigMarket\\ namespace mapped to src/ in config-market's autoload psr-4`
- [ ] `it leaves the ScopedFieldRegistry empty when config-market's boot closure runs against an empty registry (no-op)`
- [ ] `it ships a README.md for config-market documenting the placeholder status`
- [ ] `it is registered in the root composer.json require block under markommerce/config-locale`
- [ ] `it is registered in the root composer.json require block under markommerce/config-market`

## Acceptance Criteria
- All requirements have passing tests.
- `composer validate` passes on both new composer.json files.
- The `module.php` boot closures explicitly document "placeholder — no fields registered today" with a pointer to FEATURES.md tier rows.
- PHPStan level 8 clean for both packages.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
