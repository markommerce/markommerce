# Task 002: Move storefront source from catalog to catalog-storefront

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Relocate every HTTP/Latte/theme-coupled artefact from `packages/catalog/` to `packages/catalog-storefront/`. The move is atomic: PHP source, the layout file, Latte templates, CSS/JS resources, the npm `package.json`, and the storefront tests all move in this task, and every cross-reference (template-namespace strings, the `catalog-scope` import path for `ProductGridComponent`, `dirname(__DIR__, N)` test paths) is updated so the full test suite stays green at the end.

After this task:
- `packages/catalog/src/` contains only `Contracts/`, `Entity/`, `Enum/`, `Exceptions/`, `Repositories/`, `Services/`.
- `packages/catalog/` no longer has `layout/`, `Controller/`, `Component/`, `Context/`, `Data/`, `Iteration/`, `resources/`, or `package.json`.
- `packages/catalog-storefront/src/` contains the moved Controller, Components, Context providers, Data DTOs, and Iteration.
- `packages/catalog-storefront/layout/category_show.php` exists with updated template strings (`catalog-storefront::components/…`).
- `packages/catalog-storefront/resources/{views,css,js}/` contains the moved Latte templates, CSS, and TS.
- `packages/catalog-storefront/tests/Feature/CategoryControllerTest.php`, `tests/Feature/CategoryLayoutTest.php`, `tests/Unit/Component/ProductGridComponentTest.php` exist as moved copies with updated `use` statements and template namespace strings.
- `packages/catalog-scope/src/Component/ScopedProductGridComponent.php` imports from `Markommerce\CatalogStorefront\Component\ProductGridComponent` and `Markommerce\CatalogStorefront\Data\ProductGridData` (still lives in catalog-scope at this point — task 005 relocates it).
- `packages/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php` uses `Markommerce\CatalogStorefront\…` for imported types.

Catalog's `composer.json` is NOT modified in this task — that is task 003. Until 003 lands, catalog still requires the storefront packages it no longer uses; this is intentional so that 002 lands as a self-contained, green-tree refactor and 003 can be reverted independently if the decoupling assertions need iteration.

## Context
- Files to move (PHP, namespaces become `Markommerce\\CatalogStorefront\\…`):
  - `packages/catalog/src/Controller/CategoryController.php` → `packages/catalog-storefront/src/Controller/CategoryController.php`
  - `packages/catalog/src/Component/{ProductGridComponent,ProductCard,StockBadge}.php` → `packages/catalog-storefront/src/Component/`
  - `packages/catalog/src/Context/{CategoryDataProvider,CategoryToken}.php` → `packages/catalog-storefront/src/Context/`
  - `packages/catalog/src/Data/{ProductGridData,ProductCardData,StockBadgeData}.php` → `packages/catalog-storefront/src/Data/`
  - `packages/catalog/src/Iteration/ProductIteration.php` → `packages/catalog-storefront/src/Iteration/`
- Files to move (non-PHP):
  - `packages/catalog/layout/category_show.php` → `packages/catalog-storefront/layout/category_show.php`
  - `packages/catalog/resources/views/components/{product-card,product-grid,product-grid-item,stock-badge}.latte` → `packages/catalog-storefront/resources/views/components/`
  - `packages/catalog/resources/css/components/product-card.css` → `packages/catalog-storefront/resources/css/components/`
  - `packages/catalog/resources/js/{index.ts,package.test.ts}` → `packages/catalog-storefront/resources/js/`
  - `packages/catalog/package.json` → `packages/catalog-storefront/package.json` (rename `@markommerce/catalog` → `@markommerce/catalog-storefront`, update dependency references that still point at the old name)
- Tests to move:
  - `packages/catalog/tests/Feature/CategoryControllerTest.php` → `packages/catalog-storefront/tests/Feature/`
  - `packages/catalog/tests/Feature/CategoryLayoutTest.php` → `packages/catalog-storefront/tests/Feature/`
  - `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php` → `packages/catalog-storefront/tests/Unit/Component/`
- Template namespace string updates (find/replace `catalog::components/` → `catalog-storefront::components/`):
  - `packages/catalog-storefront/layout/category_show.php` (after move)
  - `packages/catalog-storefront/tests/Feature/CategoryControllerTest.php` line 399 (`expect(...)->toContain('catalog::components/product-grid')`)
  - `packages/catalog-storefront/tests/Feature/CategoryLayoutTest.php` line 341 (same)
  - `packages/catalog-storefront/tests/Unit/Component/ProductGridComponentTest.php` lines 278, 310, 347, 373, 398 (`$engine->renderToString('catalog::components/…', …)`)
- `dirname(__DIR__, N)` path updates: every moved test computes the catalog module path with `dirname(__DIR__, 2)`. After the move, the test sits at `packages/catalog-storefront/tests/Feature/` or `packages/catalog-storefront/tests/Unit/Component/`, so the depth recalculations are:
  - `Feature/*Test.php`: `dirname(__DIR__, 2)` → resolves to `packages/catalog-storefront/` (correct after move)
  - `Unit/Component/ProductGridComponentTest.php`: `dirname(__DIR__, 3)` → resolves to `packages/catalog-storefront/` (correct after move)
- `ModuleManifest` instantiation inside the tests currently passes `name: 'markommerce/catalog'`. After the move, it must be `name: 'markommerce/catalog-storefront'` so `LayoutDiscovery` / `ModuleTemplateResolver` resolve against the correct module.
- Storefront tests reuse `Markommerce\Catalog\Tests\Support\FakeCategoryRepository` etc. via the root composer's `autoload-dev.psr-4` — no new autoload entries needed.
- catalog-scope updates:
  - `packages/catalog-scope/src/Component/ScopedProductGridComponent.php` — change `use Markommerce\Catalog\Component\ProductGridComponent;` → `use Markommerce\CatalogStorefront\Component\ProductGridComponent;` and `use Markommerce\Catalog\Data\ProductGridData;` → `use Markommerce\CatalogStorefront\Data\ProductGridData;`. The `extends ProductGridComponent` line stays unchanged (relative resolution via `use`). The `#[Preference(replaces: ProductGridComponent::class)]` attribute automatically resolves to the new FQN because `ProductGridComponent::class` is evaluated against the updated `use` import — no separate change is required to the attribute argument.
  - `packages/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php` — update the same imports plus the `ModuleManifest` path / discovery test. Note: `use Markommerce\Catalog\Component\ProductGridComponent;` on line 10 becomes `use Markommerce\CatalogStorefront\Component\ProductGridComponent;` — this single change causes `ProductGridComponent::class` (used at lines 132, 285, and 322) to resolve to the new FQN, making the existing assertions track the move automatically.
  - `packages/catalog-scope/composer.json` — add `markommerce/catalog-storefront` to `require` (catalog-scope's `ScopedProductGridComponent` extends a class from catalog-storefront; the require makes it explicit). This is *temporary*: task 005 moves `ScopedProductGridComponent` out of catalog-scope entirely, at which point the require is removed.
- catalog test cleanup updates (CRITICAL — `packages/catalog/tests/Unit/ScopeDecouplingTest.php` references the moved test files at lines 96–116 with `expect($contents)->toContain('…')` assertions on test descriptions inside `CategoryControllerTest.php` and `CategoryLayoutTest.php`. After this task moves those files, those assertions fail because the files no longer exist in `packages/catalog/tests/Feature/`):
  - Remove the `it('preserves all non-scope test cases in CategoryControllerTest …')` block (lines 96–106).
  - Remove the `it('preserves all non-scope test cases in CategoryLayoutTest …')` block (lines 108–116).
  - Keep the `CategoryTreeIntegrationTest`, `CatalogSeederTreeTest`, and `CatalogSeederTest` preservation blocks (those files stay in catalog).
- `packages/catalog/resources/js/package.test.ts` moves with `resources/js/` to `packages/catalog-storefront/resources/js/package.test.ts`. The test contains a `describe('catalog package frontend wiring', …)` block whose package.json assertions read against `pkgRoot/package.json` — `pkgRoot` is resolved as `path.resolve(__dirname, '../../')` and naturally points at the new package root after the move, so the assertions resolve against the renamed `@markommerce/catalog-storefront` package.json. Update the `describe` label to `'catalog-storefront package frontend wiring'` and (if any inner test description embeds the literal string `'catalog package'`) update accordingly to keep the npm test suite readable.
- Reference for the move pattern: P2 task 007 (`007-create-catalog-scope-package.md`) and task 003 (`003-strip-scope-from-entities.md`) — they relocated companion entities and stripped imports in coordinated steps.

## Requirements (Test Descriptions)
- [x] `it relocates Controller, Component, Context, Data, and Iteration source files from packages/catalog/src to packages/catalog-storefront/src with namespace Markommerce\\CatalogStorefront\\…`
- [x] `it relocates the layout file from packages/catalog/layout/category_show.php to packages/catalog-storefront/layout/category_show.php`
- [x] `it relocates Latte templates, CSS, JS, and package.json from packages/catalog/resources and packages/catalog to packages/catalog-storefront with the npm package renamed to @markommerce/catalog-storefront`
- [x] `it rewrites every catalog::components/… template namespace reference to catalog-storefront::components/… in the moved layout file and moved tests`
- [x] `it updates the catalog-scope ScopedProductGridComponent.php to import ProductGridComponent and ProductGridData from Markommerce\\CatalogStorefront`
- [x] `it updates the catalog-scope ScopedProductGridComponentTest.php to reflect the new namespaces and ModuleManifest paths`
- [x] `it temporarily adds markommerce/catalog-storefront to packages/catalog-scope/composer.json require (removed again in task 005)`
- [x] `it relocates CategoryControllerTest, CategoryLayoutTest, and ProductGridComponentTest into packages/catalog-storefront/tests with corrected dirname depths and ModuleManifest names`
- [x] `it leaves packages/catalog/src with only Contracts, Entity, Enum, Exceptions, Repositories, and Services directories`
- [x] `it asserts no Markommerce\\Catalog\\Controller, Markommerce\\Catalog\\Component, Markommerce\\Catalog\\Context, Markommerce\\Catalog\\Data, or Markommerce\\Catalog\\Iteration class exists after the move (grep the entire packages/ tree)`
- [x] `it updates packages/catalog/tests/Unit/ScopeDecouplingTest.php to remove the two preservation blocks that target the now-moved CategoryControllerTest.php and CategoryLayoutTest.php (lines 96–116 of the existing file) so the catalog test suite stays green after the move`
- [x] `it updates packages/catalog/resources/js/package.test.ts (after relocation to packages/catalog-storefront/resources/js/) so the describe label and any embedded literals reference the new @markommerce/catalog-storefront npm package name`
- [x] `the full monorepo test suite (composer test) passes after this task completes`

## Acceptance Criteria
- All requirements have passing tests.
- Catalog test suite, catalog-storefront test suite, and catalog-scope test suite all stay green at the end of the task.
- No file references `Markommerce\Catalog\Controller\…`, `Markommerce\Catalog\Component\…`, `Markommerce\Catalog\Context\…`, `Markommerce\Catalog\Data\…`, or `Markommerce\Catalog\Iteration\…` anywhere in `packages/`.
- The Tier 2 end-to-end test in catalog-scope still passes (verifies the scoped grid still works through its updated imports).
- Code follows project standards.

## Implementation Notes
- This is the heaviest task in the plan. If the requirement count balloons past the TDD-cycle budget, the implementer may split into 002a (PHP source + tests) and 002b (templates + resources + template-namespace string rewrites). Each split half must end with a green test suite.
- Catalog's `composer.json` is unchanged in this task (still requires the storefront packages it no longer uses); task 003 drops them.
- The npm `package.json` rename is a metadata change; if the project's TS test suite covers it, run that suite too.
