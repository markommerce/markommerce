# Task 009: `markommerce/scope` README + CHANGELOG + Docs Page

**Status**: complete
**Depends on**: 008
**Retry count**: 0

## Description
Update the `markommerce/scope` README, create a `CHANGELOG.md`, and update the docs page at `docs/src/content/docs/packages/scope.md` to reflect the composite-overrides API. The README and docs page must cover: single-axis example, two-axis composite example, three-axis composite example, and a `walkAt` example.

## Context
- Files:
  - `packages/scope/README.md` — keep slim per the existing pattern (the `theme-blank` and other packages keep their READMEs short with a link to the docs page; mirror that).
  - `packages/scope/CHANGELOG.md` — NEW file. Follow Keep a Changelog format. First entry describes the breaking change.
  - `docs/src/content/docs/packages/scope.md` — full API + examples, mirrors the existing structure.
- Examples to include in the docs page:
  - **Single-axis**: `#[Scoped(axes: ['locale'])]` on `$name`; `setOverride(ScopeSignature::fromArray(['locale' => 'de']), 'Widget DE')`; `resolved()` walks `de.formal → de`.
  - **Two-axis composite**: `#[Scoped(axes: ['channel', 'locale'])]` on `$price`; show `setOverride` for `channel:b2b`, for `locale:es`, and for the composite `channel:b2b|locale:es`. Show the resolution priority: composite > `channel:b2b` > `locale:es` (case 3, 4, 5 from the brief).
  - **Three-axis composite**: `#[Scoped(axes: ['channel', 'locale', 'market'])]` on $price; show one full-composite override and one two-axis composite override. Reference case 12.
  - **`walkAt`**: explicit single-axis lookup ignoring `ScopeContext`. Mention that multi-axis `walkAt` throws.
- README must include:
  - Quick example with a two-axis composite (this is the headline change).
  - Cross-link to the docs page.
  - Brief mention of the breaking change with a pointer to CHANGELOG.md.
- CHANGELOG.md `## [Unreleased]` entry must say:
  - **BREAKING**: single-axis-only "first axis wins" resolution removed. Replaced with multi-axis composite overrides scored lexicographically by the attribute's declared axis priority.
  - **BREAKING**: `Markommerce\Scope\Scope` class removed. Use `Markommerce\Scope\Signature\ScopeSignature::fromArray([...])` (or `::fromString(...)`).
  - **BREAKING**: `ScopeResolver::setOverride`, `clearOverride`, `resolvedAt` now take `ScopeSignature` instead of `Scope`.
  - **BREAKING**: `ScopeWalker::walkAt` takes `ScopeSignature` (single-axis only — multi-axis throws `MultiAxisWalkAtNotSupportedException`).
  - **BREAKING**: `HasScopesInterface` parameter renamed `$scopeKey` → `$signature` (no behavior change but affects named-argument callers).
  - **BREAKING**: `ScopeSortRendererInterface` / `ScopeSortExpression` removed; replaced by `ScopedFieldRendererInterface` / `ScopedFieldExpression`. `ScopedOrderBy` and its factory now take `SignatureCandidateEnumerator` and `ScopedFieldRendererInterface` in their constructors instead of the old `ScopeSortRendererInterface`. (See scope-pgsql CHANGELOG for the renderer impl.)
  - **BREAKING / DATA**: stored override JSONB keys for single-axis overrides are unchanged (`"locale:es"`), but the resolution semantics have changed and the old single-axis-only fallthrough is gone. Pre-existing single-axis overrides will continue to resolve via the new walker, but **pre-release packages**: if you've stored data, dropping the `scopes` column and re-writing is the recommended path.
  - **Added**: `ScopeSignature` value object, `SignatureCandidateEnumerator`, `ScopeSignatureValidator`, `InvalidSignatureException`, `InvalidSignatureForAttributeException`, `MultiAxisWalkAtNotSupportedException`.

## Requirements (Test Descriptions)
- [x] `the README mentions ScopeSignature in a code example`
- [x] `the README mentions a two-axis composite example`
- [x] `the README links to the docs page at /docs/packages/scope`
- [x] `the CHANGELOG.md file exists and contains the breaking-change list`
- [x] `the CHANGELOG.md mentions removal of the Scope class`
- [x] `the CHANGELOG.md mentions removal of ScopeSortRendererInterface`
- [x] `the docs page contains a single-axis usage example`
- [x] `the docs page contains a two-axis composite usage example`
- [x] `the docs page contains a three-axis composite usage example`
- [x] `the docs page contains a walkAt example with a single-axis signature`
- [x] `the docs page does not import Markommerce\Scope\Scope anywhere`
- [x] `the docs page lists ScopeSignature in the API Reference table`

## Acceptance Criteria
- All requirements have passing tests (added to `packages/scope/tests/Unit/ReadmeTest.php`).
- Docs page renders correctly in the Starlight build (no broken links — verify `npm run build` in `docs/` if practical, otherwise visual inspection).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes

- Added `ScopeSignature::fromArray(array $axisValues): self` static factory method (alias for the constructor) so docs examples use the named factory pattern consistent with the task brief.
- Updated `packages/scope/README.md` with a two-axis composite example (`channel` + `locale`), a brief breaking-change note pointing to `CHANGELOG.md`, and a docs link using root-relative path `/docs/packages/scope/`.
- Created `packages/scope/CHANGELOG.md` following Keep a Changelog format with a full `## [Unreleased]` section listing all breaking changes and additions.
- Rewrote `docs/src/content/docs/packages/scope.md` to include single-axis, two-axis composite, three-axis composite, and `walkAt` usage sections, removed any reference to the old `Markommerce\Scope\Scope` class, and updated the API Reference table with `ScopeSignature`, `SignatureCandidateEnumerator`, `ScopeSignatureValidator`, and the new exceptions.
