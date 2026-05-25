# Task 010: PackageJsonExportsTest + Add Catalog CSS Exports

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/PackageJsonExportsTest.php` asserting that every `packages/*/` containing any `resources/css/*.css` file declares those CSS files in `package.json`'s `exports` map. Add the missing `exports` block to `packages/catalog/package.json` to bring the test green.

## Context

Initial RED state — `catalog` ships `resources/css/components/product-card.css` but has no `exports` map. Compare `theme-blank/package.json`:
```json
"exports": {
    ".": "./resources/js/index.ts",
    "./css/tokens.css": "./resources/css/tokens.css",
    "./css/base.css": "./resources/css/base.css",
    "./css/layouts.css": "./resources/css/layouts.css",
    "./css/components/*.css": "./resources/css/components/*.css"
}
```

For catalog, the analog exports block:
```json
"exports": {
    ".": "./resources/js/index.ts",
    "./css/components/*.css": "./resources/css/components/*.css"
}
```

Packages without any CSS in `resources/css/` are not required to have an exports map.

Packages without a `package.json` at all (e.g., layout-demo, config, scope) are exempt — they don't ship JS or CSS.

- Related files:
  - `packages/catalog/package.json` — needs exports
  - `packages/theme-blank/package.json`, `packages/frontend/package.json` — reference for canonical shape
  - All `packages/*/resources/css/` directories
- Patterns to follow: theme-blank's exports map

## Requirements (Test Descriptions)

- [x] `it asserts every package that has resources/css/*.css files also has a package.json`
- [x] `it asserts every package that ships CSS exposes those files via package.json exports`
- [x] `it asserts catalog/package.json specifically exports ./css/components/*.css`

## Acceptance Criteria
- `tests/PackageStandard/PackageJsonExportsTest.php` exists and follows Pest 4 syntax
- `packages/catalog/package.json` has an `exports` map covering its CSS components
- All other packages already-compliant remain compliant
- `composer test` passes
- No regressions in other tests

## Implementation Notes
- `frontend-demo` also had CSS (`resources/css/components/counter.css`) without an exports map, so it needed `exports` added alongside `catalog`.
- `frontend` has CSS at root level (`resources/css/layers.css`) but the glob pattern `resources/css/**/*.css` does not match it (no subdirectory), so it was already compliant.
- Requirement 1 passed immediately because all packages with CSS already had `package.json`.
