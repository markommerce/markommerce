# Task 015: Server-rendered page-fragment endpoint

**Status**: complete
**Depends on**: 012, 013
**Retry count**: 0

## Description
Add a storefront endpoint that renders ONLY the product-grid fragment (the cards for one page) through the existing Latte/layout pipeline, so load-more/infinite-scroll JS can fetch and append real server-rendered markup. No JSON, no client-side templating.

## Context
- **How fragment rendering actually works in this stack (verified):** `MarkommerceLayoutMiddleware` matches the request to a route, derives a handle key `ControllerFQCN::action`, looks up the compiled `PreparedTree` for that handle in the layout artifact, runs the controller for side effects (and honors any non-2xx short-circuit), then renders the WHOLE tree via `Renderer::render(...)` and returns `Response::html`. The controller's own 200 body is discarded — the layout output wins. Therefore the fragment endpoint is implemented as a **new controller action + a dedicated layout file** whose root produces ONLY the product-grid markup (no `OneColumnLayout`/`base.latte` chrome). You cannot "render just a sub-component" of the existing `category_show` tree in isolation — you author a separate `Layout` with its own `handle` whose root template is the grid (or a slim fragment template) and whose placement reuses `ProductGridComponent` + the `ProductCard` repeat slot.
- Files: a new controller method, e.g. `CategoryController::pageFragment` with `#[Get('/catalog/category/{id}/page')]` reading `page`/`size`/`sort`; a new layout file `packages/catalog-storefront/layout/category_page_fragment.php` (handle `[CategoryController::class, 'pageFragment']`, NO `extends: OneColumnLayout::class`, root = grid/fragment template) reusing the `ProductGridComponent` placement and `ProductCard` repeat slot from `category_show.php`. This new layout must be compiled into the artifact (the feature test must compile it, mirroring `CategoryLayoutTest`).
- The fragment template reuses `product-card.latte` via the same repeat-slot placement so card markup is byte-identical to the full page. "Identical card markup" is asserted by comparing the card region of both renders.
- Reuse `ProductGridComponent` data + `PaginationOptionsResolver`; render via the same `Renderer` the middleware uses so card markup is identical to the full page.
- **Short-circuit semantics:** because the middleware returns the controller response as-is for any non-2xx status, the controller returns `Response::html('', 404)` for an unknown category and `Response::html('', 410)` when `page > maxPageDepth` (using the SAME depth signal as Task 011/018), and these are honored without rendering. For the happy path the controller returns a 200 placeholder and the layout renders the fragment.
- "No more results" past the end: render an empty grid fragment (the layout naturally yields empty repeat output) plus a marker (e.g. omit `data-next`); a `204` is acceptable but is simpler to keep `200` + empty grid + absent `data-next`.
- Include the `nextPageUrl` (or a `data-next` attribute) in the fragment so the client knows whether to continue.

## Requirements (Test Descriptions)
- [x] `it renders the product cards for the requested page as html`
- [x] `it produces card markup identical to the full page render`
- [x] `it respects the size and sort query params`
- [x] `it signals no more results past the last page`
- [x] `it returns 410 when the requested page exceeds the max depth`
- [x] `it returns 404 for an unknown category`

## Acceptance Criteria
- Endpoint returns server-rendered grid-fragment HTML reusing the product-card template.
- All requirements have passing tests (PHP feature tests).

## Implementation Notes

- New controller action `CategoryController::pageFragment` at `GET /catalog/category/{id}/page` with optional `PaginationOptionsResolver` dependency (nullable, default null) to preserve backward-compat of existing `show`-only tests.
- New layout file `layout/category_page_fragment.php` — `extends: null`, handle `[CategoryController::class, 'pageFragment']`, reuses `ProductGridComponent` + `ProductCard` repeat slot with `product-grid-fragment` template (instead of `product-grid`).
- New template `resources/views/components/product-grid-fragment.latte` — renders `<div class="catalog-product-grid-fragment" data-next="...">` (omits `data-next` when `$nextPageUrl === null`).
- `ProductGridComponent::data()` updated to also compute `nextPageUrl` for `RandomAccessPageInterface` (offset) pages when `currentPage < totalPages`; removed the now-redundant second check.
- Test harness in `CategoryPageFragmentTest.php` mirrors `CategoryControllerTest.php`; uses named `query:` constructor argument on `Request` objects so query params are available to both the controller depth-check and the layout `Source::query()` sources.
