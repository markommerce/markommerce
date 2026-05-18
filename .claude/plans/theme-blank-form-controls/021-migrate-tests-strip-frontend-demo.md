# Task 021: Migrate Tests + Strip `frontend-demo`

**Status**: completed
**Depends on**: 019, 020
**Retry count**: 0

## Description
With `theme-blank-demo` now wired up, remove the primitives + form controls sections from `packages/frontend-demo/resources/views/counter.latte` (restoring it to the original counter-only state) and move the corresponding tests from `frontend-demo`'s `DemoControllerTest.php` to `theme-blank-demo`'s `ThemeBlankDemoControllerTest.php` (re-pointing them at `/markommerce/_demo/theme-blank`). This is the actual cutover that flips the demo page split.

## Context
- Files to MODIFY:
  - `packages/frontend-demo/resources/views/counter.latte` — strip everything *except* the `<markommerce-counter>` line. Final state must match the original Phase 1 counter demo (1 line).
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php` — delete the Phase 2 + Phase 3 test cases listed below (the primitives + form controls ones).
- Files to MODIFY/EXTEND (new file from task 019):
  - `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` — append the migrated tests, all pointing at `/markommerce/_demo/theme-blank` and using the `themeBlankDemoTestBuildRouter` helper added in task 019.

## Tests to Move From `frontend-demo/DemoControllerTest.php`

All these test cases currently live in `frontend-demo/tests/Feature/DemoControllerTest.php` and assert against the `/markommerce/_demo` route. They are DELETED from `frontend-demo/DemoControllerTest.php` and REPLACED in `theme-blank-demo/ThemeBlankDemoControllerTest.php` by the consolidated tests listed in the Requirements section below (NOT a one-to-one move). The Requirements section is the authoritative list of what the migrated test file should contain. The list immediately below is only the inventory of what is being stripped from `frontend-demo` — it informs the deletion, not the new test shape:

1. `the /markommerce/_demo route renders a Layout Primitives heading` — rename to `the /markommerce/_demo/theme-blank route renders a Layout Primitives heading`
2. `the /markommerce/_demo route renders a Typography Primitives heading` — same rename pattern
3. `the rendered page contains at least one mk-stack element` — rename: `the /markommerce/_demo/theme-blank rendered page contains at least one mk-stack element`
4. `the rendered page contains at least one mk-cluster element` — same pattern
5. `the rendered page contains at least one mk-grid element`
6. `the rendered page contains at least one mk-container element`
7. `the rendered page contains at least one mk-sidebar element`
8. `the rendered page contains at least one mk-switcher element`
9. `the rendered page contains at least one mk-cover element`
10. `the rendered page contains at least one mk-divider element`
11. `the rendered page contains at least one mk-heading element`
12. `the rendered page contains at least one mk-text element`
13. `the rendered page contains at least one mk-link element`
14. `the rendered page contains at least one mk-badge element`
15. `it renders the Form Controls section heading in the demo view`
16. `it the rendered page contains at least one <mk-button> element`
17. `it the rendered page contains at least one <mk-input> element`
18. `it the rendered page contains at least one <mk-textarea> element`
19. `it the rendered page contains at least one <mk-select> element`
20. `it the rendered page contains at least one <mk-checkbox> element`
21. `it the rendered page contains at least one <mk-radio> element`
22. `it the rendered page contains at least one <mk-switch> element`
23. `it the rendered page contains at least one <mk-field> element`
24. `it the rendered page contains at least one <mk-fieldset> element`
25. `it the rendered page contains at least one <mk-form> element`
26. `it renders a complete mk-form example with at least one mk-field child`
27. `the original counter demo continues to render on the same page` — DELETE entirely (the split means the counter and the primitives no longer share a page, which is the point — this test described the *old* behavior and is now obsolete).

After the move, `frontend-demo/DemoControllerTest.php` should retain ONLY its original Phase 1 tests:
- the 200/404 enabled/disabled tests
- the `<markommerce-counter>` body assertion
- the Vite head-tag presence assertion
- the layout / counter component / vite-import / config-injection / naming tests
- the `the demo main.ts imports open-props/style.css...` test
- the `it loads the @markommerce/frontend cascade layers and @markommerce/theme-blank tokens CSS...` test

## `counter.latte` Final State

After the strip, the file must contain ONLY:

```latte
<markommerce-counter start-value="0" suffix=" clicks"></markommerce-counter>
```

(Single line, plus trailing newline — exactly as the file existed before task 015 appended the primitives section.)

The file-content assertion must therefore check:
- The file contains `<markommerce-counter`
- The file does NOT contain any `<mk-` substring (this is the key test that detects regression if any primitive or form-control tag accidentally remains)
- The file does NOT contain `<section class="demo-primitives"` or `<section class="demo-form-controls"`

## Requirements (Test Descriptions)

Tests in `packages/frontend-demo/tests/Feature/DemoControllerTest.php`:

- [ ] `it counter.latte renders only the markommerce-counter element and no longer contains primitives or form controls (file-content assertion)`
- [ ] `it the /markommerce/_demo response body no longer contains any <mk- element (verified by HTTP request against the live route)`

Tests in `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` (covering the migrated assertions):

- [ ] `it the /markommerce/_demo/theme-blank route renders a Layout Primitives heading`
- [ ] `it the /markommerce/_demo/theme-blank route renders a Typography Primitives heading`
- [ ] `it the /markommerce/_demo/theme-blank route renders a Form Controls heading`
- [ ] `it the rendered page contains every Phase 2 primitive tag (mk-stack, mk-cluster, mk-grid, mk-container, mk-sidebar, mk-switcher, mk-cover, mk-divider, mk-heading, mk-text, mk-link, mk-badge) — single test asserting each tag's presence`
- [ ] `it the rendered page contains every Phase 3 form control tag (mk-button, mk-input, mk-textarea, mk-select, mk-checkbox, mk-radio, mk-switch, mk-field, mk-fieldset, mk-form) — single test asserting each tag's presence`
- [ ] `it renders a complete mk-form example with at least one mk-field child`

Note: the per-tag tests are consolidated into two parametrised `it` cases (one per phase) to keep the test file from ballooning to 1500+ lines. Each test loops over its tag array and `expect()`s every tag in the response body. The original frontend-demo file had each tag as a separate `it`, which the devil's advocate flagged in task 015 — collapsing them now.

## Acceptance Criteria
- All requirements have passing tests
- `frontend-demo` test suite still passes (with the deleted tests removed and the two new file-content tests added)
- `theme-blank-demo` test suite passes
- `composer test` (whole repo) exits 0
- `counter.latte` is a single non-empty line (`<markommerce-counter>...</markommerce-counter>`)
- The `/markommerce/_demo` route, when enabled, still returns 200 and the body contains `<markommerce-counter` but contains NO `<mk-` tag

## Implementation Notes
(Left blank — filled in by programmer during implementation)
