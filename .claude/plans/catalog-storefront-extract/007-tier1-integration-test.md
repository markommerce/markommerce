# Task 007: Tier 1 end-to-end integration test (full container boot, no scope, no locale)

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Add `packages/catalog-storefront/tests/Feature/Tier1EndToEndTest.php`. The test boots a real container with the module manifests for the Tier 1 stack — `marko/config`, `marko/core`, `marko/database`, `marko/routing`, `marko/view`, `marko/view-latte`, `markommerce/catalog`, `markommerce/catalog-storefront`, `markommerce/config`, `markommerce/config-pgsql`, `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank` — and **NOT** any scope, locale, or scope-aware package. It then exercises the public `/catalog/category/{id}` route end-to-end and asserts that:
1. The category page renders against a real product.
2. The container resolves the plain `ProductGridComponent` (not a Preference replacement — nothing replaces it without `catalog-storefront-scope` installed).
3. No `Markommerce\Scope\…` or `Markommerce\Locale\…` class is touched during the request.

This is the canonical Tier 1 contract. Any future change that re-couples the storefront to scope, or accidentally drags locale into Tier 1, fails this test.

## Context
- Reference: `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php` (created in P2 task 011). Same pattern, minus the scope/locale axis.
- Module manifest construction follows the inline pattern from `BridgeContributionTest` and `Tier2EndToEndTest`: build `ModuleManifest` instances pointing at the real package paths under `dirname(__DIR__, 3) . '/packages/{name}'`.
- For the request flow, reuse the Latte engine + view + layout middleware scaffolding from the moved `CategoryControllerTest` (which already has a `CatalogControllerFakeView` and `CatalogControllerFakeContainer`). The Tier 1 test should ideally use real Marko services rather than fakes where practical, but in-memory `FakeProductRepository` / `FakeCategoryRepository` / `FakeProductCategoryAssignmentRepository` are acceptable to avoid Postgres.
- The negative assertion (no scope class touched) is the subtle part. Two approaches:
  - **(a)** Walk the rendered DOM/string and assert it equals the raw product name (no resolver substitution would change it; with no overrides installed there's nothing to substitute anyway). Necessary but not sufficient.
  - **(b)** Wrap the container with a binding-watcher that records every `get()` call and assert no recorded key contains `Markommerce\Scope` or `Markommerce\Locale`. Stronger.
  - The implementer picks; both are valid. Document the choice in the implementation notes.
- The test must NOT install or boot any of: `markommerce/scope`, `markommerce/scope-pgsql`, `markommerce/catalog-scope`, `markommerce/locale`, `markommerce/catalog-locale`, `markommerce/catalog-storefront-scope`. Their absence from the module manifest list is the demonstration that Tier 1 doesn't need them.
- Storefront tests already pass against the moved code (task 002). This task adds a *higher-level* test on top.

## Requirements (Test Descriptions)
- [ ] `it boots the Tier 1 module manifest stack — catalog + catalog-storefront + config + config-pgsql + layout + frontend + theme-blank — without referencing any scope or locale module`
- [ ] `it registers the CategoryController route GET /catalog/category/{id} via RouteDiscovery against the booted container`
- [ ] `it compiles the catalog-storefront category_show layout against the LayoutDiscovery and yields a PreparedTree for the controller handle`
- [ ] `it renders /catalog/category/{id} with a real Product assigned to a Category and returns 200 with the product name and category name in the response body`
- [ ] `it returns 404 when the category does not exist (smoke test for the scope-free path)`
- [ ] `it resolves the plain ProductGridComponent from the container (no Preference replacement is active without catalog-storefront-scope installed)`
- [ ] `it records zero Markommerce\\Scope\\ or Markommerce\\Locale\\ container lookups during the request lifecycle`

## Acceptance Criteria
- All requirements have passing tests in `packages/catalog-storefront/tests/Feature/Tier1EndToEndTest.php`.
- The test is repeatable and runs in the default `composer test` (parallel) suite.
- The test does not depend on Postgres or any other external service.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
