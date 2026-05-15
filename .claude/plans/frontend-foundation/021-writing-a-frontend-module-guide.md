# Task 021: Write "Writing a Markommerce frontend module" guide

**Status**: completed
**Depends on**: 007, 008, 009, 011, 017
**Retry count**: 0

## Description

Author a step-by-step guide at `docs/src/content/docs/guides/writing-a-frontend-module.md` (or the equivalent location in the docs site's Guides section per `docs/DOCS-STANDARDS.md`) walking a module author through: 1) creating a new Markommerce package with the dual `composer.json` + `package.json` manifest, 2) declaring the `markommerce` block, 3) authoring a Lit Web Component with `protected` template methods, 4) registering it via `registerBase`, 5) extending an existing component via a functional mixin, 6) registering a hook for data transformation, 7) declaring a typed DOM event, 8) extending core types via declaration merging, 9) authoring component CSS using Markommerce semantic tokens and cascade layers, 10) testing components with Vitest, 11) documenting the module with a README and docs page.

## Context

- File: `docs/src/content/docs/guides/writing-a-frontend-module.md` (or whichever subdirectory `docs/DOCS-STANDARDS.md` prescribes for Guides).
- Tone: task-oriented, not reference. Reader knows the concepts; they want to ship a module.
- Each step has a code snippet pulled from the demo's actual code where possible (avoid drift between the guide and reality).
- Concludes with a "Where to look next" list pointing at `packages/frontend-demo/src/` as the canonical reference.
- The guide is the spec's listed Phase 1 deliverable: "Documentation: how to write a Markommerce frontend module."

## Requirements (Test Descriptions)

(Verified by docs Pest tests + manual review.)

- [ ] `the guide exists at the docs site Guides path documented in DOCS-STANDARDS.md`
- [ ] `the guide explains how to scaffold a new Markommerce package with both composer.json and package.json manifests`
- [ ] `the guide documents the markommerce block in package.json with all required and optional fields`
- [ ] `the guide includes a worked example of authoring a Lit component with protected template methods`
- [ ] `the guide includes a worked example of registering a base via registerBase`
- [ ] `the guide includes a worked example of authoring and registering a functional mixin via addMixin`
- [ ] `the guide includes a worked example of registering and consuming a hook via registerHook and runHook`
- [ ] `the guide includes a worked example of declaring a typed CustomEvent and extending MarkommerceEventMap`
- [ ] `the guide includes a worked example of extending core types via declare module declaration merging`
- [ ] `the guide explains the cascade layer order and how to write CSS that participates in it`
- [ ] `the guide shows how to write Vitest tests for a component and a mixin`
- [ ] `the guide includes a "where to look next" section linking to packages/frontend-demo as the canonical reference`
- [ ] `the guide passes the existing DocsStandards Pest tests`

## Acceptance Criteria

- File parses as valid Markdown / MDX (per the docs site's processor).
- All code blocks are valid TypeScript / PHP / JSON.
- Guide passes docs-related Pest tests.
- Pull-quote verification: every API mentioned in the guide is actually exported by the kernel.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
