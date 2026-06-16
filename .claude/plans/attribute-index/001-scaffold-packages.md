# Task 001: Scaffold `indexer` + `catalog-attribute-index` packages

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Create the shared `markommerce/indexer` kernel package and the `markommerce/catalog-attribute-index`
package: composer.json, `module.php` skeleton, Pest bootstrap, `src/`+`tests/`, root wiring.
Loadable, testable shells.

## Context
- Mirror existing kernel + index packages: STUDY `packages/scope/composer.json` (kernel) and
  `packages/catalog-price-index/composer.json` (index).
- `indexer/composer.json`: `type: marko-module`, PSR-4 `Markommerce\Indexer\` → `src/`, tests
  `Markommerce\Indexer\Tests\`. Requires `php: ^8.5`, `marko/core`, `marko/database`,
  `markommerce/scope`; require-dev `marko/testing`, `markommerce/testing`, `pestphp/pest: ^4.0`.
- `catalog-attribute-index/composer.json`: PSR-4 `Markommerce\CatalogAttributeIndex\` → `src/`.
  Requires `php: ^8.5`, `marko/core`, `marko/database`, `markommerce/indexer`,
  `markommerce/catalog`, `markommerce/catalog-attribute`, `markommerce/catalog-attribute-scope`,
  `markommerce/attribute`, `markommerce/scope`; require-dev as above.
- Add BOTH to root `composer.json` `require` + test namespaces to root `autoload-dev` (mirror how
  `catalog-price-index` is wired). Add `.gitattributes` + `LICENSE` per siblings.

## Requirements (Test Descriptions)
- [x] `it autoloads a class from the Markommerce\Indexer namespace`
- [x] `it autoloads a class from the Markommerce\CatalogAttributeIndex namespace`
- [x] `it marks indexer as a marko module in composer extra`
- [x] `it marks catalog-attribute-index as a marko module in composer extra`
- [x] `it declares markommerce/indexer as a dependency of catalog-attribute-index`

## Acceptance Criteria
- Both packages autoload; `composer test` discovers their suites.
- Layout matches the kernel + index package conventions.

## Implementation Notes
- Created `packages/indexer/` with composer.json (marko-module, PSR-4 `Markommerce\Indexer\`), `src/Indexer.php` placeholder, `tests/PackageScaffoldingTest.php`, `tests/Pest.php`, `module.php`, `LICENSE`, `.gitattributes`, `README.md`.
- Created `packages/catalog-attribute-index/` with the same structure; composer.json requires `markommerce/indexer` plus catalog/attribute/scope siblings.
- Wired both packages into root `composer.json`: added to `require` block in alphabetical order within `markommerce/*` group; added test namespaces to `autoload-dev`.
- Ran `composer dump-autoload` inside Docker to register new path-repo symlinks; all 5 scaffold tests pass and full suite remains green (2209 passed).
