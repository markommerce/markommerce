# Task 031: Layout-demo exercises default, inherits, and dynamic handles

**Status**: complete
**Depends on**: 030
**Retry count**: 0

## Description
Extend `markommerce/layout-demo` so every new handle feature has a worked example a developer can curl and inspect. Add a default handle, a child gallery handle that inherits from `layout_demo`, and a `HandleProvider` that switches in extra placements based on a query parameter. The demo serves as the canonical reference for the docs guide in task 032.

## Context
- New files under `packages/layout-demo/`:
  - `layout/default.php` — declares `Layout(handle: 'default', …)` with a sitewide notice prepended to the `content` slot. Demonstrates default-handle merge.
  - `layout/layout_demo_child.php` — declares `Layout(handle: …, inherits: 'layout_demo', …)` adding one new placement and using `Remove` to drop one inherited placement.
  - `src/Handle/GalleryVariantHandleProvider.php` — implements `HandleProvider`, returns `['layout_demo.variant.featured']` when `Source::query('variant')` resolves to `'featured'`, otherwise empty.
  - `layout/layout_demo_variant_featured.php` — a layout for the variant handle with an extra "featured callout" placement.
- Update `packages/layout-demo/layout/layout_demo.php` to declare the new `handleProviders:` entry pointing at `GalleryVariantHandleProvider`.
- Update the controller route to accept the variant query param via `Source::query('variant', '', 'string')`.
- After implementation: run `vendor/bin/marko layout:compile` and curl each of: base page, child handle page, base page with `?variant=featured`. Verify each renders the expected differences.

## Requirements (Test Descriptions)
- [x] `it renders the default-handle notice on the base demo page`
- [x] `it renders the inherits-child handle with a removed parent placement`
- [x] `it renders the dynamic featured-callout when variant=featured is on the query string`
- [x] `it does not render the featured-callout when variant is absent`
- [x] `it raises a clear error if layout:compile fails on the new files`

## Failure-Mode Coverage
Failure-mode integration tests (each new exception fires end-to-end with location + suggestion) live in the per-feature tasks they belong to:
- `CircularInheritanceException`, `UnknownParentHandleException`, `DuplicateContextTokenException` — task 026 tests
- `DefaultHandleConflictException` — task 027 tests
- `InvalidLayoutFileException` for non-`HandleProvider` class, `InvalidSourceTypeException` for forbidden Source kinds — task 028 tests
- `UnknownDynamicHandleException` — task 029 tests
- `DynamicHandleConflictException`, `ChainedHandleProviderException` — task 030 tests

Task 031's job is happy-path showcase. Do not duplicate failure-mode tests here.

## Acceptance Criteria
- All requirements have passing tests
- All three live URLs return correct HTML when curled
- No regressions in the existing 9-operation demo

## Implementation Notes
- Created `layout/default.php` with `handle: 'default'` that places `SitewideNoticeComponent` (marker class: `layout-demo-sitewide-notice`) into the `content` slot.
- Created `layout/layout_demo_child.php` inheriting from the base controller handle, using `Remove('layout_demo.gallery_footer')`.
- Created `src/Handle/GalleryVariantHandleProvider.php` implementing `HandleProvider` with `#[ProvidesHandles]`, returns `['layout_demo.variant.featured']` when `variant === 'featured'`.
- Created `layout/layout_demo_variant_featured.php` for the dynamic handle; it uses a `Remove('default.sitewide_notice')` operation so the default-handle's placement doesn't collide with the base tree at runtime (compile-time validation catches duplicate names across base and dynamic trees).
- Updated `layout_demo.php` to add `ProvideHandle` using `Source::query('variant', '', 'string')`.
- Tests live in `packages/layout-demo/tests/Feature/HandleFeatureTest.php`.
- The `Request` constructor takes query params as the second argument (`query:`), not via `QUERY_STRING` in the server array.
