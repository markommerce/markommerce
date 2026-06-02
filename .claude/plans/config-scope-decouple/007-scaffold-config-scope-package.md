# Task 007: Scaffold the markommerce/config-scope package skeleton

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create the `packages/config-scope/` directory and populate it with the canonical Markommerce package skeleton: `composer.json`, `module.php` placeholder, `README.md`, `LICENSE`, `.gitattributes`, `tests/Pest.php`, `tests/PackageScaffoldingTest.php`, `tests/ReadmeTest.php`, and `tests/Unit/AutoloadableClassesTest.php`. The skeleton should pass its own scaffolding tests immediately; production source classes land in later tasks (008–010). The `composer.json` declares dependencies on `markommerce/config` and `markommerce/scope`. The `module.php` boot closure is empty (a placeholder docblock noting that real wiring lands in task 008).

## Context
- Related files (new):
  - `packages/config-scope/composer.json`
  - `packages/config-scope/module.php`
  - `packages/config-scope/README.md` (placeholder; full README in task 014)
  - `packages/config-scope/LICENSE`
  - `packages/config-scope/.gitattributes`
  - `packages/config-scope/src/.gitkeep` (production classes added in 008)
  - `packages/config-scope/tests/Pest.php`
  - `packages/config-scope/tests/PackageScaffoldingTest.php`
  - `packages/config-scope/tests/ReadmeTest.php`
  - `packages/config-scope/tests/Unit/AutoloadableClassesTest.php`
- Patterns to follow: copy structure from `packages/catalog-scope/` (composer.json, README header) and `packages/catalog-market/` (Pest.php, scaffolding tests). PSR-4 root namespace: `Markommerce\\ConfigScope\\` → `src/`. Tests namespace: `Markommerce\\ConfigScope\\Tests\\` → `tests/`.

## Requirements (Test Descriptions)
- [x] `it declares its name as markommerce/config-scope in composer.json`
- [x] `it declares its type as marko-module in composer.json`
- [x] `it requires markommerce/config and markommerce/scope as self.version dependencies`
- [x] `it declares the Markommerce\\ConfigScope\\ namespace mapped to src/ in autoload psr-4`
- [x] `it declares the Markommerce\\ConfigScope\\Tests\\ namespace mapped to tests/ in autoload-dev psr-4`
- [x] `it declares extra.marko.module true in composer.json`
- [x] `it exposes a module.php that returns an array with a callable boot closure`
- [x] `it ships a README.md containing the package name as the H1 heading`
- [x] `it is registered in the root composer.json require block under markommerce/config-scope`
- [x] `it autoloads all classes under packages/config-scope/src via PSR-4`

## Acceptance Criteria
- All requirements have passing tests.
- `composer validate` passes on `packages/config-scope/composer.json`.
- The placeholder `module.php` boot closure does not throw; task 008 replaces it with real wiring.
- `composer dump-autoload` from the monorepo root finds `Markommerce\\ConfigScope\\Tests\\` test classes.

## Implementation Notes
- Task 006 stubs were already in good shape: `composer.json`, `README.md`, `LICENSE`, `.gitattributes`, `tests/Pest.php`, and an empty `src/` directory all existed.
- Created `packages/config-scope/module.php` as a placeholder with a `static function (ContainerInterface $container): void {}` boot closure. Real wiring lands in task 008.
- Created `tests/PackageScaffoldingTest.php` covering all composer.json structure requirements.
- Created `tests/ReadmeTest.php` asserting the H1 heading matches the package name.
- Created `tests/Unit/AutoloadableClassesTest.php` which handles the empty `src/` directory gracefully by asserting the package directory exists, then iterating PHP files if any exist.
- All 10 tests pass; `composer validate` is clean; pre-existing catalog-market failures are unrelated.
