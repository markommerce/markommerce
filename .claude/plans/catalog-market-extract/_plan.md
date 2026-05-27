# Plan: Extract market features from catalog (Phase 4)

## Created
2026-05-27

## Status
completed

## Objective
Create `markommerce/market` (axis declaration), `markommerce/catalog-market` (no-op field bridge placeholder), and `markommerce/catalog-market-category-trees` (multi-tree market-assignment + resolver). Move every market-coupled artefact out of `markommerce/catalog` into `catalog-market-category-trees`. After this plan, `catalog` keeps its single-default-tree and multi-tree CRUD but has zero knowledge of the `market` concept. Tier 3 merchants install `market` + `catalog-market` + `catalog-market-category-trees` on top of Tier 2 to get per-market category-tree resolution; Tier 1 and Tier 2 merchants pay nothing for market machinery.

## Related Issues
none

## Discovery Notes

### Pre-P4 state
- `catalog` is headless after P3 (no storefront, no scope). It still owns the full multi-tree `CategoryTreeService` including market-coupled methods (`assignTreeToMarket`, `unassignMarket`, `resolveTreeForMarket`), the `CategoryTreeMarketAssignment` entity, its repository + interface, and the `TreeHasMarketAssignmentsException` thrown from `deleteTree`.
- `catalog/Seed/CatalogSeeder.php` only uses `ensureDefaultTreeExists()` and `placeCategory()` — no market calls. Seeder stays in catalog unchanged.
- `catalog-storefront`, `catalog-storefront-scope`, and `frontend-demo` do NOT call any market-suffixed method (verified by grep). Demo packages do not need new requires for P4.
- `Product` entity today has only `id, sku, name, description`. No `price`, no `visibility`. The catalog-market bridge promised in FEATURES.md ("scope Product.price, Product.visibility by market") has no real targets yet — the bridge ships as a no-op placeholder.
- `ScopedFieldRegistry::register()` validates the entity class but NOT property names. Registering non-existent properties silently succeeds, which is why the no-op-bridge approach is chosen over "register fields that don't exist": the no-op is honest, the latter would mask wiring bugs.

### Decisions locked in during clarification
- **Service split: catalog keeps all single-tree CRUD, new package adds only market features.** Catalog's `CategoryTreeService` retains `createTree` (default + non-default), `setDefaultTree`, `deleteTree`, `ensureDefaultTreeExists`, all placement/node ops. Removes only `assignTreeToMarket`, `unassignMarket`, `resolveTreeForMarket`, the market-assignment branch of `deleteTree`, and the `categoryTreeMarketAssignmentRepository` constructor dep. Multi-tree CRUD stays in catalog because merchants who don't install the bridge can still legally create multiple trees (they just have no resolver). Picked over a deeper split because the smaller blast radius keeps catalog's service shape intact and the alternatives either coupled catalog to an unavailable interface or fragmented a cohesive service.
- **catalog-market ships as a no-op bridge today.** Same shape as `catalog-locale` — composer.json requires `catalog-scope` + `market`, module.php has a `require` block plus an empty `boot` closure (or a closure that registers no fields). Documented as the placeholder that will start contributing fields when `Product` gains `price`/`visibility`. Picked over deferring the package (FEATURES.md tier 3 row would need an annotation) and over adding `price`/`visibility` columns to Product in P4 (scope creep beyond the stated phase goal).
- **Resolver is a dedicated `CategoryTreeMarketResolver` service in `catalog-market-category-trees`.** Single-purpose class with one method `resolveTreeForMarket(string $market): CategoryTree` (returns catalog's `CategoryTree`). Easier to swap via Preference. Sibling write-side service `CategoryTreeMarketAssignmentService` owns `assignTreeToMarket()` and `unassignMarket()`. Two small focused services beat one mixed-concern service.
- **Tier 3 E2E test: full container boot.** Mirrors `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`. Boots scope + scope-pgsql + locale + market + catalog + catalog-scope + catalog-locale + catalog-market + catalog-market-category-trees + config + config-pgsql manifests. Postgres-backed. Creates two trees, assigns each to a market, verifies the resolver returns the right tree per market and falls back to the default tree for unassigned markets.
- **`TreeHasMarketAssignmentsException` guard moves out of `deleteTree`, replaced by a Marko Plugin in the bridge.** When `catalog-market-category-trees` is installed, a `#[Plugin(target: CategoryTreeService::class)]` class with `#[Before(method: 'deleteTree')]` checks the assignment repository and throws the same friendly exception. When the bridge is not installed, `deleteTree` simply delegates to the repository; if a merchant somehow has the assignment table without the bridge installed (impossible in practice, since the bridge ships the table), the PostgreSQL `ON DELETE RESTRICT` FK constraint still protects integrity at a lower layer. Plugin pattern is the only one that preserves a single `deleteTree($treeId)` API across both installation profiles — this is exactly what architecture.md calls out for Marko Plugins ("decorate public methods without subclassing").
- **Naming convention for the multi-tree-CRUD package.** Long name `catalog-market-category-trees` is chosen per FEATURES.md's locked-in naming convention (domain-first bridge with capability suffix). Namespace becomes `Markommerce\CatalogMarketCategoryTrees\`. Verbose but consistent.
- **Demo packages: no changes.** `frontend-demo` does not consume any market API today. Adding the new requires would imply seeding markets/assignments for the demo, which is out of scope for P4. If the demo ever needs to showcase Tier 3, that is a separate effort.

### Files / mechanisms touched
- New: `packages/market/{composer.json,LICENSE,.gitattributes,README.md,config/scope.php,tests/Pest.php,tests/PackageScaffoldingTest.php,tests/ReadmeTest.php}` — mirror of `packages/locale/`. `config/scope.php` declares the `market` axis with a single `default` scope path (merchants add their real markets via their own config overlay).
- New: `packages/catalog-market/{composer.json,LICENSE,.gitattributes,README.md,module.php,src/.gitkeep,tests/Pest.php,tests/PackageScaffoldingTest.php,tests/ReadmeTest.php}` — mirror of `packages/catalog-locale/` but with an empty boot closure (documented as placeholder).
- New: `packages/catalog-market-category-trees/{composer.json,LICENSE,.gitattributes,README.md,module.php,src/.gitkeep,tests/Pest.php}`.
- Moved into `packages/catalog-market-category-trees/src/`:
  - `Entity/CategoryTreeMarketAssignment.php` (namespace becomes `Markommerce\CatalogMarketCategoryTrees\Entity\`)
  - `Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php`
  - `Repositories/CategoryTreeMarketAssignmentRepository.php`
  - `Exceptions/TreeHasMarketAssignmentsException.php`
- New in `packages/catalog-market-category-trees/src/`:
  - `Services/CategoryTreeMarketResolver.php` (returns `Markommerce\Catalog\Entity\CategoryTree`; depends on the new repository interface + catalog's `CategoryTreeRepositoryInterface`)
  - `Services/CategoryTreeMarketAssignmentService.php` (`assignTreeToMarket`, `unassignMarket`)
  - `Plugins/CategoryTreeServiceDeletePlugin.php` (`#[Plugin(target: CategoryTreeService::class)]` + `#[Before(method: 'deleteTree')]`)
- Moved into `packages/catalog-market-category-trees/tests/`:
  - `Support/FakeCategoryTreeMarketAssignmentRepository.php`
  - `Unit/Entity/CategoryTreeMarketAssignmentTest.php`
  - `Unit/Exceptions/TreeHasMarketAssignmentsExceptionTest.php`
  - `Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php`
  - `Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php`
  - Market test cases excised from `catalog/tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php` (entire file) and the deleteTree-with-market case from `CategoryTreeServiceTreeCrudTest.php`. New test files in the new package exercise `CategoryTreeMarketResolver`, `CategoryTreeMarketAssignmentService`, and `CategoryTreeServiceDeletePlugin` independently.
- New: `packages/catalog-market-category-trees/tests/Feature/Tier3EndToEndTest.php`.
- Modified: `packages/catalog/composer.json` — no `require` additions (market lives in the new package, not pulled by catalog). The autoload entries already cover what stays.
- Modified: `packages/catalog/module.php` — drop `CategoryTreeMarketAssignmentRepositoryInterface => CategoryTreeMarketAssignmentRepository` binding.
- Modified: `packages/catalog/src/Services/CategoryTreeService.php` — drop the market repo constructor param, drop `assignTreeToMarket`/`unassignMarket`/`resolveTreeForMarket`, drop the `TreeHasMarketAssignments` guard in `deleteTree`, drop the now-unused imports.
- Modified: `packages/catalog/tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php` — file deleted (relocated).
- Modified: `packages/catalog/tests/Unit/Services/CategoryTreeServiceTreeCrudTest.php` — drop the `deleteTree throws TreeHasMarketAssignmentsException` case (covered by the plugin's own test in the bridge), drop the `assignmentRepo` parameter from the `makeCategoryTreeService` helper.
- Modified: `packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php` — drop the market-assignment cases (`creates a non-default tree, places categories, assigns it to a market, and resolves the tree for that market` and `resolves the default tree for a market with no assignment`), drop the `catalog_category_tree_market_assignments` CREATE/DROP statements in `beforeEach`/`afterEach`, drop the `CategoryTreeMarketAssignmentRepository` import + `makeServices` wiring.
- Modified: `packages/catalog/tests/Unit/ModuleBindingsTest.php` — drop the `CategoryTreeMarketAssignmentRepositoryInterface` binding assertion.
- Modified: `packages/catalog/tests/Unit/ScopeDecouplingTest.php` — update the "preserves all non-scope test cases in CategoryTreeIntegrationTest" assertion to drop the two market cases now relocated.
- Deleted: `packages/catalog/tests/Support/FakeCategoryTreeMarketAssignmentRepository.php`, `packages/catalog/src/Entity/CategoryTreeMarketAssignment.php`, `packages/catalog/src/Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php`, `packages/catalog/src/Repositories/CategoryTreeMarketAssignmentRepository.php`, `packages/catalog/src/Exceptions/TreeHasMarketAssignmentsException.php`, `packages/catalog/tests/Unit/Entity/CategoryTreeMarketAssignmentTest.php`, `packages/catalog/tests/Unit/Exceptions/TreeHasMarketAssignmentsExceptionTest.php`, `packages/catalog/tests/Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php`, `packages/catalog/tests/Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php`.
- Modified: `packages/catalog/README.md` — drop the opening blurb's "per-market category trees" phrase, drop the `resolveTreeForMarket('market:eu')` example. Cross-link to `catalog-market-category-trees`.
- Modified: `packages/catalog/tests/Unit/ReadmeTest.php` — relax assertions on the removed README content (mirror P3 pattern).
- New: `packages/catalog/tests/Unit/MarketDecouplingTest.php` — production safety net asserting `packages/catalog/src` has no references to `CategoryTreeMarketAssignment`, `assignTreeToMarket`, `resolveTreeForMarket`, `unassignMarket`, `TreeHasMarketAssignmentsException`, or `Markommerce\CatalogMarketCategoryTrees\…`. Extends the family of `ScopeDecouplingTest` and `StorefrontDecouplingTest`.
- Modified: `packages/catalog/tests/Unit/ComposerManifestTest.php` — add assertion that catalog.composer.json does NOT require `markommerce/market`, `markommerce/catalog-market`, `markommerce/catalog-market-category-trees`.
- Modified: root `composer.json` — add `markommerce/market`, `markommerce/catalog-market`, `markommerce/catalog-market-category-trees` to `require`; add `Markommerce\Market\Tests\\`, `Markommerce\CatalogMarket\Tests\\`, `Markommerce\CatalogMarketCategoryTrees\Tests\\` to `autoload-dev.psr-4`.
- New: `docs/src/content/docs/packages/market.md`, `docs/src/content/docs/packages/catalog-market.md`, `docs/src/content/docs/packages/catalog-market-category-trees.md`.
- Modified: `docs/src/content/docs/packages/catalog.md` — drop the market sections.
- New: `tests/Unit/Docs/CatalogMarketExtractPagesTest.php`.
- Modified: `FEATURES.md` — P4 row status `pending` → `completed`, plan `tbd` → `catalog-market-extract`. Tier 3 package count refresh if needed.

## Scope

### In Scope
- Create `markommerce/market` (axis declaration only, mirror of locale).
- Create `markommerce/catalog-market` as a no-op field bridge (placeholder package, mirror of catalog-locale with empty boot).
- Create `markommerce/catalog-market-category-trees` hosting `CategoryTreeMarketAssignment` (entity + repo + interface), `TreeHasMarketAssignmentsException`, new `CategoryTreeMarketResolver`, new `CategoryTreeMarketAssignmentService`, and the `CategoryTreeServiceDeletePlugin` guard.
- Move every market-coupled file out of catalog (entity, repository, interface, exception, fake repo, four test files); strip the three market methods + market repo dep from `CategoryTreeService`; drop the binding from catalog's `module.php`.
- Add a `MarketDecouplingTest` in catalog asserting no remaining market references.
- Extend `ComposerManifestTest` in catalog asserting the three new packages are NOT required by catalog.
- Update `CategoryTreeServiceTreeCrudTest`, `CategoryTreeIntegrationTest`, `ScopeDecouplingTest`, `ModuleBindingsTest`, `ReadmeTest` in catalog for the new shape.
- Add a full container-boot Tier 3 integration test in the new package (Postgres-backed, mirrors Tier2EndToEndTest).
- Update READMEs: catalog (strip market mentions), and write new READMEs for the three new packages.
- Update docs site pages: three new pages, edits to catalog.md.
- Update FEATURES.md status table.

### Out of Scope
- Adding `price` or `visibility` columns to `Product` — the no-op bridge shape was chosen explicitly to defer this.
- Anything under `config` decoupling, `config-scope`, `config-locale`, `config-market` — Phase 5.
- Renaming `theme-blank` (still an open question in FEATURES.md).
- Adding a meta-package per tier (`starter-shop`, etc.) — open question deferred.
- Introducing merchant-overridable bridge mappings — deferred per FEATURES.md.
- Channel axis — out of scope per FEATURES.md.
- Updating `frontend-demo` to consume Tier 3 — no current demo consumer, and adding one would imply seeding markets/assignments. Defer.
- Splitting `CategoryTreeService` further (e.g., factoring node ops into a separate service). P4 is an extraction, not a redesign.
- Refactoring `CategoryTreeMarketAssignmentRepository::save` — its upsert behaviour transfers as-is.

## Success Criteria
- [ ] `grep -rn "CategoryTreeMarketAssignment\|assignTreeToMarket\|resolveTreeForMarket\|unassignMarket\|TreeHasMarketAssignmentsException\|Markommerce\\\\CatalogMarketCategoryTrees" packages/catalog/src` returns zero matches.
- [ ] `packages/catalog/src/Services/CategoryTreeService.php` constructor signature no longer accepts a `CategoryTreeMarketAssignmentRepositoryInterface` parameter; the three market methods are deleted.
- [ ] `packages/catalog/composer.json` does not require `markommerce/market`, `markommerce/catalog-market`, or `markommerce/catalog-market-category-trees`.
- [ ] `markommerce/catalog` test suite passes without any of the three new packages being installed.
- [ ] `markommerce/market` exists, declares the `market` axis in `config/scope.php`, has scaffolding tests + README test green.
- [ ] `markommerce/catalog-market` exists as a no-op bridge, has scaffolding tests + README test green, and its module.php documents the placeholder status.
- [ ] `markommerce/catalog-market-category-trees` exists, owns the moved entity/repo/exception/fake, the new resolver + assignment service + delete plugin, and its full test suite (moved cases + new cases + Tier 3 E2E) is green.
- [ ] `CategoryTreeServiceDeletePlugin` fires before `CategoryTreeService::deleteTree` when the bridge is installed and throws `TreeHasMarketAssignmentsException` if the target tree still serves any market.
- [ ] Tier 3 E2E test boots the full module stack against Postgres, creates two trees + two market assignments, and asserts `CategoryTreeMarketResolver::resolveTreeForMarket('us')` and `resolveTreeForMarket('eu')` return the assigned trees while `resolveTreeForMarket('jp')` falls back to the default tree.
- [ ] `composer test:all` from the monorepo root passes.
- [ ] PHPStan level 8 + PHP-CS-Fixer clean for all touched packages.
- [ ] Docs site builds with the three new package pages and updated `catalog.md`.
- [ ] FEATURES.md P4 row reads `completed`, plan field reads `catalog-market-extract`.
- [ ] All requirements from each task file have passing tests.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `markommerce/market` (axis declaration; mirror of locale) | — | completed |
| 002 | Scaffold `markommerce/catalog-market` (no-op bridge placeholder; mirror of catalog-locale) | — | completed |
| 003 | Scaffold `markommerce/catalog-market-category-trees` (composer + module skeleton + tests/Pest) | — | completed |
| 004 | Move `CategoryTreeMarketAssignment` entity, repository, interface, exception, fake, and the four corresponding test files into the new package; update namespaces; register bindings in new module.php | 003 | completed |
| 005 | Add `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` to the new package; relocate market-resolution test cases from catalog into matching new test files | 004 | completed |
| 006 | Add `CategoryTreeServiceDeletePlugin` (`#[Before(method: 'deleteTree')]`) to the new package; unit-test the plugin guard in isolation | 004 | completed |
| 007 | Strip market features from catalog: remove the three service methods + market repo dep + binding + obsolete test cases + README mentions; rewire `CategoryTreeIntegrationTest` setup | 005, 006 | completed |
| 008 | Add `MarketDecouplingTest` to catalog; extend `ComposerManifestTest` with absence assertions for the three new packages | 007 | completed |
| 009 | Tier 3 end-to-end test (Postgres-backed full container boot) in `catalog-market-category-trees/tests/Feature/Tier3EndToEndTest.php` | 005, 006, 007 | completed |
| 010 | READMEs: write `market/README.md`, `catalog-market/README.md`, `catalog-market-category-trees/README.md`; trim `catalog/README.md`; update `catalog/tests/Unit/ReadmeTest.php` assertions | 002, 003, 005, 006, 007 | completed |
| 011 | Docs site: new pages `market.md`, `catalog-market.md`, `catalog-market-category-trees.md`; edit `catalog.md`; add `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` | 010 | completed |
| 012 | Update `FEATURES.md` — P4 status `pending` → `completed`, plan field `tbd` → `catalog-market-extract`; refresh package counts/notes if needed | 007, 008, 009, 010, 011 | completed |
| 013 | Consolidate `catalog-market-category-trees` into `catalog-market`; remove the former package entirely | 001–012 | completed |

## Architecture Notes

### Module structure after P4
```
packages/
  catalog/                                # Multi-tree CRUD stays; market knowledge gone
    src/{Contracts,Entity,Repositories,Services,Exceptions,Enum}/
      Services/CategoryTreeService.php    # No more market methods or repo dep
    Seed/CatalogSeeder.php                # Unchanged
    module.php                            # No CategoryTreeMarketAssignment binding
    tests/{Unit,Feature}/                 # No market files; MarketDecouplingTest added
  market/                                 # NEW — axis declaration only (mirror of locale)
    config/scope.php                      # axes.market.default='default', scopes=['default'=>[]]
    composer.json                         # markommerce/scope
    tests/                                # PackageScaffoldingTest, ReadmeTest
  catalog-market/                         # NEW — no-op bridge today (mirror of catalog-locale)
    src/.gitkeep                          # No PHP source yet
    module.php                            # require: catalog-scope + market; boot: empty closure (documented placeholder)
    composer.json                         # markommerce/catalog-scope, markommerce/market
    tests/                                # PackageScaffoldingTest, ReadmeTest
  catalog-market-category-trees/          # NEW — multi-tree market resolution + assignment
    src/
      Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php
      Entity/CategoryTreeMarketAssignment.php
      Exceptions/TreeHasMarketAssignmentsException.php
      Plugins/CategoryTreeServiceDeletePlugin.php
      Repositories/CategoryTreeMarketAssignmentRepository.php
      Services/CategoryTreeMarketAssignmentService.php
      Services/CategoryTreeMarketResolver.php
    module.php                            # Bindings for the repo interface
    composer.json                         # markommerce/catalog, markommerce/market
    tests/
      Support/FakeCategoryTreeMarketAssignmentRepository.php
      Unit/Entity/CategoryTreeMarketAssignmentTest.php
      Unit/Exceptions/TreeHasMarketAssignmentsExceptionTest.php
      Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php
      Unit/Services/CategoryTreeMarketResolverTest.php           # NEW
      Unit/Services/CategoryTreeMarketAssignmentServiceTest.php  # NEW
      Unit/Plugins/CategoryTreeServiceDeletePluginTest.php       # NEW
      Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php
      Feature/Tier3EndToEndTest.php                              # NEW
```

### Service surfaces after P4
```php
// catalog/src/Services/CategoryTreeService.php  (single-tree + multi-tree CRUD, no market)
public function __construct(
    private CategoryTreeRepositoryInterface $categoryTreeRepository,
    private CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository,
    private CategoryRepositoryInterface $categoryRepository,
) {}

public function createTree(string $code, string $name, bool $isDefault = false): CategoryTree;
public function setDefaultTree(int $treeId): void;
public function deleteTree(int $treeId): void;          // throws CannotDeleteDefaultTreeException; bridge Plugin adds TreeHasMarketAssignmentsException
public function ensureDefaultTreeExists(): CategoryTree;
public function placeCategory(...): CategoryTreeNode;
public function moveNode(...): void;
public function removeNode(...): void;
public function reorderSiblings(...): void;
public function getMaterializedTree(int $treeId): array;
```
```php
// catalog-market-category-trees/src/Services/CategoryTreeMarketResolver.php
public function __construct(
    private CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository,
    private CategoryTreeRepositoryInterface $categoryTreeRepository,
) {}

/** @throws CategoryTreeNotFoundException|DefaultTreeMissingException */
public function resolveTreeForMarket(string $market): CategoryTree;   // assigned-tree-or-default fallback
```
```php
// catalog-market-category-trees/src/Services/CategoryTreeMarketAssignmentService.php
public function __construct(
    private CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository,
    private CategoryTreeRepositoryInterface $categoryTreeRepository,
) {}

/** @throws CategoryTreeNotFoundException */
public function assignTreeToMarket(int $treeId, string $market): void;
public function unassignMarket(string $market): void;                  // no-op when no assignment exists
```
```php
// catalog-market-category-trees/src/Plugins/CategoryTreeServiceDeletePlugin.php
#[Plugin(target: CategoryTreeService::class)]
readonly class CategoryTreeServiceDeletePlugin {
    public function __construct(
        private CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository,
    ) {}

    /** @throws TreeHasMarketAssignmentsException */
    #[Before(method: 'deleteTree')]
    public function beforeDeleteTree(int $treeId): void {
        $assignments = $this->categoryTreeMarketAssignmentRepository->findByTree($treeId);
        if (count($assignments) > 0) {
            $markets = array_map(fn ($a) => $a->market, $assignments);
            throw TreeHasMarketAssignmentsException::forTreeId($treeId, $markets);
        }
    }
}
```

### Cross-package references
- `CategoryTreeMarketResolver::resolveTreeForMarket()` returns `Markommerce\Catalog\Entity\CategoryTree`. The new package imports from catalog — this is the canonical bridge direction (bridge depends on the domain, never the inverse).
- `CategoryTreeServiceDeletePlugin` references `Markommerce\Catalog\Services\CategoryTreeService` as the `Plugin(target: …)` and the catalog-owned `CategoryTreeRepositoryInterface` is not needed because the plugin only checks the assignment side.
- Catalog has no import from `Markommerce\CatalogMarketCategoryTrees\…` after P4. The `MarketDecouplingTest` enforces this.

### Plugin discovery
Marko's `PluginDiscovery` scans every module's `src/` for classes carrying `#[Plugin(target: …)]`. No explicit registration in `module.php` is required — placing `Plugins/CategoryTreeServiceDeletePlugin.php` under the module's autoloaded namespace is enough. Verified by inspecting `packages/scope/src/Plugins/ScopeResolutionCommandPlugin.php` which uses the same pattern without any module-side wiring.

### Tier 3 E2E test pattern
The test mirrors `Tier2EndToEndTest`'s shape **with one critical extension**: `Tier2EndToEndTest` builds a `Container` manually and never wires `PluginInterceptor`, so target classes resolved from it are unproxied. The Tier 3 test MUST explicitly wire `PluginInterceptor + PluginRegistry + PluginDiscovery` before resolving `CategoryTreeService`, otherwise the `CategoryTreeServiceDeletePlugin` does not fire and the delete-guard assertion is meaningless. See task 009's "Plugin interceptor wiring" section for the four required setup steps.
- Boot manifests for `marko/config`, `marko/core`, `marko/database`, `markommerce/scope`, `markommerce/scope-pgsql`, `markommerce/locale`, `markommerce/market`, `markommerce/catalog`, `markommerce/catalog-scope`, `markommerce/catalog-locale`, `markommerce/catalog-market`, `markommerce/catalog-market-category-trees`, `markommerce/config`, `markommerce/config-pgsql`. The bridge's `ModuleManifest` must be constructed with `path: dirname(__DIR__, 2)` (NOT the empty/default value used in `Tier2EndToEndTest` helpers) so `PluginDiscovery::discoverInModule()` can find the plugin class under `src/Plugins/`.
- Create real Postgres tables for `catalog_category_trees`, `catalog_category_tree_market_assignments`, `catalog_categories`, `catalog_category_tree_nodes` using a `PostgresTestConnection` helper copied into the new package's `tests/Feature/Helpers/` directory.
- Create three trees (default + `uk` + `eu`), assign `uk`/`eu` to their non-default trees via `CategoryTreeMarketAssignmentService`, then call `CategoryTreeMarketResolver::resolveTreeForMarket()` for `uk`, `eu`, and `jp`. Assert `uk` → uk-tree, `eu` → eu-tree, `jp` → default-tree.
- Additionally call `CategoryTreeService::deleteTree($ukTreeId)` after assigning `uk` and assert the Plugin guard throws `TreeHasMarketAssignmentsException`. **`CategoryTreeService` must be resolved from the container** (so the plugin proxy wraps it); instantiating it directly with `new CategoryTreeService(...)` would bypass interception and yield a false test signal.

### Decoupling safety net
`MarketDecouplingTest` walks `packages/catalog/src` and asserts none of the following appear in any file:
- `CategoryTreeMarketAssignment`
- `assignTreeToMarket`, `resolveTreeForMarket`, `unassignMarket`
- `TreeHasMarketAssignmentsException`
- `Markommerce\\CatalogMarketCategoryTrees\\`

The test mirrors the shape of `ScopeDecouplingTest` and `StorefrontDecouplingTest`. Any future PR that re-couples catalog to market goes red.

## Risks & Mitigations

- **Risk:** The Plugin attribute fires `Before` only when the wrapping interceptor wires up correctly. If Marko's `PluginDiscovery` fails to scan `catalog-market-category-trees/src/Plugins/`, the guard silently disappears and merchants can delete a tree with active market assignments — they'd see the lower-level `ON DELETE RESTRICT` Postgres error instead of the friendly exception.
  - **Mitigation:** Task 006's unit test instantiates the plugin directly and asserts its `beforeDeleteTree(int)` behaviour. Task 009's Tier 3 E2E test boots a container with `PluginInterceptor` wired, runs `PluginDiscovery::discoverInModule()` against the bridge manifest (with `path` correctly set), and asserts the friendly exception fires when deleting an assigned tree resolved through the container. Both layers must be green before the plan completes.
  - **Sub-risk** (newly identified during devil's advocate review): `Tier2EndToEndTest` — the helper pattern this test extends — does NOT set up `PluginInterceptor` and does NOT set `path` on its `ModuleManifest` instances. A worker copying that pattern verbatim would produce a test that silently bypasses interception (PluginDiscovery would scan `'' . '/src'` finding nothing; the container would resolve `CategoryTreeService` without proxying). Task 009 spells out the four explicit setup steps required to avoid this.

- **Risk:** Marko's `#[Before]` interceptor signature must match the wrapped method exactly. `CategoryTreeService::deleteTree(int $treeId)` takes one `int` — the plugin's `beforeDeleteTree(int $treeId)` must match. A signature drift (e.g., catalog adds an optional second parameter later) silently breaks the interceptor.
  - **Mitigation:** Task 006 documents the expected signature. PHPStan level 8 catches mismatches. Tier 3 E2E test catches behavioural drift.

- **Risk:** `CategoryTreeIntegrationTest` in catalog currently inlines the `CREATE TABLE catalog_category_tree_market_assignments` statement and references it from `beforeEach`/`afterEach`. Stripping those statements while keeping the related test cases would leave broken SQL refs.
  - **Mitigation:** Task 007 removes the market test cases AND the matching CREATE/DROP statements AND the `CategoryTreeMarketAssignmentRepository` wiring in `makeServices` as a single atomic edit. The other test cases (non-default tree placement that doesn't assign to a market, materialization, etc.) keep passing because they never used the table.

- **Risk:** `CategoryTreeServiceTreeCrudTest.php` covers `deleteTree throws TreeHasMarketAssignmentsException when the tree still serves any market` against the catalog service. After P4, that exception path lives in the Plugin, not in catalog's `deleteTree`. Leaving the test in catalog would fail because catalog's `deleteTree` no longer raises that exception.
  - **Mitigation:** Task 007 deletes the specific test case from catalog's file. Task 006 (which lands earlier) adds an equivalent test in the new package against the plugin class directly.

- **Risk:** `ScopeDecouplingTest::preserves all non-scope test cases in CategoryTreeIntegrationTest` asserts string-literal presence of two market test names that move out in this phase. Leaving it untouched after the relocation breaks the catalog suite.
  - **Mitigation:** Task 007 updates the assertion to drop the two market literal strings and keep only the non-relocated cases (e.g., the materialization case).

- **Risk:** Catalog's `module.php` binding for `CategoryTreeMarketAssignmentRepositoryInterface` must be dropped in lock-step with removing the interface itself. If the binding stays but the interface moves to a new namespace, the container fails at boot when trying to autoload `Markommerce\Catalog\Contracts\CategoryTreeMarketAssignmentRepositoryInterface`. If the binding is dropped but a downstream test still expects it, the test fails.
  - **Mitigation:** Task 007 edits `module.php` and `ModuleBindingsTest.php` together. Tier 3 E2E test (task 009) verifies the new package's binding takes its place.

- **Risk:** The bridge `catalog-market` ships empty (no `register()` calls). A merchant who installs it expecting market-scoped fields will see no behaviour change. They might assume the package is broken.
  - **Mitigation:** Task 010's README explicitly calls out the placeholder status with a note linking to FEATURES.md's tier 3 row and the future `Product.price`/`Product.visibility` work. The module.php boot closure carries a leading docblock that documents the same.

- **Risk:** Three new packages added to root composer.json mean three new `autoload-dev.psr-4` paths. Forgetting one breaks the test loader for that package's tests when running from the monorepo root.
  - **Mitigation:** Tasks 001, 002, 003 each include a requirement to update the root composer.json. Task 003 in particular also adds the `Markommerce\CatalogMarketCategoryTrees\Tests\\` autoload entry. The dependency on task 003 in 004/005/006 guarantees the path exists before tests are added.

- **Risk:** `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` both depend on the catalog-owned `CategoryTreeRepositoryInterface`. If the bridge's module.php fails to receive a bound implementation of that interface (because catalog hasn't been loaded yet), services can't be constructed.
  - **Mitigation:** `catalog-market-category-trees/composer.json` requires `markommerce/catalog` directly. Module manifests declare `require: ['markommerce/catalog' => '*']` so Marko's `DependencyResolver` boots catalog before the bridge. The Tier 3 E2E test verifies the wiring end-to-end.

- **Risk:** `CategoryTreeMarketAssignmentRepository::save` overrides the base `Repository::save()` to issue an `INSERT … ON CONFLICT (market) DO UPDATE` statement (PostgreSQL-specific). The class moves verbatim, but its tests must continue to assert against the same upsert semantics.
  - **Mitigation:** Task 004 moves the file and its existing tests as-is. Task 009's E2E test exercises the upsert path by calling `assignTreeToMarket` twice for the same market with different trees and asserting the second call replaces the first.

- **Risk:** The `Tier3EndToEndTest` boots a long module chain. Any required manifest that has changed shape since `Tier2EndToEndTest` (e.g., scope module changes, config-pgsql changes) silently breaks the new test.
  - **Mitigation:** Task 009 starts from a fresh copy of `Tier2EndToEndTest`, extends the manifest list incrementally, and runs the test after each manifest addition during implementation to localise any breakage.
