# Task 022: README + Docs Updates (Final Task)

**Status**: completed
**Depends on**: 021
**Retry count**: 0

## Description
Final task in the plan. Write the full `theme-blank-demo` README, add a corresponding docs page at `docs/src/content/docs/packages/theme-blank-demo.md`, update the `theme-blank` docs index to link to the demo, and adjust the `frontend-demo` README to mention the split (so future readers understand why the primitives demo lives elsewhere).

Per project convention, every plan that creates a new package must have a final README task that depends on all the other tasks so the README reflects what was actually built.

## Context
- Files to CREATE/REWRITE:
  - `packages/theme-blank-demo/README.md` (full README replacing the placeholder from task 017)
  - `docs/src/content/docs/packages/theme-blank-demo.md`
- Files to MODIFY:
  - `packages/frontend-demo/README.md` — add a "Related demo" note pointing at `theme-blank-demo`
  - `docs/src/content/docs/packages/theme-blank/index.md` — add a "Demo page" callout linking to `/docs/packages/theme-blank-demo/` (drop into the existing `## Components` intro)
  - `docs/src/content/docs/packages/frontend-demo.md` (if it exists — check first) — narrow the scope description to "kernel reference" only
- Reference templates:
  - `packages/frontend-demo/README.md` for the README shape
  - `docs/DOCS-STANDARDS.md` for the docs page conventions (intro + Installation + Usage + API Reference sections)
- Reference: `.claude/code-standards.md` § Package README Standards

## `theme-blank-demo` README Shape

Mirror the existing `frontend-demo/README.md` but customise:

```markdown
# markommerce/theme-blank-demo

Showcase module that renders the full set of `mk-*` primitives and form controls on a developer demo page (`/markommerce/_demo/theme-blank`). Use it to visually verify theme-blank changes, smoke-test new primitives, or evaluate styling overrides before shipping.

## Installation

Install as a dev-only dependency:

```bash
composer require-dev markommerce/theme-blank-demo
```

Install the npm package:

```bash
npm install @markommerce/theme-blank-demo
```

Enable the route in your config (it ships disabled by default):

```php
// config/theme_blank_demo.php
return ['enabled' => true];
```

Then visit `/markommerce/_demo/theme-blank`.

## What's on the page

- **Layout primitives** — `mk-stack`, `mk-cluster`, `mk-grid`, `mk-container`, `mk-sidebar`, `mk-switcher`, `mk-cover`, `mk-divider`
- **Typography primitives** — `mk-heading`, `mk-text`, `mk-link`, `mk-badge`
- **Form controls** — `mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-form`
- A complete working `mk-form` wired with `mk-submit` and `mk-invalid` event listeners (open the browser console to see the payloads).

## Documentation

Full usage, screenshots, and per-component docs: [markommerce/theme-blank-demo](https://markommerce.dev/docs/packages/theme-blank-demo/)
```

## `docs/src/content/docs/packages/theme-blank-demo.md` Shape

```yaml
---
title: markommerce/theme-blank-demo
description: Developer demo page for the markommerce/theme-blank primitives and form controls.
---
```

Body sections (per DOCS-STANDARDS.md):

1. **Intro paragraph** — one sentence on what it does, one on when to use it.
2. **## Installation** — composer + npm, plus the `theme_blank_demo.enabled = true` config tweak.
3. **## Usage** — show the URL, screenshots if available (skip for now, leave as a `:::note[Screenshots]` placeholder).
4. **## What's on the page** — three subsections (Layout primitives, Typography primitives, Form Controls) each listing the tags.
5. **## Architecture** — explains the split from `frontend-demo`: this package is the *theme* demo, `frontend-demo` is the *kernel* demo.
6. **## Related** — link to `markommerce/theme-blank`, `markommerce/frontend-demo`, `markommerce/frontend`.

## Updates to Existing Docs

### `packages/frontend-demo/README.md`

After the "Quick Example" section, add:

```markdown
## Related demo

The `mk-*` primitives and form controls now live in [`markommerce/theme-blank-demo`](https://markommerce.dev/docs/packages/theme-blank-demo/), rendered at `/markommerce/_demo/theme-blank`. This package (`frontend-demo`) keeps its original role: a smoke test for the `@markommerce/frontend` kernel (custom-element registry, mixin chain, ViteExtension wiring).
```

### `docs/src/content/docs/packages/theme-blank/index.md`

In the `## Components` section intro paragraph (before the `### Layout primitives` heading), append:

```markdown
A live demo page rendering all primitives and form controls is available at `/markommerce/_demo/theme-blank` via the [`markommerce/theme-blank-demo`](/docs/packages/theme-blank-demo/) package.
```

### `docs/src/content/docs/packages/frontend-demo.md` (if exists)

Open the file. If the intro paragraph claims it demos the primitives, narrow it to "kernel reference + counter smoke test". Add a "See also" pointing at theme-blank-demo.

## Requirements (Test Descriptions)

Tests in `packages/theme-blank-demo/tests/Unit/ReadmeTest.php` (new file) for the README + docs assertions:

- [ ] `it README.md exists at packages/theme-blank-demo/README.md`
- [ ] `it README.md contains the package name markommerce/theme-blank-demo`
- [ ] `it README.md documents the /markommerce/_demo/theme-blank route URL`
- [ ] `it README.md describes how to enable the route via config/theme_blank_demo.php`
- [ ] `it README.md links to the markommerce.dev docs site`
- [ ] `it docs page exists at docs/src/content/docs/packages/theme-blank-demo.md`
- [ ] `it docs page has frontmatter title and description`
- [ ] `it docs page has ## Installation, ## Usage, ## What's on the page, ## Architecture, ## Related sections`

Tests in `packages/frontend-demo/tests/Unit/ReadmeTest.php` (extend existing or create):

- [ ] `it the frontend-demo README mentions theme-blank-demo and the /markommerce/_demo/theme-blank route`

Test in `packages/theme-blank/resources/js/docs.test.ts` (extend existing):

- [ ] `it the theme-blank docs index links to /docs/packages/theme-blank-demo/`

## Acceptance Criteria
- All requirements have passing tests
- README follows project Package README Standards (see `.claude/code-standards.md`)
- Docs page passes whatever lint the docs site has (none currently, but the file must be valid Markdown with the frontmatter at the top)
- No broken internal links
- `composer test` and `npm test` both pass

## Implementation Notes
(Left blank — filled in by programmer during implementation)
