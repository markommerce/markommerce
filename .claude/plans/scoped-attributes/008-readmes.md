# Task 008: READMEs for both packages + docs touch-up

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007
**Retry count**: 0

## Description
Write the `attribute-scope` and `catalog-attribute-scope` package READMEs per the Package README
Standard, reflecting only the shipped API.

## Context
- Standard: `.claude/package-standard.md` + Package README Standards in `.claude/code-standards.md`.
  Mirror `packages/catalog-scope/README.md` / `packages/config-scope/README.md` for tone/length.
- `attribute-scope/README.md`: purpose (scoped/translatable select-option labels); the
  `AttributeOptionScopedLabels` companion (`scoped_labels` column) + `ScopedOptionLabelResolver`
  (override → base label via the scope kernel). Note PHP 8.5 / no-final / strict-types.
- `catalog-attribute-scope/README.md`: purpose (scoped product attribute values); the
  `ProductScopedAttributeValues` companion (`scoped_attribute_values` column) + `ScopedProductAttributeAccessor`
  (setScoped/getScoped/resolve; Json via companion + global fallback, Column via the generic scope
  resolver); axes from `config['axes']`; values persist via `ProductRepository->save()`; global scope
  resolution reuses the `scope` kernel. Out of scope: faceting/search/SQL resolution (later phases).

## Requirements (Test Descriptions)
- [ ] `it documents the attribute-scope option-label scoping in its README`
- [ ] `it documents the catalog-attribute-scope scoped values and accessor in its README`

> Note: documentation-completeness checks — verify the READMEs exist and contain the required
> sections (file-content assertion per the project's README-task convention).

## Acceptance Criteria
- Both READMEs follow the standard sections/length; content matches the shipped API (no unbuilt features).

## Implementation Notes
(Left blank - filled in by programmer during implementation)
