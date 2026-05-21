# Task 013: Package README

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012
**Retry count**: 0

## Description
Write `packages/catalog/README.md` documenting the catalog package: what it provides, installation, and a quick-start example covering products, categories, assignment, the storefront route, and the seeder. This task is last so the README reflects what was actually built.

## Context
- Create `packages/catalog/README.md`.
- Model the structure and length on `packages/scope/README.md` — a slim README: a one-line summary, an installation block, a concise quick example, and a link to the full docs page (`/docs/packages/catalog/`).
- Follow the content rules in `docs/DOCS-STANDARDS.md` and any Package README Standards in `.claude/code-standards.md`.
- If sibling packages ship a `tests/Unit/ReadmeTest.php` that asserts README structure (see `packages/scope/tests/Unit/ReadmeTest.php`), add an equivalent so the README stays in sync.
- Cover, briefly: the `Product` and `Category` entities (globally-unique SKU, locale-scoped name/description), assigning products to categories, the `GET /catalog/category/{id}` storefront route, and running the `catalog` seeder.
- Note that the seeder writes `locale:de` / `locale:fr` overrides which only resolve once those locales are registered in `config/scope.php`.
- Do NOT create the docs-site page (`docs/src/content/docs/packages/catalog.md`) — the `doc-updater` agent handles that in the post-implementation pipeline.

## Requirements (Test Descriptions)
- [x] `it the catalog README documents the package name and a one-line summary`
- [x] `it the catalog README includes an installation section`
- [x] `it the catalog README documents the Product and Category entities`
- [x] `it the catalog README documents the storefront category route`
- [x] `it the catalog README documents the catalog seeder`

## Acceptance Criteria
- All requirements have passing tests
- README follows the project documentation standards
- README accurately reflects the implemented package

## Implementation Notes
- Created `packages/catalog/README.md` following the slim format of `packages/scope/README.md`: one-line summary, installation block, quick example, docs link.
- README covers: `Product` (globally-unique SKU, locale-scoped name/description), `Category` (locale-scoped name/description), `CategoryAssignmentService.assign()`, the `GET /catalog/category/{id}` storefront route, and the `catalog` seeder with `locale:de` / `locale:fr` override note.
- Created `packages/catalog/tests/Unit/ReadmeTest.php` with 5 tests matching the required test descriptions exactly.
