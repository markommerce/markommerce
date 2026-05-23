# Devil's Advocate Review: layout-resources-views

## Critical (Must fix before building)

### C1. Task 004 misses two path-hardcoded test files in the catalog package
The Discovery Notes only flag `LayoutFileTest.php` and `ExtensionFileTest.php` (in `layout-demo`). For `catalog`, task 004 explicitly says "no path-hardcoded require statements pointing at `layout/category_show.php` in its tests (verified via grep)." Grepping for `'/layout/'` proves otherwise:

- `packages/catalog/tests/Feature/CategoryControllerTest.php:296` — `$layoutPath = dirname(__DIR__, 2) . '/layout/category_show.php';`
- `packages/catalog/tests/Feature/CategoryLayoutTest.php:64` — `$path = dirname(__DIR__, 2) . '/layout/category_show.php';`

If the file is moved without updating these tests, both feature suites will break at `require`.

**Fix:** Update task 004 to explicitly list and update both files. The new path expression must become `dirname(__DIR__, 2) . '/resources/views/layout/category_show.php'`.

### C2. Task 005 misses two path-hardcoded test files in the frontend-demo package
Task 005 states: "No path-hardcoded `require` statements were found in `packages/frontend-demo/tests/` (verified via grep)." This is wrong:

- `packages/frontend-demo/tests/Feature/DemoControllerTest.php:363` — `$layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';`
- `packages/frontend-demo/tests/Feature/DemoControllerTest.php:374` — `$layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';`

If the file is moved without updating these tests, `DemoControllerTest` will break.

**Fix:** Update task 005 to explicitly list these two lines and update them to `dirname(__DIR__, 2) . '/resources/views/layout/demo.php'`.

### C3. Task 006 misses `docs/src/content/docs/packages/theme-blank/index.md`
The plan's Discovery Notes don't list `theme-blank/index.md`, but it contains a relevant code-fence at `:340`:

```
docs/src/content/docs/packages/theme-blank/index.md:340: ```php title="packages/catalog/layout/category_show.php"
```

The associated acceptance criterion ("`grep -rn 'packages/[a-z-]\+/layout/' docs/` returns no documentation hit") will fail unless this file is included in the sweep.

**Fix:** Add `docs/src/content/docs/packages/theme-blank/index.md:340` to the file list in task 006.

### C4. Task 006 misses `packages/layout-demo/README.md`
A grep for `layout/` in package READMEs shows `packages/layout-demo/README.md:16` contains a PHP comment with the legacy path:

```
// layout/layout_demo.php
```

Task 006 lists `packages/layout/README.md` only (and notes that it has no path reference). The `layout-demo` README is not mentioned at all.

**Fix:** Add `packages/layout-demo/README.md:16` to task 006's file list.

## Important (Should fix before building)

### I1. Latte/PHP directory conflation is a real but unstated outcome of this migration
`packages/theme-blank/resources/views/layout/` already exists and ships `.latte` content templates (`1column.latte`, `2columns-left.latte`, etc.). `packages/frontend-demo/resources/views/layout/base.latte` also exists. After this migration, the same directory name `resources/views/layout/` is used for two unrelated purposes:

- PHP layout-definition files (this plan's domain)
- Latte content templates referenced via `theme-blank::layout/1column`

Discovery uses `glob('*.php')` so `.latte` files are silently skipped — no functional bug. But it is a contract worth pinning down in the success criteria so a future contributor doesn't drop a `.latte` file with `.php` extension by mistake, or vice versa, and silently break discovery.

**Fix:** Add a note to `_plan.md` Architecture Notes explicitly recording that `glob('*.php')` is what isolates the two file types in the same directory, and confirming this is intentional.

### I2. `_plan.md` Discovery Notes list `frontend-demo.md` as "(if it references file paths)" — verified, but it doesn't
The Discovery Notes leave that bullet ambiguous. A grep confirms `docs/src/content/docs/packages/frontend-demo.md` only references the route URL `/markommerce/_demo/theme-blank`. There is no file-path reference to migrate there.

**Fix:** Remove the ambiguous bullet from `_plan.md` Discovery Notes to keep the plan accurate, or pin it as "not affected".

### I3. Task 003 wording ("three `require` statements + one `file_exists` check") is technically accurate but ambiguous
`LayoutFileTest.php` has, on inspection:
- Line 13: `$layoutPath = __DIR__ . '/../../layout/layout_demo.php';` (path string for the `file_exists` check)
- Lines 24, 31, 39: three `require` statements

That's 4 lines to update, not 3. Worker may interpret "three require + one file_exists" as "update three lines and check that the file_exists call works" — missing the line-13 path-string update. Make the count explicit.

**Fix:** Rephrase to "four hardcoded `__DIR__ . '/../../layout/...'` path literals across LayoutFileTest (one path string for `file_exists` + three `require`s)".

### I4. Documentation tests do not verify path migration
The `DocsApiReferenceTest` and `DocsGuideTest` listed in success criteria do not test for the absence of the legacy `{module}/layout/` substring. So they will continue to pass with stale paths in docs.

**Fix:** Add an acceptance check in task 006 that explicitly grep-verifies no documentation file references the legacy `{module}/layout/{name}.php` convention (already noted in acceptance criteria, but bump it to a test description so a worker writes it as a test rather than a manual grep).

### I5. Discovery tests' "negative case" requirements are not verifiable as written
Task 001 and 002 each include a requirement like "it does not discover layouts placed in the legacy `{module}/layout` directory" / "it does not recompile based on files in the legacy `{module}/layout` directory". Writing this test requires creating files in the legacy path within a temp module and asserting they are ignored. The acceptance criteria are fine, but the test setup will need a fixture helper distinct from the new-path helper. This is implicit — flag it explicitly so the worker doesn't try to reuse a single helper.

**Fix:** Add to task 001/002 Implementation Notes: "The negative-case test must construct a legacy `{module}/layout/` directory in the temp module *in addition to* the new `resources/views/layout/` directory, and assert the legacy file is not returned."

## Minor (Nice to address)

### M1. Task 006 acceptance grep is over-eager
The grep `packages/[a-z-]\+/layout/` will match the layout namespace pattern in `theme-blank::layout/1column` if anyone writes it as `packages/theme-blank/layout/1column` somewhere. It will also match `packages/layout/` (the package directory itself). Refining the grep to `packages/[a-z-]\+/layout/[a-z_]+\.php` is more precise.

### M2. Risks section in `_plan.md` doesn't mention the test path-hardcoded gotcha
The Risks section talks about test ordering, doc drift, and empty directories — but not "consumer-package tests hardcode the path." That was the largest single class of misses in this review. Worth surfacing as a risk for future similar migrations.

### M3. Order: task 006 could safely run in parallel with 003-005
Task 006 currently depends on 003, 004, 005. Strictly speaking documentation only references file paths; nothing in the docs sweep depends on the file actually being moved. The doc edits could land at any time after the path is decided. This dependency adds latency but doesn't cause problems — leave as-is unless parallelism matters.

## Questions for the Team

### Q1. Should the architectural conflation of `.php` and `.latte` files in one directory be addressed?
After this migration, `resources/views/layout/` will contain two kinds of files that have nothing to do with each other:
- PHP layout-definition files (returned by `require`, must be `instanceof Layout`)
- Latte content templates (rendered by the theme system)

Consider whether to introduce a deeper subdirectory (e.g. `resources/views/layout/definitions/`) to keep them apart, or accept the conflation. The plan explicitly puts this out of scope, but a brief team decision is warranted.

### Q2. Is there an upgrade path for downstream consumers we should communicate?
The plan declares a "clean break" with no fallback scan. Confirm that no released branch of `markommerce/layout` is in third-party use yet (the Discovery Notes assert this; double-check).
