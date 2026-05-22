# Task 019: Package README

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017, 018
**Retry count**: 0

## Description
Write the `markommerce/layout` package README following the project's Package README Standards. It must accurately reflect the system as actually built across all prior tasks.

## Context
- Follow the Package README Standards in `.claude/code-standards.md` and `docs/DOCS-STANDARDS.md`.
- Mirror the slim style of `marko/layout/README.md` and other package READMEs: a one-line description, install command, a focused Quick Example, and a link to full docs.
- The Quick Example should show a real layout file (`Layout` + `Place` + a `Slot::repeat`), a placement-agnostic component with a typed DTO, and one extension operation — enough to convey the model without reproducing the full reference.
- Briefly state what makes this package different from `marko/layout`: placement-agnostic components, typed compile-validated trees, iteration slots, the extension vocabulary.
- Mention the `layout:compile` command and the `var/cache/markommerce/layouts.php` artifact.
- This is the final task — depends on all others so the README describes what was actually built. Reconcile against the real final API; if any task changed a name or signature from this plan, the README reflects the real one.

## Requirements (Test Descriptions)
- [x] `it has a README with a package description`
- [x] `it documents the installation command`
- [x] `it includes a layout-definition quick example`
- [x] `it documents the layout:compile command`

## Acceptance Criteria
- README follows the project's Package README Standards
- All code examples are valid against the final API
- Requirements verified (a structure/Markdown-content test or doc-lint as the project does it elsewhere)

## Implementation Notes
- Created `packages/layout/README.md` following the slim Package README Format from DOCS-STANDARDS.md.
- Created `packages/layout/tests/Unit/ReadmeTest.php` with 4 tests verifying the README exists and contains required content.
- Test path uses `__DIR__ . '/../../README.md'` (two levels up from `tests/Unit` to `packages/layout`).
- Quick example shows `Layout` + `Slot::repeat` + `Place` + `Source::iterated` + `LayoutExtension` with `InsertAfter`.
- README describes placement-agnostic model, typed DTOs via `ExtensibleData`, compile-validated trees, and `layout:compile` command with artifact path.
