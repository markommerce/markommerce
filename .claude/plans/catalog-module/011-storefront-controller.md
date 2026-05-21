# Task 011: Storefront CategoryController + View

**Status**: completed
**Depends on**: 008, 009
**Retry count**: 0

## Description
Create the storefront `CategoryController` that handles `GET /catalog/category/{id}`, lists every product assigned to that category, and renders a Latte view. An unknown category id returns a 404.

## Context
- Controller at `packages/catalog/src/Controller/CategoryController.php`; Latte template at `packages/catalog/resources/views/category.latte`.
- Route: action method carries `#[Get('/catalog/category/{id}')]` (from `marko/routing`). Marko's `Router` maps the `{id}` route parameter to an `int $id` action argument and casts it — so the action signature is `show(int $id): Response`.
- Constructor-injects `CategoryAssignmentService`, `CategoryRepositoryInterface`, `Markommerce\Scope\Resolver\ScopeResolver`, and the view (`Marko\View\ViewInterface`). Follow the interface-parameter-naming rule.
- `ScopeResolver` is a concrete `readonly class` (not an interface) with FOUR constructor dependencies: `ScopeMetadataFactory`, `ScopeWalker`, `ScopeContext`, `ScopeSignatureValidator`. There is no fake for it. At runtime the scope module's `module.php` registers `ScopeResolver` as a container singleton, so the catalog controller resolves it for free — no catalog-side binding is needed. In tests you must build the real scope stack: replicate the `buildResolverStack()` helper from `packages/scope/tests/Feature/DefaultScopeResolutionTest.php` (it constructs `PhpScopeRegistry` → `ScopeContext` → `ScopeMetadataFactory` → `SignatureCandidateEnumerator` → `ScopeWalker` → `ScopeSignatureValidator` → `ScopeResolver`). Use the shipped `packages/scope/config/scope.php` (locale axis only has the `default` scope) for the registry — that is sufficient because `resolved()` of an override-free product falls back to the base column value.
- Behaviour:
  - Look up the category; if it does not exist, return a `Marko\Routing\Http\Response` with status 404 (catch `CategoryNotFoundException` from the service, or check existence first). `Response` is a `readonly class`; use `Response::html($body, 404)` or `new Response($body, 404, $headers)`.
  - Otherwise fetch `productsInCategory($id)` and render `category.latte` with the category and its products, returning a 200 `Response` (`Response::html($renderedHtml)`).
  - Product display names are obtained through `ScopeResolver::resolved($product, 'name')` so the active request locale is honoured (with the default scope config this equals the base name; it becomes locale-aware once locales are registered).
- The Latte view lists each product's resolved name. It may extend a `markommerce/theme-blank` layout template (see `packages/theme-blank/resources/views/layout/`). Keep it minimal — a heading plus a product list.
- Reference patterns: `packages/frontend-demo/src/Controller/DemoController.php` for the route/controller shape, and `packages/frontend-demo/tests/Feature/DemoControllerTest.php` for wiring a `LatteView` in tests. For controller behaviour tests, instantiating the controller directly with the task-006 fakes, a real `ScopeResolver` (built via the scope stack helper above), and a `ViewInterface` is acceptable — a full `Router` build is not required.
- `DemoController` returns `void` and relies on `marko/layout`'s `LayoutMiddleware` to render. This task instead returns a `Response` directly (the simpler direct-render approach chosen in `_plan.md`), so the `category.latte` template must NOT depend on a `#[Layout]` attribute or layout slots — it is rendered standalone via `ViewInterface`. A `LatteView` rendered standalone has no Vite/layout chrome; keep the template self-contained.
- No `final`. `declare(strict_types=1);`. `@throws` tags where exceptions propagate.

## Requirements (Test Descriptions)
- [x] `it places a Get route at /catalog/category/{id} on the controller action`
- [x] `it returns a 200 response when the requested category exists`
- [x] `it returns a 404 response when the requested category id does not exist`
- [x] `it renders a 200 response for an existing category that has no assigned products`
- [x] `it includes every assigned product name in the rendered response body`
- [x] `it renders product names through ScopeResolver rather than the raw column value`

## Acceptance Criteria
- All requirements have passing tests
- Controller depends only on services/interfaces, not concrete repositories
- Code follows code standards

## Implementation Notes
- Controller at `packages/catalog/src/Controller/CategoryController.php` uses `#[Get('/catalog/category/{id}')]` on the `show(int $id): Response` method.
- 404 is returned by first checking `categoryRepository->find($id)` — no need to catch an exception from a service call.
- Products are fetched via `categoryAssignmentService->productsInCategory($id)`, then resolved names are built via `ScopeResolver::resolved($product, 'name')` into a `$resolvedNames` array passed to the template.
- Template at `packages/catalog/resources/views/category.latte` is standalone HTML (no layout extension) showing category heading and a product list.
- Tests build the real `ScopeResolver` stack by loading `packages/scope/config/scope.php` (path resolved via `dirname(__DIR__, 3) . '/scope/config/scope.php'` from the test file).
- The `LatteView` in tests is built using `LatteEngineFactory` (base Latte engine without Vite extension, which is sufficient for standalone template rendering).
