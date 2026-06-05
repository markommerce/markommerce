# Task 013: ProductGrid component/DTO + layout query-param wiring

**Status**: complete
**Depends on**: 012
**Retry count**: 0

## Description
Make the storefront product grid paginated: read `page`/`size`/`sort` query params in the layout, fetch a `Page` via the paginated service, and expose the pagination state (items, resolved presentation mode, page-link URLs, next/previous positions, and — when random-access — current/total pages) on `ProductGridData`.

## Context
- Files: `packages/catalog-storefront/src/Component/ProductGridComponent.php`, `packages/catalog-storefront/src/Data/ProductGridData.php`, `packages/catalog-storefront/layout/category_show.php`.
- Layout: add `Source::query('page', default: 1, as: 'int')`, `Source::query('size', default: 0, as: 'int')`, `Source::query('sort', default: '', as: 'string')` props to the `ProductGridComponent` placement in `category_show.php`. NOTE: `Source::query` only supports casts `int|string|bool` (verified in `Source::query`), so `size`/`sort` must use those. The component's `data()` parameter NAMES must exactly match the layout prop keys (`category`, `page`, `size`, `sort`) — the Renderer resolves props by name and calls `data(...$resolvedProps)`.
- **`data()` signature changes** from `data(Category $category)` to `data(Category $category, int $page, int $size, string $sort)`. This WILL break the existing `ProductGridComponentTest` calls that invoke `$component->data($category)` — those tests must be updated as part of this task (add the new args / overloads) so the suite stays green.
- Component: use `PaginationOptionsResolver` (injected) + `CategoryAssignmentService::paginatedProductsInCategory()`. Add `PaginationOptionsResolver` to the component constructor (constructor injection). Keep the existing price/name/description resolution for the page's products only.
- **URL building needs the request path/query.** The component does not directly receive the `Request`. To build crawlable `?page=N` hrefs preserving `size`/`sort`, either (a) accept the current path + query as a `Source::query`/route-derived prop, or (b) build relative URLs (`?page=N&size=…&sort=…`) that don't need the absolute path. Relative query-only hrefs are simplest and crawlable; prefer (b) and document it. If absolute/self-canonical URLs are needed, that belongs to Task 018 which does have controller/request access.
- Extend `ProductGridData` (still `ExtensibleData`) with: `PaginationPresentation $presentation`, `?int $currentPage`, `?int $totalPages`, `bool $hasNext`, `bool $hasPrevious`, and a list of crawlable page-link URLs (with preserved `size`/`sort` query params) for numbered mode plus a `nextPageUrl` for load-more/infinite.
- Build page-link URLs from the request path + query params (a small URL builder helper); these must be real `?page=N` hrefs.

## Requirements (Test Descriptions)
- [x] `it reads the requested page from the query string defaulting to page 1`
- [x] `it fetches only the current page of products`
- [x] `it exposes the resolved presentation mode on the grid data`
- [x] `it exposes crawlable page-link urls preserving size and sort params`
- [x] `it exposes a next-page url when more products exist`
- [x] `it exposes current and total pages for the random-access offset strategy`
- [x] `it resolves prices and names only for the products on the current page`

## Acceptance Criteria
- `ProductGridData` carries everything the template needs to render any presentation mode.
- The existing `ProductGridComponentTest` is updated for the new `data()` signature and still passes.
- All requirements have passing tests. Tests that exercise actual pagination over a join need a DB-backed path or query-builder spy (the array-backed `FakeProductRepository` cannot paginate a join); unit tests for URL/presentation exposure may use a stubbed `Page`.

## Implementation Notes

- `ProductGridData` extended with `presentation`, `currentPage`, `totalPages`, `hasNext`, `hasPrevious`, `pageLinkUrls`, `nextPageUrl` fields.
- `ProductGridComponent::data()` now accepts `(Category $category, int $page, int $size, string $sort)`.
- `PaginationOptionsResolver` injected into `ProductGridComponent` constructor.
- Layout `category_show.php` wired with `Source::query('page', 1, 'int')`, `Source::query('size', 0, 'int')`, `Source::query('sort', '', 'string')`.
- URL building uses relative `?page=N&size=S&sort=X` style (size and sort only included when non-default).
- All existing tests updated to use 5-arg `CategoryAssignmentService` and new `data()` signature.
- `ScopedProductGridComponent` updated to forward pagination params.
- Pre-existing test failures from tasks 011/012 fixed: `RelocationTest` updated for new catalog src dirs, `PackageScaffoldingTest` fixed by ordering `markommerce/criteria` in root `composer.json`, `OffsetPaginationStrategyTest::makeRequest` renamed to `makeOffsetPageRequest` to resolve global function collision.
