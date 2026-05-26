# Plan: Extract catalog-storefront from catalog (Phase 3)

## Created
2026-05-26

## Status
completed

## Objective
Move every HTTP/Latte/theme-coupled piece out of `markommerce/catalog` into a new `markommerce/catalog-storefront` package, and relocate `ScopedProductGridComponent` into a fresh `markommerce/catalog-storefront-scope` bridge. After this plan, `catalog` is a headless domain package (entities, repositories, services, exceptions, seeder) with zero dependency on `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, or the Marko routing/view stack. Tier 1 corner shops install `catalog` + `catalog-storefront` + theme + config and get a working public shop without a single line of scope/locale machinery. Tier 2 storefront merchants additionally install `catalog-storefront-scope` for locale-aware product rendering.

## Related Issues
none

## Discovery Notes

### Phase 2 foundation already in place
- `catalog` is already scope-free as of P2 (commit `8caa317`). `Product` and `Category` are plain entities; locale behaviour lives in `catalog-scope` + `catalog-locale`.
- `catalog/src/` still contains: `Controller/CategoryController.php`, `Component/{ProductGridComponent,ProductCard,StockBadge}.php`, `Context/{CategoryDataProvider,CategoryToken}.php`, `Data/{ProductGridData,ProductCardData,StockBadgeData}.php`, `Iteration/ProductIteration.php` — all of these import from `Markommerce\Layout\…`, `Marko\Routing\…`, or wrap layout extension/iteration primitives. They are storefront concerns.
- `catalog/layout/category_show.php` is the only layout file in catalog. It references `OneColumnLayout` from `theme-blank` and components from catalog.
- `catalog/resources/views/components/*.latte`, `catalog/resources/css/components/product-card.css`, `catalog/resources/js/index.ts`, and `catalog/package.json` are storefront assets (the npm package `@markommerce/catalog` exports CSS and TS for storefront use).
- `catalog/composer.json` requires `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, `marko/routing`, `marko/view`, `marko/view-latte` — all of which are storefront-only. `marko/config` appears unused inside `packages/catalog/src/`; task 003 verifies and drops it if so.
- `catalog/tests/Feature/{CategoryControllerTest,CategoryLayoutTest}.php` and `catalog/tests/Unit/Component/ProductGridComponentTest.php` are storefront tests.
- `catalog-scope/src/Component/ScopedProductGridComponent.php` extends `Markommerce\Catalog\Component\ProductGridComponent` — that class moves to catalog-storefront in this phase, which breaks the import path. Cannot stay in catalog-scope without making catalog-scope require catalog-storefront (drags layout/frontend/theme-blank into every Tier 2 headless install). Cannot move into catalog-storefront without making catalog-storefront require catalog-scope (drags scope into Tier 1). The resolution is a sibling bridge package.

### Decisions locked in during clarification
- **Scoped grid component placement: new `markommerce/catalog-storefront-scope` bridge.** This package requires both `catalog-storefront` and `catalog-scope`. Tier 1 = `catalog` + `catalog-storefront`. Tier 2 headless = + `catalog-scope` + `locale` + `catalog-locale`. Tier 2 storefront = + `catalog-storefront-scope`. Picked over the two non-viable alternatives (moving into catalog-storefront drags scope into Tier 1; keeping in catalog-scope drags storefront into headless Tier 2).
- **Tier 1 integration test depth: full container boot.** Mirror `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`: boot real module manifests for `catalog` + `catalog-storefront` + `config` + `config-pgsql` + `layout` + `frontend` + `theme-blank`, register routes, hit `/catalog/category/{id}` against an in-memory fake repository stack, assert a real product renders without any scope/locale class being touched.
- **Atomic source move.** The PHP source files, the layout file, the Latte templates, the CSS/JS resources, the template-namespace string references (`catalog::` → `catalog-storefront::`), and the catalog-scope import path for `ProductGridComponent` are interlocked. They move in a single task. The implementer may split during execution if the move balloons, but the plan budgets one task.
- **Catalog seeder stays in catalog.** `CatalogSeeder` seeds Products/Categories/CategoryTrees — pure domain data, no storefront coupling.
- **Test support fakes stay in catalog.** `tests/Support/Fake*Repository.php` implement catalog interfaces and live under `Markommerce\Catalog\Tests\Support\`. Storefront tests import them via the root autoload, same pattern catalog-scope already uses for `ScopedProductGridComponentTest`.
- **`StorefrontDecouplingTest` mirrors `ScopeDecouplingTest`.** Asserts catalog/src has no `Markommerce\Layout\`, `Markommerce\Frontend\`, `Markommerce\ThemeBlank\`, `Marko\Routing\`, `Marko\View\` imports, and catalog/composer.json drops the storefront require entries.
- **Template namespace renames everywhere.** `catalog::components/product-grid` → `catalog-storefront::components/product-grid`. The Latte `ModuleTemplateResolver` resolves namespace by module name, so once templates move under `packages/catalog-storefront/resources/views/`, every reference (layout file, tests, doc snippets) must update.
- **Catalog's `module.php` shape unchanged.** It only registers repository bindings — all domain. Catalog-storefront gets its own `module.php` only if needed (current source has no preferences or non-discovery bindings to register; the controller is auto-discovered by `RouteDiscovery`).

### Files / mechanisms touched
- New: `packages/catalog-storefront/composer.json` (requires `marko/core`, `marko/database` (verified needed — `ProductGridComponent.php` imports `Marko\Database\Exceptions\RepositoryException` directly), `marko/routing`, `marko/view`, `marko/view-latte`, `marko/config` (verified needed — `ProductGridComponentTest` constructs a `ViewConfig` from `ConfigRepository`), `markommerce/catalog`, `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`)
- New: `packages/catalog-storefront/{LICENSE,.gitattributes,README.md,package.json,module.php (only if needed),src/.gitkeep,tests/Pest.php}`
- Moved into `packages/catalog-storefront/src/`: `Controller/CategoryController.php`, `Component/{ProductGridComponent,ProductCard,StockBadge}.php`, `Context/{CategoryDataProvider,CategoryToken}.php`, `Data/{ProductGridData,ProductCardData,StockBadgeData}.php`, `Iteration/ProductIteration.php` (namespaces become `Markommerce\CatalogStorefront\…`).
- Moved into `packages/catalog-storefront/`: `layout/category_show.php` (template strings updated), `resources/views/components/*.latte`, `resources/css/components/product-card.css`, `resources/js/index.ts`, `resources/js/package.test.ts`.
- Moved into `packages/catalog-storefront/tests/`: `Feature/CategoryControllerTest.php`, `Feature/CategoryLayoutTest.php`, `Unit/Component/ProductGridComponentTest.php` (with updated imports and template namespace strings).
- New: `packages/catalog-storefront-scope/{composer.json,LICENSE,.gitattributes,README.md,src/.gitkeep,tests/Pest.php}`.
- Moved into `packages/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php` (namespace `Markommerce\CatalogStorefrontScope\Component\`, imports `Markommerce\CatalogStorefront\Component\ProductGridComponent`, `Markommerce\CatalogStorefront\Data\ProductGridData`).
- Moved into `packages/catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php`.
- Deleted from `packages/catalog/`: all moved files, plus `package.json` (npm package becomes `@markommerce/catalog-storefront`).
- Modified: `packages/catalog/composer.json` (drop `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, `marko/routing`, `marko/view`, `marko/view-latte`; verify and drop `marko/config` if unused).
- Modified: `packages/catalog/README.md` (strip storefront sections, cross-link to catalog-storefront).
- Modified: `packages/catalog-scope/composer.json` (no change — was never coupled to storefront; the moved `ScopedProductGridComponent` removes the only file that imported catalog's Component namespace).
- Modified: `packages/catalog-scope/README.md` (remove ScopedProductGridComponent mention; link to catalog-storefront-scope).
- Modified: `packages/frontend-demo/composer.json` (add `markommerce/catalog-storefront` and `markommerce/catalog-storefront-scope`).
- Modified: root `composer.json` (add `markommerce/catalog-storefront`, `markommerce/catalog-storefront-scope` to `require`; add `Markommerce\CatalogStorefront\Tests\\` and `Markommerce\CatalogStorefrontScope\Tests\\` to `autoload-dev.psr-4`).
- New: `packages/catalog/tests/Unit/StorefrontDecouplingTest.php`.
- New: `packages/catalog-storefront/tests/Feature/Tier1EndToEndTest.php`.
- New: `docs/src/content/docs/packages/catalog-storefront.md`.
- New: `docs/src/content/docs/packages/catalog-storefront-scope.md`.
- Modified: `docs/src/content/docs/packages/catalog.md` (drop storefront sections).
- Modified: `docs/src/content/docs/packages/catalog-scope.md` (drop ScopedProductGridComponent section).
- New: `tests/Unit/Docs/CatalogStorefrontExtractPagesTest.php`.
- Modified: `FEATURES.md` (P3 status flips to `in_progress` at start, `completed` at end; tier tables updated for catalog-storefront-scope).

## Scope

### In Scope
- Create `markommerce/catalog-storefront` and move every HTTP/Latte/theme-coupled artefact out of `catalog` into it.
- Create `markommerce/catalog-storefront-scope` and move `ScopedProductGridComponent` (plus its test) out of `catalog-scope` into it.
- Drop `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, `marko/routing`, `marko/view`, `marko/view-latte` from `packages/catalog/composer.json`. Verify and drop `marko/config` if unused.
- Update `frontend-demo/composer.json` to require the two new packages so the live demo still serves a Tier 2 storefront.
- Add a `StorefrontDecouplingTest` proving catalog has no storefront-namespace imports.
- Add a full container-boot Tier 1 integration test in catalog-storefront.
- Update READMEs: catalog (strip storefront), catalog-scope (strip ScopedProductGridComponent section), and write new READMEs for catalog-storefront and catalog-storefront-scope.
- Update docs site pages: new pages for the two new packages, edits to catalog.md and catalog-scope.md.
- Update FEATURES.md status table and tier rollup tables.

### Out of Scope
- Anything under `market`, `catalog-market`, `catalog-market-category-trees` — Phase 4.
- Anything under `config` decoupling, `config-scope`, `config-locale`, `config-market` — Phase 5.
- Renaming `theme-blank` (still an open question in FEATURES.md).
- Adding a meta-package per tier (`starter-shop`, etc.) — open question deferred.
- Introducing merchant-overridable bridge mappings — deferred per FEATURES.md.
- Channel axis — out of scope per FEATURES.md.
- Splitting the existing tests into smaller files. Move them as-is; functional content unchanged.
- Splitting `CategoryController`, `CategoryDataProvider`, or any moved class into smaller pieces. P3 is a relocation, not a redesign.

## Success Criteria
- [ ] `grep -rn "Markommerce\\\\Layout\\\\\\|Markommerce\\\\Frontend\\\\\\|Markommerce\\\\ThemeBlank\\\\" packages/catalog/src` returns zero matches.
- [ ] `grep -rn "Marko\\\\Routing\\\\\\|Marko\\\\View\\\\" packages/catalog/src` returns zero matches.
- [ ] `packages/catalog/composer.json` does not require `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, `marko/routing`, `marko/view`, or `marko/view-latte`.
- [ ] `markommerce/catalog` can be installed and tested as a headless package — its test suite passes without any of the dropped storefront dependencies being installed.
- [ ] New package `markommerce/catalog-storefront` exists, hosts every moved file, and its full test suite (relocated catalog tests + new Tier 1 integration test) passes.
- [ ] New package `markommerce/catalog-storefront-scope` exists, owns `ScopedProductGridComponent`, requires `catalog-storefront` + `catalog-scope`, and its tests pass.
- [ ] `packages/catalog-scope/src/Component/ScopedProductGridComponent.php` no longer exists (moved). `packages/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php` no longer exists in catalog-scope (moved).
- [ ] A Tier 1 integration test boots catalog + catalog-storefront + config + config-pgsql + layout + frontend + theme-blank and renders `/catalog/category/{id}` against a real product without touching any scope or locale class.
- [ ] `frontend-demo` test suite passes with the two new requires added.
- [ ] `composer test:all` from the monorepo root passes.
- [ ] PHPStan level 8 + PHP-CS-Fixer clean for all touched packages.
- [ ] Docs site builds with the new package pages and updated cross-links.
- [ ] FEATURES.md P3 row reads `completed` and the tier tables include `catalog-storefront-scope`.
- [ ] All requirements from each task file have passing tests.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `markommerce/catalog-storefront` package (composer.json, LICENSE, .gitattributes, tests/Pest.php, src/.gitkeep, root composer.json wiring) | — | completed |
| 002 | Move storefront PHP + layout + templates + resources from `catalog` to `catalog-storefront`; relocate the three storefront tests; rewrite template namespaces; update `catalog-scope/src/Component/ScopedProductGridComponent.php` to import from the new namespace; trim `packages/catalog/tests/Unit/ScopeDecouplingTest.php` preservation blocks that target the moved files; update `resources/js/package.test.ts` describe label to the new package | 001 | completed |
| 003 | Drop storefront dependencies from `packages/catalog/composer.json`; add `StorefrontDecouplingTest`; extend `ComposerManifestTest` with storefront-namespace absence assertions; verify catalog suite runs without the dropped packages | 002 | completed |
| 004 | Scaffold `markommerce/catalog-storefront-scope` package | 001 | completed |
| 005 | Move `ScopedProductGridComponent` + its test from `catalog-scope` to `catalog-storefront-scope`; update catalog-scope decoupling assertions | 002, 004 | completed |
| 006 | Update `frontend-demo/composer.json` to require `catalog-storefront` and `catalog-storefront-scope`; verify demo suite stays green | 003, 005 | completed |
| 007 | Tier 1 end-to-end integration test (full container boot, no scope, no locale) in `catalog-storefront/tests/Feature/Tier1EndToEndTest.php` | 002, 003 | completed |
| 008 | READMEs: write `catalog-storefront/README.md` and `catalog-storefront-scope/README.md`; trim `catalog/README.md` and update `packages/catalog/tests/Unit/ReadmeTest.php` to drop the storefront-route assertion; trim `catalog-scope/README.md` | 003, 005, 007 | completed |
| 009 | Docs site: new `catalog-storefront.md` and `catalog-storefront-scope.md` pages; edits to `catalog.md` and `catalog-scope.md`; update `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php` to drop the ScopedProductGridComponent literal assertion on `catalog-scope.md`; new `CatalogStorefrontExtractPagesTest` | 008 | completed |
| 010 | Update `FEATURES.md` — P3 status to `completed`, also flip stale P1/P2 to `completed`; refresh tier tables / package count to include `catalog-storefront-scope` and the Tier 2 headless/storefront distinction | 003, 005, 006, 007, 008, 009 | completed |

## Architecture Notes

### Module structure after P3
```
packages/
  catalog/                         # Headless domain
    src/{Contracts,Entity,Repositories,Services,Exceptions,Enum}/
    Seed/CatalogSeeder.php
    composer.json                  # marko/core, marko/database — that's it (plus maybe marko/config if confirmed used)
    module.php                     # repository bindings only
    tests/{Unit,Feature}/          # entity + repository + service + seeder tests; no controllers, no Latte
  catalog-storefront/              # NEW — public storefront for catalog
    src/{Controller,Component,Context,Data,Iteration}/
    layout/category_show.php
    resources/{views,css,js}/
    composer.json                  # marko/routing, marko/view, marko/view-latte, markommerce/layout, markommerce/frontend, markommerce/theme-blank, markommerce/catalog
    module.php                     # only if a binding/preference becomes necessary
    package.json                   # @markommerce/catalog-storefront npm package
    tests/{Unit,Feature}/          # CategoryControllerTest, CategoryLayoutTest, ProductGridComponentTest, Tier1EndToEndTest
  catalog-scope/                   # Unchanged from P2 minus ScopedProductGridComponent
    src/Entity/{ProductScopedOverrides,CategoryScopedOverrides}.php
    Seed/CatalogLocaleSeeder.php
    composer.json                  # markommerce/catalog, markommerce/scope
  catalog-storefront-scope/        # NEW — locale-aware grid rendering
    src/Component/ScopedProductGridComponent.php
    composer.json                  # markommerce/catalog-storefront, markommerce/catalog-scope
    tests/Unit/Component/ScopedProductGridComponentTest.php
```

### Template namespace shift
Latte templates are addressed via `{moduleName}::{path}`. The `ModuleTemplateResolver` matches the short name against the module's full name (`markommerce/catalog-storefront` matches both `catalog-storefront` and `markommerce/catalog-storefront`). Once templates move under `packages/catalog-storefront/resources/views/components/`, every consumer must update its template strings:

```diff
- template: 'catalog::components/product-grid',
+ template: 'catalog-storefront::components/product-grid',
```

This happens in `layout/category_show.php`, in the moved test files (which previously did `$engine->renderToString('catalog::components/product-grid', …)`), and in the body assertions inside `CategoryControllerTest` / `CategoryLayoutTest` (`expect($response->body())->toContain('catalog::components/product-grid')`).

### Layout discovery
`LayoutDiscovery` (in `packages/layout`) scans `{module}/layout/*.php` files. When the layout file moves from `packages/catalog/layout/category_show.php` to `packages/catalog-storefront/layout/category_show.php`, discovery picks it up automatically once `catalog-storefront` is registered as a module (extra.marko.module=true). No `module.php` boot is required.

### Tier 1 test pattern
The Tier 1 integration test follows the `Tier2EndToEndTest` skeleton but with these differences:
- Module manifests booted: `marko/config`, `marko/core`, `marko/database`, `marko/routing`, `marko/view`, `marko/view-latte`, `markommerce/catalog`, `markommerce/catalog-storefront`, `markommerce/config`, `markommerce/config-pgsql`, `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`. **Notably absent**: `markommerce/scope`, `markommerce/scope-pgsql`, `markommerce/locale`, `markommerce/catalog-scope`, `markommerce/catalog-locale`, `markommerce/catalog-storefront-scope`.
- Repositories use the existing `Markommerce\Catalog\Tests\Support\Fake*Repository` fakes so the test runs in the in-process suite (no Postgres). Seed a `Category` and one `Product` with an assignment, hit `/catalog/category/{id}` through the Router + LayoutMiddleware, assert the response body contains both the category name and the product name.
- Add an explicit negative assertion: `expect(class_exists(\Markommerce\Scope\Resolver\ScopeResolver::class, autoload: false))->toBeFalse()` is too strong (the class is still in the monorepo vendor) — instead assert that the container never resolves `ScopeResolver` during the request, e.g. by registering a binding-watcher container or by asserting against the resolved class graph. Pragmatic alternative: assert the rendered HTML matches the raw product name with no resolver wrapping. Task 007 picks the form.

### Scoped grid component move
The move from `catalog-scope` to `catalog-storefront-scope` is a pure relocation:
```diff
- namespace Markommerce\CatalogScope\Component;
- use Markommerce\Catalog\Component\ProductGridComponent;
- use Markommerce\Catalog\Data\ProductGridData;
+ namespace Markommerce\CatalogStorefrontScope\Component;
+ use Markommerce\CatalogStorefront\Component\ProductGridComponent;
+ use Markommerce\CatalogStorefront\Data\ProductGridData;
```
The `#[Preference(replaces: ProductGridComponent::class)]` attribute targets the new `Markommerce\CatalogStorefront\Component\ProductGridComponent`. `PreferenceDiscovery::discoverInModule()` scans the catalog-storefront-scope module path; once the file lives there, the discovery records and PreferenceRegistry tests assert against the new module manifest path.

### Headless safety net
`StorefrontDecouplingTest` is the production guard rail. It catches accidental re-coupling in future PRs — anyone who re-imports `Markommerce\Layout\…` into catalog gets a red test. The test mirrors `ScopeDecouplingTest`'s shape: walk `packages/catalog/src` and assert no `Markommerce\Layout\`, `Markommerce\Frontend\`, `Markommerce\ThemeBlank\`, `Marko\Routing\`, `Marko\View\` imports.

## Risks & Mitigations

- **Risk:** Task 002 is the largest task — it atomically moves PHP source, layout, templates, CSS/JS, and the catalog-scope import path. A partial move (templates without the layout, or PHP source without templates) breaks the tree.
  - **Mitigation:** The task is written as a single TDD cycle with one merged set of requirements covering every moved file kind. The implementer keeps the working copy in a broken state only briefly, then verifies the full storefront suite + catalog-scope suite green before committing. If the implementer hits >7 requirement explosion they may split 002 into 002a (resources + template strings) and 002b (PHP + tests + catalog-scope import) — but each must end with a green tree.
- **Risk:** Latte templates are looked up by short module name (`catalog-storefront` matches `markommerce/catalog-storefront`). If the short name collides with another module (e.g., a future merchant module also named `catalog-storefront`), template resolution becomes ambiguous.
  - **Mitigation:** Same shape that already exists for `catalog::` today; not a new risk introduced by P3. Documented in `catalog-storefront.md`.
- **Risk:** Catalog's seeder (`CatalogSeeder`) is autoloaded under `Markommerce\Catalog\Seed\`. Dropping the storefront require entries from `catalog/composer.json` does not affect it (no storefront imports), but the test infrastructure that runs seeders (`tests/Feature/CatalogSeederTreeTest.php`) must not transitively need any dropped class.
  - **Mitigation:** Task 003 runs the catalog suite in isolation (pass at every step). If a test indirectly needs a dropped class, the fix is to relocate it to catalog-storefront/tests (the import scope is wrong for catalog).
- **Risk:** The Tier 1 integration test is meant to prove the scope-free path. If `ScopeResolver` is still constructible inside the test process (it's autoloaded in the monorepo), the test could silently pass even if catalog-storefront's runtime wiring leaks a scope dependency.
  - **Mitigation:** Task 007's design avoids checking class loading. It either (a) inspects the container's registered bindings and asserts no scope-namespaced binding is present, or (b) asserts the rendered template equals the raw product name without resolver substitution. Implementer picks; both are valid.
- **Risk:** `package.json` (npm) lives next to `composer.json` (PHP) and currently declares `@markommerce/catalog` exporting CSS and JS for storefront use. Moving npm package metadata is a cross-toolchain change.
  - **Mitigation:** Move `packages/catalog/package.json` to `packages/catalog-storefront/package.json`, rename to `@markommerce/catalog-storefront`, and keep its `markommerce.extension` priority. Verify with `cd packages/catalog-storefront && npm run …` if the repo's npm tests cover the path; otherwise let CI run.
- **Risk:** `frontend-demo/composer.json` already requires `markommerce/catalog`, `markommerce/catalog-locale`, `markommerce/catalog-scope`, `markommerce/locale`. After P3 it must also require `markommerce/catalog-storefront` and `markommerce/catalog-storefront-scope`, otherwise the demo's category page 404s at runtime.
  - **Mitigation:** Task 006 adds both new requires and re-runs the frontend-demo test suite. The existing `frontend-demo/tests/Unit/ComposerRequiresTest.php` is extended with assertions for the two new requires.
- **Risk:** Catalog-scope's `composer.json` does not reference catalog-storefront today, so dropping `ScopedProductGridComponent` from catalog-scope doesn't require a composer change there. But if a downstream test in catalog-scope still references `ScopedProductGridComponent` (e.g., in a discovery test asserting the file exists), it breaks.
  - **Mitigation:** Task 005 sweeps catalog-scope for any remaining reference and either updates it or moves it to catalog-storefront-scope. A `grep -rn "ScopedProductGridComponent" packages/catalog-scope` after the move must return zero.
- **Risk:** Test support fakes (`Markommerce\Catalog\Tests\Support\Fake*Repository`) are autoloaded only under catalog's `autoload-dev` namespace. Storefront tests using them rely on the *root* composer's `autoload-dev.psr-4` registration to map `Markommerce\Catalog\Tests\\` to `packages/catalog/tests/`. The existing root composer already does this.
  - **Mitigation:** Verified at plan time. Task 002 confirms that storefront tests using `use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;` resolve correctly. No new autoload entry needed for the fakes themselves.
- **Risk:** Cross-package tests that boot a real module manifest hard-code paths via `dirname(__DIR__, N)`. Moving a test file changes the directory depth — a `dirname(__DIR__, 2)` that pointed at `packages/catalog/` becomes wrong when the test now lives in `packages/catalog-storefront/tests/Feature/`.
  - **Mitigation:** Every relocated test file is reviewed in task 002 for `dirname(__DIR__, N)` and `dirname(__DIR__, N) . '/layout/category_show.php'` etc. The depth recalculations are mechanical but easy to get wrong; both the storefront tests are exercised after the move to catch any path mistakes.
- **Risk:** `CategoryController::class` is referenced by FQN inside `layout/category_show.php` and inside `LayoutDiscovery` cache keys (`CategoryController::class . '::show'`). When the controller moves from `Markommerce\Catalog\Controller\CategoryController` to `Markommerce\CatalogStorefront\Controller\CategoryController`, every consumer (layout file, tests, route registration) must update.
  - **Mitigation:** Task 002 enumerates every reference site. A grep for `Markommerce\Catalog\Controller\` and `Markommerce\Catalog\Component\` after the move must return zero in `packages/`.
- **Risk:** Marketing claim that "Tier 1 = 7 packages" in FEATURES.md is currently `catalog + catalog-storefront + config + config-pgsql + layout + frontend + theme-blank`. The `catalog-storefront` package fills the slot it was already promised — no count change needed for Tier 1. Tier 3 in FEATURES.md does not yet enumerate `catalog-storefront-scope`; task 010 adds it under "Auto-wiring bridges" or as a separate "Storefront + scope" line.
  - **Mitigation:** Task 010 updates the table prose and the tier rollup. The new package count for Tier 2 storefront = Tier 2 headless + `catalog-storefront-scope` = 15 packages; document explicitly.
