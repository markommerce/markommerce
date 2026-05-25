# Task 024: README for `markommerce/config`

**Status**: completed
**Depends on**: 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017, 018, 019, 020
**Retry count**: 0

## Description
Write the slim package README per the project's Package README Standards. Cover: what the package is (vs. `marko/config`), installation, the developer-facing API (typed proxy via `ConfigResolver::get(...)`), declaring configs (`#[Config]` + `#[Scoped]`), changing defaults via Marko Preferences, the CLI commands, and a brief on storage + driver requirement.

## Context
- Standards: `docs/DOCS-STANDARDS.md` for the slim README format
- Cross-link to the docs site page at `docs/src/content/docs/packages/config.md` (created/maintained by the post-implementation `doc-updater` agent — README only needs to link there)
- Open with the "this is not `marko/config`" callout to disambiguate
- Show one minimal end-to-end example: declare a `CatalogConfig`, register it, read it under a scope context
- Mention the codegen step (`config:generate`) and that it should run on `composer install`/CI

## Requirements (Test Descriptions)
- [x] `it has a README.md at the package root`
- [x] `it disambiguates the package from marko/config in the first paragraph`
- [x] `it shows a complete minimal usage example with declaration, registration, and resolved read`
- [x] `it documents the four CLI commands with one-line descriptions and example invocations`
- [x] `it points to the docs site for full reference`
- [x] `it lists the required driver package (markommerce/config-pgsql) under installation`

## Acceptance Criteria
- README conforms to the slim format from `docs/DOCS-STANDARDS.md`
- All code blocks are valid PHP
- Internal links target real docs paths
- Tests are validation tests over the markdown file (presence of required sections, etc.) — written as Pest tests in `tests/Unit/ReadmeTest.php` matching the same pattern other markommerce packages use, if any

## Implementation Notes
(Left blank — filled in by programmer)
