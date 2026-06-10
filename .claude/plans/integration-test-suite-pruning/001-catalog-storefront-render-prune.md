# Task 001: Prune catalog-storefront render suite

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
The 7 `Category*` Feature tests + `Tier1EndToEndTest` re-assert the same controller→layout→render path dozens of times; the catalog-storefront **Unit** suite already renders real Latte HTML via `productGridBuildLatte()` against fakes (markup, presentation modes, crawlable `?page=N` links, canonical/next-page URLs, empty-state). Trim the integration tier to the cases that guard a distinct real risk (real SQL via factories, config-pipeline-driven behavior, HTTP status/redirect/header wiring, full route assembly).

## Context
- Dir: `packages/catalog-storefront/tests/Feature/`
- No-DB coverage that makes markup re-assertions redundant: `packages/catalog-storefront/tests/Unit/Component/ProductGridComponentTest.php`, `ProductCardTest.php`, `ProductPaginationTest.php`.
- Keep `IntegrationTestCase::skipIfUnavailable()` + `->group('integration-destructive')` on every surviving DB test.

## Requirements (verification assertions about the resulting suite)
- [x] `it deletes Tier1EndToEndTest.php integration cases` — remove the milestone file's DB cases (markup/404-smoke/container-resolution/zero-scope-lookup/route-discovery). If the 2 non-DB compile cases (`buildTier1Manifests` shape, `buildTier1Artifact` compile) carry unique value, relocate them to `tests/Unit/`; else delete the file entirely.
- [x] `it collapses CategoryControllerTest to one combined render case` — keep one 200+404 (no `data-template`) case; delete the standalone name-in-heading / every-product / empty-state DB cases (covered by Unit Latte). Move the 3 reflection/file-exists cases to `tests/Unit/`.
- [x] `it trims CategorySortRedirectTest to two cases` — keep one invalid-sort→302 (preserves page/size, drops sort) + the keyset loud-exception case; delete the other ~5 redirect re-assertions.
- [x] `it trims PresentationSwitchTest to the config-driven matrix` — keep the one mode-switch-via-real-config-writes case; delete per-mode singletons and the crawlable-links/`data-next` cases (Unit-covered).
- [x] `it trims CategoryPageFragmentTest to three cases` — keep fragment render, 410 max-depth, 404; delete "identical to full page", size/sort, "no more results".
- [x] `it merges CategorySeoTest canonical cases` — keep canonical-header, view-all, 410-from-config; merge the two self-referencing-canonical cases into one.
- [x] `it relocates ProductGridTemplateTest direct-engine renders to Unit` — the 7 direct-engine fragment/grid branch renders use no DB and aren't tagged integration; move them beside the Unit component tests; the single `handle()` case is covered by the kept Controller case — delete it.
- [x] `it deletes the brittle stock-badge slot test in CategoryLayoutTest` — remove the case asserting internal `PreparedTree`/slot indices; collapse the 2 duplicate render cases to ≤1.
- [x] `it keeps every surviving DB test tagged and skip-guarded`.

## Acceptance Criteria
- `./vendor/bin/pest packages/catalog-storefront/tests/` green (with DB available) and `--exclude-group=integration-destructive` green without DB.
- No dangling `use`/helper references; relocated cases keep correct `dirname()` depth.
- Surviving storefront `integration-destructive` cases ≈ 12–14, each guarding a distinct risk.

## Execution (deletion/relocation task — no Red phase)
1. Run `./vendor/bin/pest packages/catalog-storefront/tests` (DB available) green first.
2. Before deleting each markup re-assertion, confirm the named Unit equivalent (`ProductGridComponentTest` / `ProductCardTest` / `ProductPaginationTest`) asserts the same markup/behavior; keep+note otherwise.
3. Relocations are same-depth `Feature/ → Unit/` (preserve `dirname`); for `ProductGridTemplateTest` direct-engine renders moved to Unit, confirm no destination name collision.
4. Re-run green with and without DB (`--exclude-group=integration-destructive`); phpcs on touched files.

## Implementation Notes

- `Tier1EndToEndTest.php` deleted entirely; 2 non-DB compile cases relocated to `tests/Unit/Tier1CompileTest.php`. Helper functions renamed with `tier1Compile` prefix to avoid collisions. `dirname` depths: from `tests/Unit/` the same 4 levels up reaches the markommerce root; packages root uses `+ '/packages'` suffix (same as Feature original).
- `CategoryControllerTest.php` collapsed to 1 DB test (combined 200+404); 3 reflection cases relocated to `tests/Unit/CategoryControllerReflectionTest.php`.
- `CategorySortRedirectTest.php` trimmed to 2 cases: "preserves page and size params while dropping invalid sort" (302 + params) + keyset loud-exception.
- `PresentationSwitchTest.php` trimmed to 1 case: the config-driven matrix across all 3 modes.
- `CategoryPageFragmentTest.php` trimmed to 3 cases: fragment render (200), 410 max-depth, 404.
- `CategorySeoTest.php` trimmed to 4 cases: canonical-header, view-all, 410, and a merged self-referencing-canonical test covering both view-all-active and view-all-disabled scenarios in one test.
- `ProductGridTemplateTest.php` Feature file deleted; 7 direct-engine renders relocated to `tests/Unit/Component/ProductGridTemplateTest.php` with `dirname(__DIR__, 3)` pointing to the catalog-storefront package root. The handle() integration case was deleted (covered by CategoryControllerTest).
- `CategoryLayoutTest.php` stock-badge slot test deleted; duplicate render cases collapsed from 2 to 1 (kept the more comprehensive "migrates" assertion).
- All 12 surviving DB tests have `->group('integration-destructive')` and `IntegrationTestCase::skipIfUnavailable()`.
- phpcs clean on all touched files.
