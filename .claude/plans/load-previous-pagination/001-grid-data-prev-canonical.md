# Task 001: ProductGridData/Component — previousPageUrl + canonicalPageUrl

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add backward + canonical URLs to the product grid data so the frontend can load earlier pages and show the correct address-bar URL. `ProductGridComponent` computes `previousPageUrl` (fragment endpoint URL for the previous page) and `canonicalPageUrl` (the real full-page URL for the current page).

## Context
- Files: `packages/catalog-storefront/src/Data/ProductGridData.php`, `packages/catalog-storefront/src/Component/ProductGridComponent.php`, and `packages/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php`.
- **CRITICAL — `ScopedProductGridComponent` does NOT just mirror a signature; it RE-CONSTRUCTS `ProductGridData` field-by-field** (see its `data()`: it calls `parent::data()` then builds a brand-new `ProductGridData` copying every named arg). It is a `#[Preference(replaces: ProductGridComponent::class)]`, so when the scope module is installed it is the ACTIVE component. If it does not forward the two new fields, they default to `null` and the feature is silently dead in any scoped install. Task 001 MUST add `previousPageUrl: $data->previousPageUrl` and `canonicalPageUrl: $data->canonicalPageUrl` to that re-construction. This is not optional.
- READ `ProductGridComponent::data()` — it already builds `nextPageUrl` as the FRAGMENT endpoint URL (`/catalog/category/{id}/page?page=N+1`, preserving non-default size/sort) inside the `RandomAccessPageInterface` branch, and has `$id = $category->id`. Mirror that exactly for the new URLs.
- `previousPageUrl`: set ONLY when the page is `RandomAccessPageInterface` AND `currentPage > 1`; value = fragment endpoint URL for `currentPage - 1`: `sprintf('/catalog/category/%d/page?%s', $id, http_build_query($params))` with `page => currentPage-1` (+ size/sort if non-default). Otherwise null.
- `canonicalPageUrl`: the CURRENT page's full-page (non-fragment) URL: `sprintf('/catalog/category/%d?%s', $id, http_build_query($params))` with `page => currentPage` (+ size/sort if non-default). Set whenever random-access (so the frontend always has the entry page's canonical for scroll-spy). For non-random-access pages it may be null.
- Add both as `?string` params on `ProductGridData` (readonly DTO, still `ExtensibleData`); keep existing fields/order stable where possible (append new params before `extensions`).
- Standards: `declare(strict_types=1)`, readonly DTO, narrowest types.

## Requirements (Test Descriptions)
- [x] `it leaves previousPageUrl null on the first page`
- [x] `it sets previousPageUrl to the previous page fragment url when currentPage is greater than one`
- [x] `it preserves non-default size and sort in previousPageUrl`
- [x] `it sets canonicalPageUrl to the full page url for the current page (not the fragment endpoint)`
- [x] `it leaves previousPageUrl null for a non-random-access page`
- [x] `it forwards previousPageUrl and canonicalPageUrl through the scoped component` (add to the catalog-storefront-scope test suite: assert the scoped `data()` output carries both fields when given a random-access page > 1)

## Acceptance Criteria
- `ProductGridData` exposes `previousPageUrl` + `canonicalPageUrl`; `ProductGridComponent` populates them per the rules above.
- `ScopedProductGridComponent` forwards BOTH new fields in its `ProductGridData` re-construction (verified by a scope test); its existing tests + the whole suite stay green.
- All requirements have passing tests; PHPStan level 8 clean.

## Implementation Notes
