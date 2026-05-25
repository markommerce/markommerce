# Task 001: Author Package Standard Document

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Author `.claude/package-standard.md` as the single source of truth for markommerce's package structure. Document the required files per package type (marko-module vs library), `resources/` sub-conventions, naming rules, and the disambiguation between "Markommerce-layout DSL" and "Latte layout templates". Embed reference templates (LICENSE, .gitattributes, Pest.php skeleton) so other tasks can compare against them.

## Context

This document is referenced by every other task in the plan — the meta-tests assert that packages comply with what is written here.

- Related files:
  - `CLAUDE.md` — top-level project rules
  - `.claude/architecture.md` — module system, naming conventions, interface/driver split
  - `.claude/code-standards.md` — coding conventions to align with
  - `LICENSE` (repository root) — MIT text to copy
  - `packages/scope/.gitattributes`, `packages/scope-pgsql/.gitattributes` — reference `.gitattributes` to standardize on
  - `packages/scope/tests/Pest.php` — the existing identical empty Pest skeleton
- Patterns to follow: marko upstream uses LICENSE in all 80 packages, `.gitattributes` in all 80, and `module.php` in 68 of 80 (only when needed)

## Requirements (Test Descriptions)

This task produces a document, not code; "test descriptions" below are content sections the document MUST contain. Each section will be verified by a meta-test in a later task.

- [ ] `it documents the required files for a marko-module package (composer.json, README.md, LICENSE, .gitattributes, tests/, tests/Pest.php, src/)`
- [ ] `it documents the required files for a library package (composer.json, README.md, LICENSE, .gitattributes, src/) and clarifies that tests/ and module.php are not required`
- [ ] `it documents that module.php should exist only when the module has bindings, plugins, observers, or other registrations — not as an empty return []`
- [ ] `it embeds the canonical LICENSE text (MIT) as a fenced block that the FilePresenceTest will diff against`
- [ ] `it embeds the canonical .gitattributes content as a fenced block` — NOTE: the two existing reference files (`packages/scope/.gitattributes`, `packages/scope-pgsql/.gitattributes`) list `phpunit.xml.dist` as `export-ignore`, but no package ships that file. The standard should either (a) keep the line for forward-compat with PHPUnit users, or (b) drop it. Decide and document the rationale; the canonical block is whichever you pick.
- [ ] `it embeds the canonical Pest.php skeleton as a fenced block`
- [ ] `it documents that exception classes live in src/Exceptions/ (plural) — singular Exception/ is forbidden`
- [ ] `it documents that composer.json must have exactly one PSR-4 autoload root mapped to src/ (with one documented exception: packages shipping marko/database seeders may declare a second PSR-4 root mapped to Seed/) and exactly one autoload-dev PSR-4 root mapped to tests/`
- [ ] `it documents the Seed/ directory convention: marko's SeederDiscovery (marko/database/src/Seed/SeederDiscovery.php) globs vendor/*/*/Seed at fixed depth, so seeder classes MUST live in <package-root>/Seed/, NOT src/Seed/. The package's composer.json autoload block declares a secondary PSR-4 root for that namespace (e.g. "Markommerce\\Catalog\\Seed\\": "Seed/"). This is the canonical layout for any package that ships seeders. Cite the upstream discovery code as the source of truth.`
- [ ] `it documents the resources/ subconventions: css/, js/, views/ are the only allowed top-level children; .gitkeep is allowed only in directories that are otherwise empty`
- [ ] `it documents that packages shipping CSS under resources/css/ must expose them via the package.json "exports" map`
- [ ] `it documents that .generated/ subdirectories must not contain per-package .gitignore files (root .gitignore already excludes them)`
- [ ] `it documents the two distinct meanings of "layout" — markommerce-layout DSL at <pkg>/layout/ vs Latte template inheritance at <pkg>/resources/views/layout/ — and explains why both names coexist`
- [ ] `it documents that per-package CHANGELOG.md files are not maintained (matches marko upstream convention)`
- [ ] `it documents the Pest allow-plugins block required in every package's composer.json that uses Pest`
- [ ] `it documents that marko-module packages with tests/ must declare marko/testing in require-dev`

## Acceptance Criteria
- `.claude/package-standard.md` exists at the repository root under `.claude/`
- All content requirements above are satisfied
- The document explicitly cross-references CLAUDE.md and architecture.md so contributors have one entry point
- The document explains the rationale for each rule briefly (so future contributors can judge edge cases)
- Markdown is well-formed and renders cleanly

## Implementation Notes
(Left blank — filled in by programmer during implementation)
