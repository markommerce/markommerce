# Task 001: Scaffold `markommerce/market` axis package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `markommerce/market` package as a thin axis declaration, mirroring the existing `markommerce/locale` package. The package ships only `config/scope.php` declaring the `market` axis with a single `default` scope path. No PHP source classes ship in P4. Merchants extend the axis with their real markets via their own config overlay.

## Context
- Mirror package: `packages/locale/` (`composer.json`, `config/scope.php`, `tests/`, `LICENSE`, `.gitattributes`, `README.md`).
- Locale's `config/scope.php` is the template:
  ```php
  return ['axes' => ['locale' => ['default' => 'default', 'scopes' => ['default' => []]]]];
  ```
- Locale's tests cover: `config/scope.php` shape, composer requires `markommerce/scope`, `extra.marko.module=true`, autoload `Markommerce\Locale\` namespace registered for the (currently empty) `src/`.
- Root `composer.json` needs `markommerce/market` added to `require` and `Markommerce\Market\Tests\\` added to `autoload-dev.psr-4`.
- README test pattern: `packages/locale/tests/ReadmeTest.php` asserts standards from `docs/DOCS-STANDARDS.md`.

## Requirements (Test Descriptions)
- [x] `it declares a market axis with default 'default' and a single scope path 'default' in config/scope.php`
- [x] `it requires markommerce/scope in composer.json`
- [x] `it declares itself as a marko-module via composer extra.marko.module=true`
- [x] `it uses the Markommerce\\Market\\ namespace for any future autoload`
- [x] `it registers the package in the root composer.json require block and adds Markommerce\\Market\\Tests\\ to autoload-dev.psr-4`
- [x] `it ships a README that follows the project package README standards`

## Acceptance Criteria
- `packages/market/{composer.json,LICENSE,.gitattributes,README.md,config/scope.php,tests/Pest.php,tests/PackageScaffoldingTest.php,tests/ReadmeTest.php}` exist.
- Root `composer.json` lists `markommerce/market` under `require` and `Markommerce\Market\Tests\\` under `autoload-dev.psr-4`.
- `composer dump-autoload` succeeds.
- Package test suite is green.
- PHPStan + PHP-CS-Fixer clean.

## Implementation Notes

- Created `packages/market/` mirroring `packages/locale/` structure exactly.
- `config/scope.php` declares `market` axis with `default` scope.
- `composer.json` requires `markommerce/scope`, declares `Markommerce\Market\` autoload namespace.
- Root `composer.json` updated with `markommerce/market` in `require` and `Markommerce\Market\Tests\` in `autoload-dev.psr-4`.
- Test path fix: `dirname(__DIR__, 3)` used in root composer.json test (tests/ → market/ → packages/ → root).
- PHPStan test exclusion (`packages/*/tests`) means `file_get_contents` / `json_decode` patterns are not flagged.
- All 6 tests pass, PHP-CS-Fixer and PHPStan clean on non-test source files.
