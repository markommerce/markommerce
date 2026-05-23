# Plan: Relocate Layout Files Under `resources/views/layout/`

## Created
2026-05-23

## Status
completed

## Objective
Move layout definition and extension files from `{module}/layout/` to `{module}/resources/views/layout/` so the layout system conforms to the project-wide convention that view-side resources live under `resources/views/`. Update discovery, all consumer packages, tests, and documentation accordingly.

## Related Issues
none

## Discovery Notes

**Current state**
- Discovery scans `{module}/layout/` for layouts and `{module}/layout/extensions/` for extensions. Hardcoded in two places:
  - `packages/layout/src/Discovery/LayoutDiscovery.php:29`
  - `packages/layout/src/Middleware/CompileIfStaleMiddleware.php:93`
- Three packages currently ship layout files at the legacy path:
  - `packages/layout-demo/layout/` — `default.php`, `layout_demo.php`, `layout_demo_child.php`, `layout_demo_variant_featured.php`, and `extensions/layout_demo_extension.php`
  - `packages/catalog/layout/` — `category_show.php`
  - `packages/frontend-demo/layout/` — `demo.php`
- Discovery unit tests build temporary `{tmp}/layout/...` fixtures:
  - `packages/layout/tests/Unit/Discovery/LayoutDiscoveryTest.php`
  - `packages/layout/tests/Unit/Middleware/CompileIfStaleMiddlewareTest.php`
- Documentation files referencing the legacy path:
  - `docs/src/content/docs/packages/layout.md`
  - `docs/src/content/docs/packages/catalog.md`
  - `docs/src/content/docs/packages/layout-demo.md`
  - `docs/src/content/docs/packages/theme-blank/index.md` (one code-fence title at `:340`)
  - `docs/src/content/docs/guides/working-with-layouts.md`
  - `packages/layout-demo/README.md` (one comment line at `:16`)
  - `packages/layout/README.md` (verify only; currently no path literal)
- Path-hardcoded `require`/`file_exists` references in package tests (must be updated alongside the file moves):
  - `packages/layout-demo/tests/Unit/LayoutFileTest.php` (4 occurrences)
  - `packages/layout-demo/tests/Unit/ExtensionFileTest.php` (3 occurrences)
  - `packages/catalog/tests/Feature/CategoryControllerTest.php` (1 occurrence at `:296`)
  - `packages/catalog/tests/Feature/CategoryLayoutTest.php` (1 occurrence at `:64`)
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php` (2 occurrences at `:363` and `:374`)
- `docs/src/content/docs/packages/frontend-demo.md` was verified to contain no file-path references that need migrating (only route URLs).

**Decisions reached during clarification**
- New path is `resources/views/layout/` — **plural** `views`, matching the existing repo convention (`packages/{catalog,theme-blank,layout-demo,frontend-demo}/resources/views/` already exist).
- Extensions stay nested: `resources/views/layout/extensions/`.
- Clean break: no fallback scan of the old `{module}/layout/` path. This branch line has not been released, so there is no public contract to preserve.
- `Layout` and `LayoutExtension` remain separate types in this plan. A potential unification is out of scope and deferred.

**Out of scope**
- The Latte `theme-blank::layout/...` template namespace under `packages/theme-blank/resources/views/layout/` is unrelated to the PHP layout-definition system and is **not** touched by this plan.
- URL paths such as `/markommerce/_demo/layout/1` are runtime routes, not filesystem paths, and stay as-is.

## Scope

### In Scope
- Update `LayoutDiscovery` to scan `{module}/resources/views/layout/` and `{module}/resources/views/layout/extensions/`.
- Update `CompileIfStaleMiddleware` source-file collection to use the new paths.
- Update all corresponding unit tests in `packages/layout/`.
- Move layout/extension PHP files in `packages/layout-demo/`, `packages/catalog/`, and `packages/frontend-demo/` to the new paths.
- Delete now-empty `{module}/layout/` directories where applicable.
- Update path-sensitive tests in `packages/layout-demo/tests/`, `packages/catalog/tests/`, and `packages/frontend-demo/tests/` (file lists captured in Discovery Notes).
- Update all documentation pages (including `docs/.../packages/theme-blank/index.md`), code-fence title attributions, the `markommerce/layout` README, and the `markommerce/layout-demo` README to reflect the new path.

### Out of Scope
- Merging `Layout` and `LayoutExtension` types.
- Any change to the `theme-blank::layout/...` Latte template namespace.
- Any change to URL routing for the demo (`/markommerce/_demo/layout/{page}` stays unchanged).
- Backward-compatibility fallback for the old `{module}/layout/` path.

## Success Criteria
- [ ] `LayoutDiscovery` discovers layouts and extensions only from `{module}/resources/views/layout/[extensions/]`.
- [ ] `CompileIfStaleMiddleware` stat-walks the new paths only.
- [ ] All three consumer packages (`layout-demo`, `catalog`, `frontend-demo`) have their layout files at the new path; the old `{module}/layout/` directories are removed.
- [ ] `composer test` passes for the whole monorepo.
- [ ] `./vendor/bin/phpstan analyse` passes.
- [ ] `./vendor/bin/phpcs` passes.
- [ ] All affected docs (including `docs/.../packages/theme-blank/index.md`), the `markommerce/layout` README, and the `markommerce/layout-demo` README reference the new path.
- [ ] All path-hardcoded test references in consumer packages (`layout-demo`, `catalog`, `frontend-demo`) are updated to the new path.
- [ ] Doc-content tests (`DocsApiReferenceTest`, `DocsGuideTest`) still pass.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Update `LayoutDiscovery` to scan `resources/views/layout/` | - | completed |
| 002 | Update `CompileIfStaleMiddleware` to stat-walk `resources/views/layout/` | - | completed |
| 003 | Migrate `layout-demo` package layout files and tests | 001, 002 | completed |
| 004 | Migrate `catalog` package layout file | 001, 002 | completed |
| 005 | Migrate `frontend-demo` package layout file | 001, 002 | completed |
| 006 | Update documentation and `markommerce/layout` README | 003, 004, 005 | completed |

## Architecture Notes
- The hardcoded path strings live in two production classes (`LayoutDiscovery`, `CompileIfStaleMiddleware`). Both must be updated together; otherwise the dev-mode middleware will miss freshly-edited files. Keep tasks 001 and 002 atomic and independently parallelizable.
- Discovery test fixtures use `mkdir(... '/layout/extensions', 0755, true)` — the recursive `true` flag handles the deeper path with no extra work.
- The `extensions/` subdirectory name stays the same; only the parent prefix changes.
- **File-type conflation in `resources/views/layout/`**: after this migration the directory will hold both PHP layout-definition files (this plan's domain) and Latte content templates (e.g. `theme-blank/resources/views/layout/1column.latte`, `frontend-demo/resources/views/layout/base.latte`). `LayoutDiscovery` and `CompileIfStaleMiddleware` both use `glob('*.php')`, which isolates the two file types — `.latte` files are silently ignored, no functional bug. This is intentional but the worker should be aware that the new directory mixes concerns; a future task may revisit the layout (a deeper subdirectory) if conflicts arise.

## Risks & Mitigations
- **Risk:** A consumer package's tests pass on its own but the full suite breaks because discovery still points at the old path. **Mitigation:** Tasks 003/004/005 explicitly depend on 001 + 002, so the discovery change lands before any package's files move.
- **Risk:** Stale documentation pointing at the old path confuses future contributors. **Mitigation:** Task 006 sweeps all docs and the README in one pass and depends on the moves so prose and reality match.
- **Risk:** Old `{module}/layout/` directories left behind on disk continue to confuse readers. **Mitigation:** Each migration task explicitly removes the empty parent directory after the move.
