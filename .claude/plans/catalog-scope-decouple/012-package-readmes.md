# Task 012: READMEs for new packages and catalog README update

**Status**: completed
**Depends on**: 002, 007, 008, 009
**Retry count**: 0

## Description
Create README files for the three new packages following the project's Package README Standards (`docs/DOCS-STANDARDS.md`), and update catalog's README to reflect that scope-coupling is now optional and provided by `catalog-scope` rather than being part of catalog itself.

Each README must follow the standard sections: installation, quick example, key concepts, link to docs site for full reference.

## Context
- Reference for shape:
  - `packages/scope/README.md` (recently updated in P1 — good model)
  - `packages/config-pgsql/README.md` (driver/bridge pattern)
- Files to write:
  - `packages/catalog-scope/README.md` (new)
  - `packages/locale/README.md` (new)
  - `packages/catalog-locale/README.md` (new)
  - `packages/catalog/README.md` (update existing — likely a few lines about optional scope bridge)
- DOCS-STANDARDS reference: see `docs/DOCS-STANDARDS.md` (READMEs are slim — code + a one-paragraph hook + a link to the docs site)

## Requirements (Test Descriptions)
- [x] `it ships a README.md in packages/catalog-scope with installation, quick example, and docs link`
- [x] `it ships a README.md in packages/locale with installation, axis-config example, and docs link`
- [x] `it ships a README.md in packages/catalog-locale with installation, boot-bridge example, and docs link`
- [x] `it updates packages/catalog/README.md to note that scope is no longer required and link to catalog-scope`
- [x] `it passes ReadmeTest-style assertions (where applicable, mirroring the scaffolding-test pattern other packages use)`
- [x] `it follows DOCS-STANDARDS.md sectioning conventions for each new README`

## Acceptance Criteria
- All requirements have passing tests.
- Four README files updated/created.
- READMEs are slim and link to the docs site for full reference.
- Code follows project standards (README content adheres to DOCS-STANDARDS).

## Implementation Notes
- Created `packages/catalog-scope/tests/Unit/ReadmeTest.php` — verifies installation, quick example, and docs link
- Created `packages/locale/tests/ReadmeTest.php` — verifies installation, axis-config example, and docs link
- Created `packages/catalog-locale/tests/ReadmeTest.php` — verifies installation, boot-bridge example, and docs link
- Created `packages/catalog-scope/tests/Unit/DocStandardsTest.php` — verifies DOCS-STANDARDS conventions (no Overview heading, correct sections) for all three new READMEs
- Updated `packages/catalog/tests/Unit/ReadmeTest.php` — added test for catalog-scope mention and "optional" language
- Updated all four README files to follow DOCS-STANDARDS slim format: title+one-liner, Installation, Quick Example, Documentation link
- catalog README now notes scope support is optional and links to catalog-scope
