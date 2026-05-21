# Task 016: Switch CategoryController to #[Layout] and remove standalone view

**Status**: pending
**Depends on**: 014a, 015
**Retry count**: 0

## Description
Rewire `CategoryController::show()` to use the marko/layout `#[Layout(\Markommerce\ThemeBlank\Layout\OneColumnLayout::class)]` pattern (reusing theme-blank's existing 1column layout — added as a PHP class in task 014a, no catalog-owned layout): the controller becomes a thin action that no longer renders directly, and all data fetching moves into `ProductGridComponent::data()` (already done in task 015). Delete the now-unused standalone `resources/views/category.latte`. Update the controller's tests to match the new pattern and assert end-to-end that `GET /catalog/category/{id}` returns a fully chromed page with the grid component slotted into the layout.

## Context
- **Pattern reference**: `packages/frontend-demo/src/Controller/DemoController.php` and `packages/theme-blank-demo/src/Controller/ThemeBlankDemoController.php`. Both carry `#[Layout(...)]` on the class, and their action methods do nothing (`{}`). `LayoutMiddleware` discards the controller's return value (per task 014a's discovery notes) — the page is assembled from the Layout + its slot components.
- **New controller shape** (note the cross-package class reference — `OneColumnLayout` lives in `markommerce/theme-blank` and was added in task 014a):
  ```php
  use Markommerce\ThemeBlank\Layout\OneColumnLayout;
  use Marko\Layout\Attributes\Layout;
  // …
  #[Layout(OneColumnLayout::class)]
  class CategoryController
  {
      #[Get('/catalog/category/{id}')]
      public function show(int $id): void {}
  }
  ```
  - All three previously-injected dependencies (`CategoryAssignmentService`, `CategoryRepositoryInterface`, `ScopeResolver`, `ViewInterface`) move OUT of the controller — they now live on `ProductGridComponent` (task 015). The controller has no constructor dependencies.
  - `void` return type is fine; `LayoutMiddleware` ignores any return.
- **404 handling**: With Layout active, the framework discards the controller's return value, so `Response::html('', 404)` no longer works at the controller level. Two viable strategies — pick the one that matches `marko/layout`'s current behaviour (verify by reading `LayoutProcessor` and any exception handler middleware before implementing):
  1. **Exception-driven 404**: Let `ProductGridComponent::data()` throw `CategoryNotFoundException` (already wired in task 015). A global exception-to-Response mapper turns that into a 404. If no such mapper exists in `marko/routing` or `marko/core`, this strategy requires adding one — out of scope for this task; fall back to option 2.
  2. **Pre-check in controller**: Inject `CategoryRepositoryInterface` into the controller AFTER ALL and have `show()` early-return a `Response(404)` if the category does not exist. `LayoutMiddleware` *may* respect a non-null Response return — verify in `LayoutMiddleware::process()`. If it does, this is the path of least resistance and we keep the data-fetching duplication minimal because the component still does its own lookup.
  - The plan's preference is option 1 (cleaner separation), but the implementer should switch to option 2 if option 1 requires non-trivial framework changes. Document the choice in the implementation notes.
- **Template deletion**: remove `packages/catalog/resources/views/category.latte`. It's superseded by `catalog::layout/category-page` + `catalog::components/product-grid` + `catalog::components/product-grid-item`. Also remove the `category.latte` rendering test from `CategoryControllerTest` (task 011) — replaced by the new tests below.
- **Existing tests to migrate**: `packages/catalog/tests/Feature/CategoryControllerTest.php` was created in task 011. Its assertions about response body content (rendered product names) need to be re-stated against the new layout output. The end-to-end test should drive a real `Router` + `LayoutMiddleware` so the pipeline (route match → controller → LayoutProcessor → component.data() → templates → assembled HTML) is exercised. Use `packages/frontend-demo/tests/Feature/DemoControllerTest.php` (or theme-blank-demo's equivalent) as the wiring reference.
- **Behaviour after this task**:
  - `GET /catalog/category/{id}` for an existing category: 200, full HTML page including `<mk-grid>` and per-product `<article>` cards with placeholder images.
  - `GET /catalog/category/{id}` for an unknown id: 404 (regardless of which strategy was used).
  - Locale resolution still flows through `ScopeResolver` — but now via `ProductGridComponent::data()`, not the controller.
- **module.php**: no changes needed. The component is auto-discovered by marko/layout's component scanner (verify the discovery path by reading the layout package).
- **Project rules**: `declare(strict_types=1)`, no `final`, `@throws` tags, interface-parameter naming.

## Requirements (Test Descriptions)
- [ ] `it places a Get route at /catalog/category/{id} on the controller action`
- [ ] `it declares theme-blank's OneColumnLayout via the Layout attribute on the controller class`
- [ ] `it returns a 200 response with the assembled layout HTML when the category exists`
- [ ] `it returns a 404 response when the requested category id does not exist`
- [ ] `it includes the category name in the rendered page heading`
- [ ] `it renders every assigned product as a product grid item in the response body`
- [ ] `it renders an empty-state message when the category has no products`
- [ ] `it removes the standalone resources/views/category.latte template`

## Acceptance Criteria
- All requirements have passing tests
- `CategoryController` has no constructor dependencies beyond what the chosen 404 strategy requires
- The end-to-end 200 test exercises the full layout pipeline (no shortcuts that bypass `LayoutMiddleware` / `LayoutProcessor`)
- The 404 path is asserted via the public Router pipeline, not by directly calling the controller method
- No reference to `catalog::category` (the old template ID) remains in the codebase
- Code follows project standards

## Notes for Implementer
- **Verify framework behaviour first**: before writing the controller, read `marko/packages/layout/src/Middleware/LayoutMiddleware.php` to confirm how it handles a controller that returns a `Response` (option 2) and how it handles thrown exceptions (option 1). The discovery notes in task 014a captured the high-level flow but not the exception path — re-check.
- **End-to-end test wiring**: build the test harness using the same pattern as `packages/frontend-demo/tests/Feature/DemoControllerTest.php` (or theme-blank-demo equivalent) so a real `Router` dispatches into `LayoutMiddleware` → `LayoutProcessor` → `ComponentDataResolver` → templates. The `ScopeResolver` instance in tests is still built via the `buildResolverStack()` helper from task 011.
- **Test fixtures**: reuse the in-memory repository fakes from task 006 — bind them in the test container so `ProductGridComponent`'s container-resolved constructor picks them up.
- **Cleanup checklist before marking complete**:
  - `packages/catalog/resources/views/category.latte` deleted.
  - `CategoryController` constructor empty (or only contains the 404-strategy dependency).
  - `tests/Feature/CategoryControllerTest.php` no longer references the deleted template, the old `view->render('catalog::category', ...)` call, or `ScopeResolver` injection on the controller.
  - All catalog tests pass: `./vendor/bin/pest packages/catalog`.
  - Full project lint + phpstan still pass: `./vendor/bin/phpcs`, `./vendor/bin/phpstan analyse`.
- **Future work to leave for a follow-up task (do not do here)**:
  - Adding a real product image field on `Product` and threading it through the partial (currently hardcoded placehold.co).
  - Pagination, filtering, sorting on the grid.
  - Layered navigation / filters in a sidebar — switch the catalog controller from `OneColumnLayout` to `TwoColumnsLeftLayout` (already added to theme-blank in task 014a) and add a sidebar component slotted into the `sidebar-left` slot.
