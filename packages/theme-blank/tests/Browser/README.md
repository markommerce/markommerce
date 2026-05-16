# Browser Tests (Playwright)

This directory contains Playwright-based browser tests for the `theme-blank` package, starting with a Cumulative Layout Shift (CLS) smoke test.

## How to Run

From the **repo root** on the **host machine**:

```bash
# Install Playwright + browser binaries (first time only)
npm install
npx playwright install chromium

# Run all CLS / browser tests
npm run test:cls
```

> **CI note**: In CI pipelines, use `npx playwright install --with-deps chromium` to also install the OS-level dependencies required by Chromium.

### Running inside Docker (Alpine-based container)

The Playwright-bundled Chromium requires glibc which is not available in Alpine Linux. Install system Chromium and use the `test:cls:docker` script instead:

```bash
# Inside the Docker node container (one-time setup, as root)
apk add --no-cache chromium

# Run tests pointing at the system Chromium
npm run test:cls:docker
```

## Phase 1: CLS Smoke Test

`cls.spec.ts` loads a static HTML fixture (`fixtures/base-page.html`) that mirrors the `1column.latte` layout structure, observes `layout-shift` PerformanceObserver entries, and asserts the cumulative CLS score is exactly `0`.

No web server is needed — the test uses `page.setContent()` to inject the fixture HTML directly. No JavaScript is loaded in Phase 1.

## Phase 2+: Adding a Fixture for a New Component

When a new `mk-*` component ships, follow this pattern to add browser CLS coverage:

1. **Create a new fixture HTML** under `fixtures/`:
   ```
   fixtures/mk-button-page.html
   ```
   - The fixture must be a self-contained HTML file.
   - Inline the relevant CSS from `packages/theme-blank/resources/css/` (tokens, base, layouts, and any component-specific CSS).
   - Replace any `@media (--mk-breakpoint-*)` custom media queries with their resolved values (e.g., `@media (min-width: 768px)`), because the fixture is executed directly in the browser without PostCSS processing.
   - Include the `<mk-button>` element (or whichever component is under test) in the `<main class="mk-layout-1col">` area.

2. **Create a new spec file**:
   ```
   mk-button.spec.ts
   ```
   - Follow the same `PerformanceObserver` pattern as `cls.spec.ts`.
   - Load the fixture via `readFileSync` + `page.setContent()`.
   - Assert `cls` is `0` after `waitForTimeout(500)`.

3. **Run the new spec**:
   ```bash
   npm run test:cls -- --grep "mk-button"
   # or run all browser tests:
   npm run test:cls
   ```

## Config

The Playwright configuration lives at the **repo root**: `playwright.config.ts`.

- `testDir`: `./packages/theme-blank/tests/Browser`
- Browser: Chromium only (Phase 1 restriction — add more browsers in Phase 2+ as needed)
- Viewport: 1280×720, headless
- No web server: tests use `page.setContent()` with inlined HTML
