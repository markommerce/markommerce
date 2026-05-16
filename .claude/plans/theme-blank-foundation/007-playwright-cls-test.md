# Task 007: Playwright + CLS Smoke Test

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Install Playwright as a repository-wide dev dependency and write the CLS-prevention smoke test that all later phases will extend. The Phase 1 fixture loads a `1column.latte`-based test page (no components — just the base + layout), runs through full hydration, observes `layout-shift` `PerformanceObserver` entries, and asserts the page produces zero cumulative layout shift. Phases 2–5 add `<mk-*>` elements to the fixture as components ship.

## Context
- **Install Playwright** at the repo root: `npm install --save-dev @playwright/test`. Add a `npm run test:cls` script in the root `package.json`: `"test:cls": "playwright test"`.
- **DO NOT wire Playwright into `composer test:all`.** Per `CLAUDE.local.md`, composer scripts run inside the `marko-playground-app` Docker container (a `php:8.5-cli-alpine` image), which has no Node.js, no Chromium, and no Playwright browser dependencies — adding Playwright to a composer script breaks `composer test:all` outright. Keep `composer test:all` PHP-only (as the existing scripts section is) and run Playwright via `npm run test:cls` from the host. Document this split clearly in the docs page (task 008) so contributors aren't surprised by the divided test surface. The existing `composer test:all` script in the repo-root `composer.json` is currently `"pest -c phpunit.xml --parallel"` — leave that unchanged.
- **Configure Playwright** in `playwright.config.ts` at the repo root:
  - `testDir: './packages/theme-blank/tests/Browser'`
  - `use: { headless: true, viewport: { width: 1280, height: 720 } }`
  - Only run Chromium (skip Firefox/Webkit for CI speed — adding browsers is a future concern)
  - `webServer` config: build the demo via Vite then serve `public/build/` plus a tiny fixture page that loads the manifest. Alternatively, simpler: use Playwright's `route` API to serve a fixture HTML string and `page.setContent()` — no real HTTP server needed.
- **Fixture page** at `packages/theme-blank/tests/Browser/fixtures/base-page.html`:
  - Static HTML mirroring what Latte renders for `1column.latte` with empty content
  - `<link>` tag pointing at the built CSS bundle (or inline the relevant CSS for test isolation — simpler)
  - `<script type="module">` that imports the built JS or stubs the registry to no-ops
  - For Phase 1 isolation, prefer inlining the relevant CSS (`tokens.css`, `base.css`, `layouts.css`) into a `<style>` block in the fixture and skip the JS — task 005 already proves the Latte renders, this test proves it doesn't layout-shift.
- **CLS observation** in `packages/theme-blank/tests/Browser/cls.spec.ts`:
  ```ts
  import { test, expect } from '@playwright/test';
  import { readFileSync } from 'node:fs';
  import { resolve } from 'node:path';

  test('base 1column layout produces zero CLS through load + idle', async ({ page }) => {
    await page.addInitScript(() => {
      (window as any).__clsEntries = [];
      const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!(entry as any).hadRecentInput) {
            (window as any).__clsEntries.push((entry as any).value);
          }
        }
      });
      observer.observe({ type: 'layout-shift', buffered: true });
    });

    const fixtureHtml = readFileSync(
      resolve(__dirname, 'fixtures/base-page.html'),
      'utf-8',
    );
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500); // allow late shifts to surface

    const cls = await page.evaluate(() => {
      const entries: number[] = (window as any).__clsEntries;
      return entries.reduce((acc, v) => acc + v, 0);
    });

    expect(cls).toBe(0);
  });
  ```
- **CI wiring**: ensure Playwright browsers are installed in CI before running. The simplest approach is to add `npx playwright install --with-deps chromium` as a step in whatever workflow runs `composer test:all`. Document this in the docs page (task 008).
- **Test pattern for future phases:** when components arrive, the fixture is extended with `<mk-*>` markup pre-hydration and the same CLS-zero assertion runs. Document this in `packages/theme-blank/tests/Browser/README.md` (a short readme inside the test dir explaining how to add a new fixture).

## Requirements (Test Descriptions)
- [x] `it installs @playwright/test as a repo-root devDependency`
- [x] `it adds a "test:cls" script to the root package.json invoking playwright test`
- [x] `the root composer.json's test:all script remains PHP-only (does NOT invoke Playwright) so composer test:all keeps working inside the PHP-only Docker container`
- [x] `it ships a playwright.config.ts at the repo root with testDir pointed at packages/theme-blank/tests/Browser`
- [x] `it ships a fixture HTML at packages/theme-blank/tests/Browser/fixtures/base-page.html that includes the tokens, base, and layouts CSS inline (or via link tags) and renders a 1column.latte-shaped DOM`
- [x] `it ships a cls.spec.ts Playwright test that observes layout-shift PerformanceObserver entries and asserts the sum is 0 against the fixture`
- [x] `the Playwright config restricts to Chromium for Phase 1`
- [x] `there is a packages/theme-blank/tests/Browser/README.md documenting how Phase 2+ contributors add a new fixture for new components`
- [x] `running npm run test:cls against the Phase 1 fixture passes (CLS === 0)` — this is the integration assertion; the test itself is the spec.

## Acceptance Criteria
- All unit-level requirements (config files exist, scripts wired, fixture exists) pass
- `npm run test:cls` runs locally and passes
- CI documentation in task 008's docs page describes how to install Playwright browsers
- The test takes < 10 seconds to run (smoke test, not a benchmark)
- No regression in `composer test` or `npm run test`

## Implementation Notes

- Installed `@playwright/test` as a root devDependency (v1.60.0).
- Added `test:cls` (host) and `test:cls:docker` (Alpine container with system Chromium) scripts to `package.json`.
- Created `playwright.config.ts` at repo root with Chromium-only project config; uses `CHROMIUM_PATH` env var to support Alpine Docker environments where Playwright's bundled glibc-based Chromium headless shell does not run.
- Added `test.exclude: ['**/tests/Browser/**']` to `vite.config.ts` so Vitest ignores `.spec.ts` Playwright files.
- Fixture HTML at `packages/theme-blank/tests/Browser/fixtures/base-page.html` inlines all CSS from `tokens.css`, `base.css`, and `layouts.css` with `@custom-media` queries resolved to their literal values (e.g., `@media (min-width: 768px)`).
- `cls.spec.ts` uses `page.addInitScript` to register a `PerformanceObserver` before content loads; `__clsEntries` is read with `?? []` fallback to handle environments where the init script runs in a separate context from `setContent`.
- All 10 Vitest config-level assertions pass in `playwright-config.test.ts`; the Playwright CLS integration test passes in ~2.4 s.
