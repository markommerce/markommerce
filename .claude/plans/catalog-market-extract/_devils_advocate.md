# Devil's Advocate Review: catalog-market-extract

## Critical (Must fix before building)

### C1. Task 009's Tier 3 E2E test will silently NOT exercise the Plugin interceptor as written
**Affected task:** 009 (and indirectly 006's "Risk" mitigation in `_plan.md`).

**Problem:** The plan says "boots the full container so the Plugin interceptor wires up" and instructs the implementer to "construct the real `CategoryTreeService` from catalog via the container so the Plugin interceptor wires up". But the reference pattern (`packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`) does NOT do any of the following:
- It does not call `$container->setPluginInterceptor(...)` — without that, `Container::resolve()` skips the `$this->pluginInterceptor->createProxy(...)` line entirely (see `marko/packages/core/src/Container/Container.php:137,207`). The `CategoryTreeService` instance is returned unproxied, so `deleteTree` runs without any `#[Before]` interception. The "guard via Plugin" claim becomes invisible.
- It does not call `PluginDiscovery::discoverInModule(...)` — and even if it did, `Tier2*Manifest()` helper functions construct `ModuleManifest` instances with no `path` set, so `PluginDiscovery` would scan `''/src` and find zero plugin classes.
- The only working pattern in the codebase for plugin-aware containers is in `packages/layout/tests/Feature/RendererPluginTest.php` and `ExtensionPluginTest.php` (lines 91-105), which manually construct `PluginInterceptor` AND manually register `PluginDefinition` instances into a `PluginRegistry`.

**Concrete consequence:** Task 009's "Plugin guard blocks deleteTree when assignments exist" assertion will fail (because the unproxied `CategoryTreeService::deleteTree` no longer contains the guard after task 007 strips it) OR — worse — will pass for the wrong reason if a developer copy-pastes the catalog FK constraint failure path. Either way the test does not verify the bridge wiring claim it advertises.

**Fix:** Task 009 must spell out the plugin-interceptor wiring explicitly:
1. After `buildTier3Container()` instantiates the Container, instantiate `PluginInterceptor` (passing the container, a fresh `PluginRegistry`, and a `new InterceptorClassGenerator()`) and call `$container->setPluginInterceptor($interceptor)` and `$container->instance(PluginInterceptor::class, $interceptor)` BEFORE any module boot closure runs.
2. Run `PluginDiscovery::discoverInModule()` against a `ModuleManifest` whose `path` is the absolute filesystem path to `packages/catalog-market-category-trees` (e.g., `dirname(__DIR__, 2)`). Register each returned `PluginDefinition` into the `PluginRegistry`.
3. Only after plugin registration is in place should the test resolve `CategoryTreeService` from the container — that is the call that triggers `PluginInterceptor::createProxy()`.

Without these steps the plan's central claim (that the Tier 3 E2E test "proves the interceptor is wired in real boot") is unverifiable.

### C2. `module.php` for the new package needs the manifest `path` attribute to be set in tests
**Affected task:** 009.

**Problem:** Same root cause as C1. The plan re-uses `tier2*Manifest()` helpers which omit `path`. For Plugin discovery the test MUST construct `ModuleManifest(... path: dirname(__DIR__, 2), ...)` for the bridge package. This is not stated anywhere in tasks 003, 006, or 009.

**Fix:** Task 009's Context section must instruct the implementer to construct the bridge's `ModuleManifest` with `path: dirname(__DIR__, 2)` (and not copy the `path: ''` pattern from `Tier2EndToEndTest`). Task 003's `module.php` is a separate file from this concern; the fix lives in 009.

### C3. Task 007 strips `deleteTree`'s `TreeHasMarketAssignmentsException` from `CategoryTreeService::deleteTree`'s `@throws` clause AND its imports — but the docblock still lists it
**Affected task:** 007.

**Problem:** `CategoryTreeService::deleteTree` currently carries `@throws CategoryTreeNotFoundException|CannotDeleteDefaultTreeException|TreeHasMarketAssignmentsException`. Task 007 must update that `@throws` line to drop `TreeHasMarketAssignmentsException`, otherwise PHPStan level 8 fails on an undefined imported class once the import line is removed. The current task description mentions deleting unused imports but does not call out the `@throws` line edit explicitly.

**Fix:** Add an explicit requirement to task 007: "Update `CategoryTreeService::deleteTree`'s `@throws` PHPDoc tag to drop `TreeHasMarketAssignmentsException` (the exception no longer propagates from this method after the guard moves to the Plugin bridge)."

### C4. `ScopeDecouplingTest::preserves all non-scope test cases in CategoryTreeIntegrationTest` will fail BEFORE task 007 even runs if task 004's deletes break its referenced literals
**Affected tasks:** 004 (dependency), 007 (fix), and the ordering edge.

**Problem:** `packages/catalog/tests/Unit/ScopeDecouplingTest.php` lines 96-103 assert that `CategoryTreeIntegrationTest.php` contains the two market-related test name literals. Task 004 deletes the *moved* files but only Task 007 strips the market cases out of `CategoryTreeIntegrationTest`. Between 004 and 007 the catalog suite is intentionally broken (acknowledged in 004), but the assertion in `ScopeDecouplingTest` still passes during that window because `CategoryTreeIntegrationTest` is unmodified. The breakage starts at 007 when those test cases are removed.

This is acknowledged in the plan ("Risk:" line 272), but the listed mitigation only updates `ScopeDecouplingTest` "to drop the two market literal strings". That removes the assertion entirely — but the assertion name says "preserves all non-scope test cases" which is the contract that future PRs rely on. The right thing to do is rename/repurpose the assertion to reference only the surviving cases (e.g., the materialization case), not silently delete the two literal-presence checks.

**Fix:** Task 007's edits to `ScopeDecouplingTest.php` must keep at least one literal-presence assertion (`'materializes the tree with correct nesting and position order against the real database'`) so the test name remains accurate. Spell this out as a requirement.

### C5. `tests/Unit/MarketDecouplingTest.php` walk path will include the test file itself unless exclusion is enforced
**Affected task:** 008.

**Problem:** Task 008 says "with `MarketDecouplingTest.php` itself excluded from the walk". But the forbidden substrings list includes the literal strings `assignTreeToMarket`, `resolveTreeForMarket`, `unassignMarket`, `TreeHasMarketAssignmentsException`, and `CategoryTreeMarketAssignment` — these MUST appear in the test file itself (it is asserting their absence). The exclusion must be precise: skip files whose basename equals `MarketDecouplingTest.php` (matching how `ScopeDecouplingTest` line 66 excludes itself).

The current task body does call out the exclusion in prose but does not include it in the "Requirements (Test Descriptions)" checklist, which means a TDD worker might write the walk first, fail the self-test, and then add the exclusion as an afterthought.

**Fix:** Add an explicit requirement to task 008: "the test must skip its own file in the walk (match on basename `MarketDecouplingTest.php`)."

### C6. Task 002's "no scoped fields registered" assertion will fail if any other manifest in the test pulled in `catalog-locale`'s boot
**Affected task:** 002.

**Problem:** Task 002 requires `it registers no scoped fields when the boot closure runs against a fresh ScopedFieldRegistry`. That is verifiable by running the closure against a brand-new `ScopedFieldRegistry` and asserting `hasScopedProperties(Product::class)` returns false. But the wording "against a fresh ScopedFieldRegistry" needs to be unambiguous: the test must instantiate a fresh `ScopedFieldRegistry` and pass it directly to the closure (without invoking the boot of `catalog-locale`, which would populate the same shared registry if the test reuses the container helper from `catalog-locale/tests/Feature/BootContributionTest.php`).

The current task body is unclear on this. A worker mirroring `catalog-locale`'s `BootContributionTest` might unwittingly include `catalogLocaleModuleManifest()`, causing the assertion to fail (because `catalog-locale` registers `Product.name`).

**Fix:** Add to task 002's Implementation Notes: "The boot-runs-empty test must construct a fresh `ScopedFieldRegistry` directly and invoke ONLY the `catalog-market` boot closure (do not chain `catalog-locale` or any other bridge into the same container)."

### C7. Task 009 plan asserts the upsert path with `assignTreeToMarket` "twice for the same market" — but `CategoryTreeMarketAssignmentService::assignTreeToMarket` does NOT have upsert semantics on the service side
**Affected tasks:** 005, 009.

**Problem:** The repository's `save()` is what implements ON CONFLICT upsert. The service just constructs a new `CategoryTreeMarketAssignment` and saves it. That's fine in catalog today because the repository handles the upsert. But the test scenario in task 009 ("`assignTreeToMarket` upsert: assigning a second tree to the same market replaces the first") needs the in-memory `FakeCategoryTreeMarketAssignmentRepository::save()` (for task 005's unit tests) to mimic the same upsert semantics — keyed by `market`, replacing the existing entry rather than appending.

Looking at `CategoryTreeServiceMarketResolutionTest.php` lines 42-55, the existing test `assignTreeToMarket replaces an existing assignment for the same market` expects `assignmentRepo->byMarket` to have count 1 after two calls — meaning the fake already implements upsert-by-market. Good. The plan's task 005 just relocates this test; no new fake behaviour needed.

However, task 005's text does not mention that the fake's upsert-by-market behaviour is load-bearing. A worker re-implementing the fake (rather than re-using the relocated one) could miss this. Also, the relocation in task 004 moves the existing fake (correct), but the dependency graph (005 depends on 004) is what enforces this. Tasks 005's "replaces an existing assignment" requirement is fine as-is, but the fake-mirrors-repo invariant deserves a one-line callout.

**Fix:** Add a sentence to task 005's Context block: "The relocated `FakeCategoryTreeMarketAssignmentRepository` already implements upsert-by-market semantics (the `byMarket` map is keyed by market string, so re-saving for the same market overwrites). Do not rewrite the fake."

## Important (Should fix before building)

### I1. Task 004's namespace transformation for the entity changes the FQCN but the `#[Table]` table name stays — yet the integration test re-creates the table inline using `catalog_` prefix
**Affected task:** 004.

**Problem:** The plan correctly says the entity keeps `#[Table('catalog_category_tree_market_assignments')]` and the FK references catalog tables. Task 004 also moves the `CategoryTreeMarketAssignmentRepositoryIntegrationTest.php` which inlines `CREATE TABLE catalog_category_tree_trees` etc. (it FKs to `catalog_category_trees`). The relocated test will need the catalog table `catalog_category_trees` to exist before creating the FK'd assignments table. The current task description doesn't say whether the relocated integration test (a) brings its own CREATE for `catalog_category_trees` (it already does — see lines 45-59), and (b) drops/cleans them in `afterEach`.

If the new package's `PostgresTestConnection` helper is copy-pasted, this works. If the test is rewritten to share catalog's helper, the cross-package include path will need `dirname(__DIR__, 3)` to traverse out of the new package, into the monorepo root, into `catalog/tests/Feature/Helpers/`. This is brittle.

**Fix:** Add to task 004's Context block: "Copy `PostgresTestConnection.php` into `packages/catalog-market-category-trees/tests/Feature/Helpers/` rather than depending on catalog's copy. This avoids cross-package `require_once` paths that bake the monorepo layout into the test file."

### I2. Task 004 moves the binding into the new module.php but the catalog `module.php` still binds the old interface; intermediate state breaks autoload until task 007 lands
**Affected tasks:** 004, 007.

**Problem:** Task 004 explicitly leaves catalog's `module.php` untouched (re-confirmed in 004's Acceptance Criteria: "The catalog test suite is intentionally LEFT broken at the end of this task"). But the binding line in `packages/catalog/module.php` references `Markommerce\Catalog\Contracts\CategoryTreeMarketAssignmentRepositoryInterface::class` and `Markommerce\Catalog\Repositories\CategoryTreeMarketAssignmentRepository::class` — both of which are deleted in 004. PHP will fatal-error on the `module.php` require because of `::class` resolution against non-existent classes.

Wait — `::class` is a compile-time constant that resolves the literal string at parse time WITHOUT triggering autoload. So a `module.php` referencing a deleted class via `::class` will produce a string like `'Markommerce\\Catalog\\Contracts\\...'` without crashing. The binding registration would fail later when the container tries to autoload that class, but that only happens if a test asks for it.

Still — `ModuleBindingsTest.php` line 78-84 explicitly asserts the binding key is present in the array. That test passes (it just reads the array). But other tests (e.g. `CategoryTreeIntegrationTest`) that instantiate `CategoryTreeService` directly with the old constructor will crash on the missing class type-hint. Task 004 acknowledges catalog tests are intentionally broken in this window. So this is fine — but only if task 007 lands in the same PR as 004 (it does — they're sequential in a single plan).

**Fix:** Add to task 004's Acceptance Criteria: "Workers must NOT run `composer test` against `packages/catalog/` after task 004 completes — catalog is intentionally in a broken intermediate state until task 007 lands. Validate task 004's correctness by running ONLY the new package's tests (`packages/catalog-market-category-trees/`)."

### I3. Task 005 and 006 share dependency `004` but can run in parallel — if both add files to the new package's `module.php` they will collide
**Affected tasks:** 005, 006.

**Problem:** Tasks 005 and 006 both list `004` as their only dependency, suggesting they can run in parallel. Task 005 adds `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` (no `module.php` edits required — those are services, not bound interfaces unless the test expects them to be). Task 006 adds the plugin (no `module.php` edits needed — plugin discovery is automatic). So no collision today.

But: if a worker decides to register `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` as `singleton` for performance (a reasonable choice mirroring scope), the file edits could race with 006's edits. Also, both tasks need to verify `composer dump-autoload` works, which is a stateful operation across tasks.

**Fix:** Add to task 005's and 006's Acceptance Criteria a note that NEITHER task is permitted to edit `module.php` — task 004 sets the bindings, and these two tasks add pure source files only. If a future need arises for singleton registration of the resolver, that's a follow-up.

### I4. Task 003 says the README is "not required in this task (lands in task 010)" — but `packages/catalog-locale` has a `ReadmeTest.php` listed in the test files that loads on `composer test` runs
**Affected tasks:** 003, 010.

**Problem:** Task 003 explicitly defers the README to task 010, which is fine for catalog-market-category-trees. But task 003's Acceptance Criteria says "the package test suite is green" — and the `tests/Pest.php` will scan for any test file. If task 003 ships a `ReadmeTest.php` (mirroring locale's pattern), the test will fail because there's no README. If task 003 omits the `ReadmeTest.php`, then task 010 must add both the README AND the `ReadmeTest.php`. The plan only says task 010 writes "READMEs" — it doesn't say it adds the `ReadmeTest.php` for `catalog-market-category-trees`.

Wait — line 26 of task 003 says: "README is **not** required in this task (lands in task 010 once the package's behaviour is final)." And task 010's Requirements line 35 says "each new package ships a ReadmeTest". Good — task 010 covers this. But task 003's `tests/PackageScaffoldingTest.php` should NOT assert "README exists" or it will fail until task 010.

**Fix:** Add to task 003's Acceptance Criteria: "`tests/PackageScaffoldingTest.php` must NOT include any assertion about README presence — that arrives in task 010 alongside the README."

### I5. Task 011 docs test file location is inconsistent with naming convention
**Affected task:** 011.

**Problem:** Task 011 mentions `tests/Unit/Docs/CatalogMarketExtractPagesTest.php`. The two prior phases used `CatalogScopeDecouplePagesTest.php` and `CatalogStorefrontExtractPagesTest.php` — the naming pattern is `{plan-name-pascal-case}PagesTest`. `catalog-market-extract` → `CatalogMarketExtractPagesTest` (consistent — good). But the file is "at the monorepo root" (referenced in `_plan.md`'s "Files / mechanisms touched" as `tests/Unit/Docs/...`), not in a package. The plan does not confirm whether the monorepo has a root-level `tests/Unit/Docs/` directory.

Quickly verified: the previous phase plans both add files at `tests/Unit/Docs/` at the monorepo root. This is consistent with existing structure.

**Fix:** No source change needed — the path is consistent. Add to task 011's Implementation Notes: "Place the test at the monorepo root: `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` (matches `CatalogScopeDecouplePagesTest` and `CatalogStorefrontExtractPagesTest` locations)."

### I6. The `CategoryTreeMarketAssignmentRepositoryInterface` extends `Marko\Database\Repository\RepositoryInterface` — relocation must preserve this for the existing repository test
**Affected task:** 004.

**Problem:** `FakeCategoryTreeMarketAssignmentRepositoryTest.php` (lines 10-15) asserts the relocated interface still extends `Marko\Database\Repository\RepositoryInterface`. Task 004 must preserve this (the file move includes the parent interface), but the requirement is not called out explicitly.

**Fix:** Task 004's relocation of `CategoryTreeMarketAssignmentRepositoryInterface` should include a one-line reminder: "The interface must continue to extend `Marko\Database\Repository\RepositoryInterface` (the relocated test asserts this)."

### I7. Plugin interceptor argument-count contract: `beforeDeleteTree(int $treeId): void` matches `deleteTree(int $treeId): void` — what about return type?
**Affected task:** 006.

**Problem:** Marko's `#[Before]` interceptor pattern from `marko/packages/core/tests/Unit/Plugin/PluginInterceptionTest.php` shows two `Before` signature patterns: `beforeHash(string $value): ?string` (returns `null` to continue, anything else short-circuits) and `beforeHash(string $value): array` (returns modified args). For a `void` target, the standard is `void`-returning `Before` hooks signal "continue" by completing without short-circuit, and throw to short-circuit with failure.

The plan's `beforeDeleteTree(int $treeId): void` is correct: it throws on guard failure, returns normally to continue. This works.

But: the plan's `_plan.md` line 224 shows `public function beforeDeleteTree(int $treeId): void`. Task 006's Context block (line 13) matches this. No issue — just confirming. Add a documentation cross-link to the interception convention.

**Fix:** Add to task 006's Implementation Notes: "Marko's `#[Before]` plugin returns: `void` (continue), `null` (continue, when nullable type), `array` (replace args), any non-null value (short-circuit). The guard throws, so `void` is correct. See `marko/packages/core/tests/Unit/Plugin/PluginInterceptionTest.php` for the convention."

### I8. Demo packages and `composer test:all` for the monorepo
**Affected task:** Tier 3 success criteria in `_plan.md`.

**Problem:** Success criterion `composer test:all` from the monorepo root passes. After P4, the root composer requires the three new packages. But the demo packages (`frontend-demo`, `theme-blank-demo`, `layout-demo`) are in `require-dev`, and they don't reference any market API. So they should continue to pass. However, if `frontend-demo`'s `composer.json` was last touched in P3 and the test suite reads `Markommerce\Catalog\Contracts\CategoryTreeMarketAssignmentRepositoryInterface` somewhere transitively (e.g., a Pest container helper), it would fail. The plan asserts that demos do NOT consume market API (verified by grep in Discovery Notes line 20). Trust but verify during task 007.

**Fix:** Add to task 007's Acceptance Criteria: "Run `composer test:all` from the monorepo root (not just `packages/catalog/`). If any demo package breaks due to the deleted interface/exception/repository, it is a sign that the demo had an undocumented coupling — escalate to user rather than patching the demo."

### I9. `Markommerce\\Market\\` and `Markommerce\\CatalogMarket\\` forbidden substrings in `MarketDecouplingTest` are too aggressive
**Affected task:** 008.

**Problem:** Task 008 forbids `Markommerce\\Market\\` and `Markommerce\\CatalogMarket\\` in any catalog source/test file. This catches direct imports. But: the `MarketDecouplingTest` itself contains those strings as assertion targets. Task 008 already excludes itself from the walk (call-out in I5/C5). Good.

But also: `packages/catalog/tests/Unit/ComposerManifestTest.php` (when extended per the plan) will need to assert absences of `markommerce/market`, `markommerce/catalog-market`, `markommerce/catalog-market-category-trees`. These are package names, not namespaces, so they don't match the `Markommerce\\Market\\` forbidden pattern. Safe.

**No fix needed.** Just noting for the implementer.

### I10. Task 005's `CategoryTreeMarketResolver` test "throws DefaultTreeMissingException when neither a market assignment nor a default tree exist" — but the relocated test from catalog covers this case differently
**Affected task:** 005.

**Problem:** Task 005's requirement reads `CategoryTreeMarketResolver throws DefaultTreeMissingException when neither a market assignment nor a default tree exist`. Looking at the source service (`CategoryTreeService::resolveTreeForMarket`, lines 153-169), the only path to `DefaultTreeMissingException` is when there's no assignment AND `findDefault()` throws. The `FakeCategoryTreeRepository` needs to throw on `findDefault()` when no default exists — verify this is the fake's behavior, otherwise the test would silently fail to exercise the branch.

`CategoryTreeServiceMarketResolutionTest.php` (already in catalog, lines I read up to 100) covers "returns the default tree when no assignment exists" but probably not the `DefaultTreeMissingException` case. Adding it in task 005 is fine but it's a NEW test (not strictly a relocation).

**Fix:** Update task 005's Description to note that "throws DefaultTreeMissingException when neither a market assignment nor a default tree exist" is a NEW test case (not a relocation) and the implementer must verify `FakeCategoryTreeRepository::findDefault()` throws `DefaultTreeMissingException` when no default tree exists. If the fake doesn't, extend it.

## Minor (Nice to address)

### M1. Tier 3 E2E test should also assert: deleting an unassigned tree succeeds when the bridge is installed
Already covered by task 009 requirement "it allows deleteTree on a non-default tree with no market assignments". OK.

### M2. The `_plan.md` mentions `config/scope.php` for `market` declares "axes.market.default='default', scopes=['default'=>[]]"
This mirrors the `locale` axis exactly. No issue. But: a one-line comment in `config/scope.php` ("Merchants extend this list with their real markets via their own config overlay") would help merchants self-onboard.

### M3. Naming: `CategoryTreeServiceDeletePlugin` vs. plugin method `beforeDeleteTree`
Convention from `ScopeResolutionCommandPlugin` (target method `execute`, hook `beforeExecute`/`afterExecute`). Match holds. No fix.

### M4. Task 010's README for `catalog-market-category-trees` should call out that the package depends on PostgreSQL (via the ON CONFLICT upsert + ON DELETE RESTRICT FK)
The repository's `save()` uses PostgreSQL-specific syntax. A merchant trying to swap in MySQL would silently fail. Worth a one-line "PostgreSQL only" note in the README and docs.

### M5. The new package's namespace `Markommerce\CatalogMarketCategoryTrees\` is verbose — `_plan.md` acknowledges this. Workers should double-check PHP-CS-Fixer doesn't reflow imports inconvenietly.

### M6. The `_plan.md` Architecture Notes lists test files under `packages/catalog-market-category-trees/tests/`:
- `Unit/Services/CategoryTreeMarketResolverTest.php`
- `Unit/Services/CategoryTreeMarketAssignmentServiceTest.php`
- `Unit/Plugins/CategoryTreeServiceDeletePluginTest.php`
- `Feature/Tier3EndToEndTest.php`

These names match the prescribed shape across tasks 005, 006, 009. Good.

## Questions for the Team

### Q1. Should `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` be registered as `singletons` in `module.php`?
The catalog's `CategoryTreeService` is not a singleton (it has per-request mutable cache). The new services have no mutable state. Marko's convention from `packages/scope` is to register service-like classes as `singletons`. Decision: register or not?

### Q2. Should `catalog-market` ship with a leading docblock in its currently-empty boot closure, or with a single commented-out example `register()` call as a self-documenting placeholder?
A docblock alone is invisible to merchants reading `module.php`. A commented `register()` call shows the intended future shape. Decision: how prescriptive should the placeholder be?

### Q3. Should the Tier 3 E2E test cover a "delete bridge package; verify guard disappears" scenario?
This would prove the guard is conditional on the bridge being installed. But it requires booting a second container without `catalog-market-category-trees`, which doubles test setup. Decision: worth the cost or out of scope?

### Q4. The `_plan.md`'s "Files / mechanisms touched" lists `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` at the monorepo root. Confirm this directory exists and the test loader picks it up.
Verified: prior phases created `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php` and `CatalogStorefrontExtractPagesTest.php` at the same level. Path is correct. No question — this is just a verification reminder.

### Q5. The plan defers any update to `frontend-demo`. If a curious merchant clones the repo and runs the demo expecting Tier 3 to "just work", will the missing market seed data be confusing?
Defer-or-do is a product question. Out of scope for this review.
