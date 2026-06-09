# Task 010: Redirect to default on invalid sort (catalog-storefront)

**Status**: completed
**Depends on**: 004, 008
**Retry count**: 0

## Description
When a category page is requested with an unknown/disabled `?sort=` key, the storefront currently 500s (the resolver throws `InvalidPaginationConfigException::forInvalidSort`, uncaught). Make the storefront instead issue a 302 redirect to the canonical category URL with the invalid `sort` param removed, so the default order applies. This is important because the v1 config rename means old links like `?sort=name`, `?sort=sku`, or `?sort=price` (no longer registered keys) now hard-fail. Genuine misconfigurations (keyset-incompatible sort, bad config values) must STAY loud — only an unknown *requested* sort key is redirected.

## Context
- The exception is thrown when `PaginationOptionsResolver::resolve()` validates the requested `?sort=` against the registry / `enabledSorts` gate (task 004). It currently throws `InvalidPaginationConfigException::forInvalidSort(...)`. The keyset-incompatibility case (`forKeysetIncompatibleSort`) and config-value errors must remain uncaught (server misconfig, not user input) → stay loud.
- **Need a catchable, narrow type.** Introduce `UnknownSortRequestedException` (in `packages/catalog/src/Exceptions/`) extending `InvalidPaginationConfigException` (so existing `catch (InvalidPaginationConfigException)` sites and the loud-error contract are preserved). Move the `forInvalidSort` request-sort path to throw this subclass (e.g. rename/relocate the factory to `UnknownSortRequestedException::forRequestedKey(string $sort, string $allowedKeys)`, keeping message/context/suggestion). Do NOT change `forKeysetIncompatibleSort` — it stays a plain `InvalidPaginationConfigException`.
- **Where to catch — `packages/catalog-storefront/src/Controller/CategoryController.php`:**
  - `show()` already calls `$this->paginationOptionsResolver->resolve(...)` inside a `try { } catch (PageDepthExceededException) { return Response::html('', 410); }`. Add a `catch (UnknownSortRequestedException)` that returns `Response::redirect($url, 302)`.
  - `pageFragment()` (the `/catalog/category/{id}/page` load-more/infinite endpoint) does the same resolve — add the same catch + redirect there.
  - Build `$url` as the category path with the invalid `sort` removed: keep `page` (if > 1) and `size` (if > 0) query params, drop `sort` entirely (default order then applies). Reuse the host/scheme/`http_build_query` approach already in `buildCanonicalUrl()`; a small private helper `redirectUrlWithoutSort(Request $request, int $id, int $page, int $size): string` is fine.
- **Why this works cleanly (verified):** `MarkommerceLayoutMiddleware::handle()` runs the controller via `$next($request)` and short-circuits on any non-2xx response (`statusCode() < 200 || >= 300` → returns it as-is, lines ~82-84) BEFORE rendering the layout tree. So a 302 from `show()` means `ProductGridComponent::data()` (which would otherwise re-resolve and re-throw) is never invoked. No double-throw.
- `Response::redirect(string $url, int $statusCode = 302): self` already exists (`marko/packages/routing/src/Http/Response.php`). Use 302 (the resource is fine; we're normalizing a user-supplied param — not a permanent move).

## Requirements (Test Descriptions)
- [x] `it redirects to the category url without the sort param when an unknown sort is requested`
- [x] `it issues a 302 status for an unknown requested sort`
- [x] `it preserves the page and size params while dropping the invalid sort on redirect`
- [x] `it renders normally without redirecting for a valid registered sort`
- [x] `it does not redirect and stays loud when a keyset-incompatible sort is requested under the keyset strategy`
- [x] `it redirects the page fragment endpoint to default on an unknown sort`

## Acceptance Criteria
- Unknown/disabled `?sort=` → 302 to the category URL with `sort` removed (default order applies); valid sorts unaffected.
- Keyset-incompatibility and other `InvalidPaginationConfigException` cases are NOT redirected (remain loud / propagate).
- `UnknownSortRequestedException extends InvalidPaginationConfigException`; resolver throws it for the request-sort case; message/context/suggestion preserved.
- Run `packages/catalog` + `packages/catalog-storefront` tests + `./vendor/bin/phpstan analyse` (use `php -d memory_limit=2G ./vendor/bin/phpstan analyse` — the env's default 128M child limit OOMs) — green/type-clean. Update any resolver test that asserted the old `forInvalidSort` type.

## Implementation Notes

- Created `UnknownSortRequestedException extends InvalidPaginationConfigException` with `forRequestedKey(string $sort, string $allowedKeys): self` factory in `packages/catalog/src/Exceptions/`.
- Updated `PaginationOptionsResolver::resolveRequestedSortOrder()` to throw `UnknownSortRequestedException::forRequestedKey(...)` instead of `InvalidPaginationConfigException::forInvalidSort(...)`.
- Removed dead `forInvalidSort()` factory from `InvalidPaginationConfigException`.
- Added `UnknownSortRequestedException` import and catch block in both `CategoryController::show()` and `CategoryController::pageFragment()`, returning `Response::redirect($url, 302)`.
- Added private `redirectUrlWithoutSort(Request $request, int $id, int $page, int $size): string` helper that builds the redirect URL preserving `page` (if > 1) and `size` (if > 0) while dropping `sort`.
- Keyset-incompatible sort continues to propagate as uncaught `InvalidPaginationConfigException`.
