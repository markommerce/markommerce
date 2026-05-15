# Task 020: markommerce/frontend-demo README + docs page

\*\*Status\*\*: completed
**Depends on**: 015, 016, 017, 018
**Retry count**: 0

## Description

Author the slim `packages/frontend-demo/README.md` and the docs page at `docs/src/content/docs/packages/frontend-demo.md`. Explain what the demo proves, how to install (composer require-dev + npm), how to enable the route (`frontend_demo.enabled = true`), how to access it (`/markommerce/_demo`), what to look for (counter increments, suffix mixin label, DOM event in console), and how to use the demo as living documentation when writing a new frontend module. Link to the "Writing a Markommerce frontend module" guide (task 021).

## Context

- README mirrors the slim pattern used by other packages.
- Docs page follows `docs/DOCS-STANDARDS.md` for the Packages section.
- This package's role is "reference + smoke test"; the docs page must say that explicitly so consumers don't confuse it for a production component library.
- Include a code snippet showing the registration pattern (`registerBase` + `addMixin`) so readers see the canonical wiring at a glance.

## Requirements (Test Descriptions)

(Verified by docs Pest tests + doc-updater pipeline.)

- [ ] `the package has a README.md pointing at the docs site`
- [ ] `the docs page exists at docs/src/content/docs/packages/frontend-demo.md`
- [ ] `the docs page intro paragraph states this package is a reference and dev-only`
- [ ] `the docs page has an Installation section showing composer require-dev and npm install`
- [ ] `the docs page has a Configuration section documenting frontend_demo.enabled`
- [ ] `the docs page has a Usage section explaining how to visit /markommerce/_demo`
- [ ] `the docs page documents the full end-to-end consumer-app integration (add markommerce/frontend-demo as a path repo in the consuming app's composer.json, set frontend_demo.enabled=true, export MARKOMMERCE_CONSUMER_PUBLIC, run npm run dev) since markommerce itself has no server entry point`
- [ ] `the docs page shows the registerBase + addMixin snippet for the counter and the LabelSuffixMixin`
- [ ] `the docs page links to the writing-a-markommerce-frontend-module guide`
- [ ] `the docs page passes the existing DocsStandards Pest tests`

## Acceptance Criteria

- Both files pass docs-related Pest tests.
- All code snippets render the actual demo source verbatim.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
