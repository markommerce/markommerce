# Task 018: SEO — per-page canonical, view=all, 410 depth cap

**Status**: pending
**Depends on**: 013, 017
**Retry count**: 0

## Description
Add the SEO behaviors that make paginated listings safe for crawlers: a self-referencing canonical per page (page 2 → page 2, not page 1), an optional `?view=all` rendering gated by `viewAllThreshold`, and a `410 Gone` response on the main category route when the requested page exceeds `maxPageDepth`.

## Context
- **Canonical injection has no existing wiring path — this must be solved explicitly.** Verified: `base.latte` exposes `{block head-extra}{/block}` in `<head>`, but `1column.latte` (the chain used by `category_show`) does NOT override `head-extra`, and the layout DSL renders components only into the `content` slot — a content-slot component CANNOT write into the base `<head>` block. So a `ProductGridComponent` DTO field alone will not place a `<link rel="canonical">` in the head. Choose ONE concrete mechanism and implement it end-to-end:
  - **(Preferred) Response header:** the `CategoryController::show` action sets a `Link: <url>; rel="canonical"` response HTTP header. This is crawler-valid, needs no template-chain changes, and the controller already has the `Request` (path + query) to build the absolute URL. NOTE: the layout middleware returns its own `Response::html($html)` (status 200) and DISCARDS the controller body — verify whether controller-set HEADERS survive the middleware re-wrap; if they do not, this mechanism requires a small middleware/controller adjustment (call it out as a sub-step) or fall back to the template mechanism below.
  - **(Alternative) Head slot in the layout chain:** add a `head-extra` block override in `1column.latte` (or a dedicated head template) that renders a canonical value threaded from the category layout context/DTO. This is more invasive (touches theme-blank) and must be justified.
- Files: `packages/catalog-storefront/src/Controller/CategoryController.php` (status codes + canonical header OR canonical value provided to a head template), and `ProductGridComponent`/data for the `viewAll` flag and the canonical URL string. Confirm which mechanism the middleware actually supports before committing (see the header caveat above) — this is a real verification step, not a formality.
- Per-page self canonical: canonical href includes the current `?page=N` (and normalized size/sort), NOT the bare category URL.
- `?view=all`: when `viewAllThreshold > 0` and the category's total products `<= viewAllThreshold`, render all products on one page; paginated pages then canonicalize to the view-all URL. When disabled (threshold 0) the param is ignored.
- Depth cap: the main `#[Get('/catalog/category/{id}')]` route returns `Response::html('', 410)` when `page > maxPageDepth` (catching/using the SAME depth signal defined in Task 011 — `PageDepthExceededException` or equivalent). Because the layout middleware honors any non-2xx controller response as a short-circuit (verified), returning 410 from the controller correctly bypasses layout rendering and emits Gone. The controller must resolve the depth via `PaginationOptionsResolver` (or read `maxPageDepth` from config) BEFORE returning 200.

## Requirements (Test Descriptions)
- [x] `it sets a self-referencing canonical pointing at the current page`
- [x] `it normalizes size and sort params in the canonical url`
- [x] `it renders all products on one page when under the view-all threshold`
- [x] `it canonicalizes paginated pages to the view-all url when view-all is active`
- [x] `it ignores the view-all param when the threshold is disabled`
- [x] `it returns 410 gone when the requested page exceeds the max depth`

## Acceptance Criteria
- Canonical/meta and status-code behavior verified via PHP feature tests.
- All requirements have passing tests.

## Implementation Notes

### Canonical mechanism: Response header (preferred path taken)

Verified that `MarkommerceLayoutMiddleware` discarded controller response headers (line 89: `return Response::html($html)`). Made a minimal fix: the middleware now merges non-content-type headers from the controller response into the final rendered `Response`. This preserves `Link: <url>; rel="canonical"` headers through the layout pipeline.

### Controller changes (`CategoryController::show`)

- Added `Request $request` parameter (router auto-injects it by type-hint)
- Added optional `ConfigResolverInterface $configResolver` and `CategoryAssignmentService $categoryAssignmentService` constructor args for view-all threshold checking and product count queries
- Reads `page`, `size`, `sort` from the request query
- Resolves pagination options via `PaginationOptionsResolver` — catches `PageDepthExceededException` → returns 410
- Sets `Link: <url>; rel="canonical"` header on the 200 response

### Canonical normalization

Default params (page=1, default size, default sort) are omitted from the canonical URL. The resolver is called with `null, null, null` to get defaults, then compared against the resolved options.

### View-all

When `viewAllThreshold > 0` in config AND (a) `?view=all` is in the request OR (b) the category's total products ≤ threshold (queried via `CategoryAssignmentService`), the canonical URL is `?view=all`.
