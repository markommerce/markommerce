# Task 001: Scaffold `markommerce/catalog-storefront` package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the empty `markommerce/catalog-storefront` package skeleton. This task lays down the package directory, composer manifest, license, gitattributes, an empty `src/.gitkeep`, a `tests/Pest.php`, and wires the package into the monorepo (`composer.json` `require` + `autoload-dev.psr-4`). No source code moves yet — this scaffold simply enables tasks 002 onward to land code in a discoverable place.

## Context
- Reference scaffolds:
  - `packages/catalog-scope/` and `packages/catalog-locale/` from P2 — same shape, same conventions
  - `packages/catalog-scope/composer.json` for the marko-module declaration
- Composer `require` for the new package: `php ^8.5`, `marko/core`, `marko/database`, `marko/routing`, `marko/view`, `marko/view-latte`, `marko/config`, `markommerce/catalog`, `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`.
  - `marko/database` is required because `ProductGridComponent.php` (which moves to catalog-storefront in task 002) imports `Marko\Database\Exceptions\RepositoryException` directly in its `@throws` chain. Verified via grep — `packages/catalog/src/Component/ProductGridComponent.php:7: use Marko\Database\Exceptions\RepositoryException;`. The package depends on the database exception type even though it only consumes catalog Repository *interfaces*, not the database layer itself.
  - `marko/config` is required because `ProductGridComponentTest` (which moves to catalog-storefront in task 002) imports `Marko\Config\ConfigRepository` to construct a `ViewConfig` for Latte rendering. Verified via grep — `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php:24: use Marko\Config\ConfigRepository;`. Latte `ViewConfig` is constructor-injected with `ConfigRepositoryInterface`.
- Composer `require-dev`: `marko/testing`, `pestphp/pest ^4.0`.
- Autoload PSR-4: `Markommerce\\CatalogStorefront\\` → `src/`. Autoload-dev: `Markommerce\\CatalogStorefront\\Tests\\` → `tests/`.
- `extra.marko.module = true` to register the module with Marko's discovery.
- Root `composer.json` (`/home/michal/www/marko/markommerce/composer.json`) — add `markommerce/catalog-storefront` to `require` (alphabetical within `markommerce/*` group) and `Markommerce\\CatalogStorefront\\Tests\\` to `autoload-dev.psr-4`.
- Do NOT create `module.php` yet. None of the moved code requires custom bindings or preferences — controllers are auto-discovered by `RouteDiscovery`, components by `LayoutDiscovery`. Task 002 verifies that assumption and only adds `module.php` if a real need surfaces.

## Requirements (Test Descriptions)
- [ ] `it creates packages/catalog-storefront/composer.json declaring markommerce/catalog-storefront as a marko-module with the correct require list`
- [ ] `it declares Markommerce\\CatalogStorefront\\ PSR-4 autoload mapping to src/`
- [ ] `it declares Markommerce\\CatalogStorefront\\Tests\\ PSR-4 autoload-dev mapping to tests/`
- [ ] `it includes markommerce/catalog, markommerce/layout, markommerce/frontend, markommerce/theme-blank, marko/routing, marko/view, marko/view-latte in the require block`
- [ ] `it creates packages/catalog-storefront/LICENSE, .gitattributes, src/.gitkeep, and tests/Pest.php matching the project scaffolding conventions`
- [ ] `it adds markommerce/catalog-storefront to the root composer.json require block alphabetically within the markommerce/* group`
- [ ] `it adds Markommerce\\CatalogStorefront\\Tests\\ to the root composer.json autoload-dev.psr-4 block`
- [ ] `it passes composer validate on packages/catalog-storefront/composer.json`

## Acceptance Criteria
- All requirements have passing tests (`packages/catalog-storefront/tests/PackageScaffoldingTest.php`).
- `composer dump-autoload` at monorepo root succeeds with the new autoload entries.
- The package exists with no src/ classes (just `.gitkeep`).
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
