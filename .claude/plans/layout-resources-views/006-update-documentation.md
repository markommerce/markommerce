# Task 006: Update Documentation and Package README

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Sweep every documentation page and the `markommerce/layout` package README, replacing the legacy `{module}/layout/{name}.php` path conventions with `{module}/resources/views/layout/{name}.php` (and `extensions/` accordingly). Update every Markdown `title=` code-fence attribution that points at a relocated file so the path matches reality.

## Context

Files to sweep:
- `docs/src/content/docs/packages/layout.md`
  - Prose at `:26` ("`{module}/layout/{name}.php`") → new path
  - Code-fence title at `:28` (`packages/catalog/layout/category_show.php`) → new path
  - Prose at `:305` ("`{module}/layout/extensions/{name}.php`") → new path
- `docs/src/content/docs/packages/catalog.md`
  - Prose at `:137` (`packages/catalog/layout/category_show.php`)
  - Prose at `:141` ("declared in `layout/category_show.php`")
  - Code-fence title at `:143`
- `docs/src/content/docs/packages/layout-demo.md`
  - Code-fence titles at `:59`, `:187`, `:380`, `:412`, `:437` (all pointing at `packages/layout-demo/layout/*` or `layout/extensions/*`)
  - Prose at `:57` ("defined in `layout/layout_demo.php`")
  - Prose at `:378` ("`layout/default.php` demonstrates …")
  - Prose at `:410` ("`layout/layout_demo_child.php` demonstrates …")
  - Prose at `:435` ("`layout/layout_demo_variant_featured.php` is …")
- `docs/src/content/docs/guides/working-with-layouts.md`
  - Prose at `:12` ("lives at `{module}/layout/{name}.php`")
  - Code-fence titles at `:14`, `:429`, `:465`, `:549`, `:591`
  - Prose at `:587` ("Extension files live at `{module}/layout/extensions/{name}.php`")
- `docs/src/content/docs/packages/frontend-demo.md` — verified: only references the route URL `/markommerce/_demo/theme-blank`, no file-path migration needed.
- `docs/src/content/docs/packages/theme-blank/index.md`
  - Code-fence title at `:340` (`packages/catalog/layout/category_show.php`) → new path. This file is in the PHP Layout Classes section and uses the catalog layout file as the canonical example of `extends:` referencing a theme-blank layout class.
- `packages/layout/README.md` — currently the README has no explicit path reference (the Quick Example shows code, not a directory), but verify by grep; if any `{module}/layout/` literal is added later, update it.
- `packages/layout-demo/README.md`
  - Code comment at `:16` (`// layout/layout_demo.php`) → change to `// resources/views/layout/layout_demo.php`. The route URL on `:53` (`/markommerce/_demo/layout/{id}`) must NOT be changed.

URL-style references such as `/markommerce/_demo/layout/{page}` (a route in `layout-demo.md`) are runtime URLs and must **not** be changed.

The Latte-template namespace `theme-blank::layout/1column` referenced in docs is unrelated to the PHP layout-definition path and stays unchanged.

## Requirements (Test Descriptions)
- [x] `it shows the new resources/views/layout path in the layout package documentation`
- [x] `it shows the new resources/views/layout path in the catalog package documentation`
- [x] `it shows the new resources/views/layout path in the layout-demo package documentation`
- [x] `it shows the new resources/views/layout path in the theme-blank package documentation`
- [x] `it shows the new resources/views/layout path in the working-with-layouts guide`
- [x] `it shows the new resources/views/layout/extensions path for extension files in documentation`
- [x] `it shows the new resources/views/layout path in the layout-demo package README`
- [x] `no documentation file references the legacy {module}/layout/{name}.php convention` (asserted by absence of substring matches across docs/ and packages/*/README.md)
- [x] `route URLs like /markommerce/_demo/layout/{page} are preserved unchanged in the demo documentation`
- [x] `DocsApiReferenceTest and DocsGuideTest still pass`

## Acceptance Criteria
- All listed docs files, plus `packages/layout/README.md` and `packages/layout-demo/README.md`, reflect the new path everywhere a file reference exists.
- No `grep -rn "packages/[a-z-]\+/layout/[a-z_]\+\.php" docs/ packages/*/README.md` returns a hit (route URLs and Latte namespaces remain).
- Existing doc-content tests under `packages/layout/tests/Unit/Docs*Test.php` continue to pass.
- `composer test` passes across the monorepo.

## Implementation Notes
