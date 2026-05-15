# Task 019: markommerce/frontend README + docs page

**Status**: completed
**Depends on**: 007, 008, 009, 010, 011, 012, 013, 014
**Retry count**: 0

## Description

Author the slim `packages/frontend/README.md` (intro + install + one tiny example + link to docs site) and the full docs page at `docs/src/content/docs/packages/frontend.md` covering: what the package provides, installation (Composer + npm), configuration (`config/vite.php` keys), the component registry API (`registerBase`, `addMixin`, `defineAllComponents`, introspection helpers), the hooks registry API, the DOM events helper, the CSS foundation files (`layers.css`, `tokens.css`), the Vite plugin and how to install it in a consumer app's `vite.config.ts`, the Latte `{vite()}` function, and a "Related Packages" section linking to `markommerce/frontend-demo` and `marko/vite`.

## Context

- Follow `docs/DOCS-STANDARDS.md` (Packages section conventions): intro paragraph, no `## Overview` heading, sections in order — Installation, Configuration, Usage, API Reference, Related Packages.
- README mirrors the slim pattern used by `packages/catalog/README.md` and `packages/money/README.md`.
- Docs page targets the doc-updater agent's expectations.
- Code examples must use real, runnable snippets (the registry/hooks/Latte calls authored in tasks 007/008/013).
- Include a "Configuration" section with the default `config/vite.php` keys and what each one does.

## Requirements (Test Descriptions)

(Verified by the existing docs tests under `tests/Unit/Docs/`, which the doc-updater agent and pipeline already cover.)

- [ ] `the package has a README.md following the slim pattern (intro, install, one example, link to docs site)`
- [ ] `the docs page exists at docs/src/content/docs/packages/frontend.md`
- [ ] `the docs page intro paragraph one-liners what markommerce/frontend provides`
- [ ] `the docs page has an Installation section with both composer require and npm install commands`
- [ ] `the docs page has a Configuration section listing every config/vite.php key with defaults and meaning`
- [ ] `the docs page documents the registerBase, addMixin, and defineAllComponents API with TypeScript signatures`
- [ ] `the docs page documents the registerHook and runHook API with TypeScript signatures`
- [ ] `the docs page documents the DOM events helper and the MarkommerceEventMap extension pattern`
- [ ] `the docs page documents how to install the markommerceModuleScanner Vite plugin in a consumer vite.config.ts`
- [ ] `the docs page documents the Latte {vite()} function with a usage example`
- [ ] `the docs page explains the MarkommerceLatteEngineFactory Preference pattern so module authors who want to register their own Latte extensions know to subclass it`
- [ ] `the docs page documents the MARKOMMERCE_CONSUMER_PUBLIC env var and the consumer-app integration story (why the build output goes into the consuming app's public/build/)`
- [ ] `the docs page has a Related Packages section linking to markommerce/frontend-demo and marko/vite`
- [ ] `the docs page passes the existing DocsStandards Pest tests`

## Acceptance Criteria

- Both files pass markdownlint (if configured) and the docs-related Pest tests at `tests/Unit/Docs/`.
- All code snippets in the docs match the actual exported API verbatim.
- The "Related Packages" section uses correct link targets.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
