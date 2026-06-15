# Task 007: README (catalog-attribute) + attribute README touch-up

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006
**Retry count**: 0

## Description
Write the `catalog-attribute` package README per the Package README Standard, and add a brief note
to the `attribute` README for the new generic `AttributeValueAccessorInterface` + entity-class
registry introduced this phase. Must reflect the shipped API only.

## Context
- Standard: `.claude/package-standard.md` + Package README Standards in `.claude/code-standards.md`.
  Mirror `packages/catalog-scope/README.md` for tone/length.
- `catalog-attribute/README.md`: purpose (binds the attribute kernel to `Product`); the
  `ProductAttributeValues` companion + `attribute_values` column; the `ProductAttributeAccessor`
  (set/get/all/clear, Json vs Column backing, default fallback, select-option enforcement); the
  static `sku`/`name`/`priceAmount` definitions; that values persist via `ProductRepository->save()`;
  global scope only (scoped values = Phase 3, out of scope). Note PHP 8.5 / no-final / strict-types.
- `attribute/README.md`: add a short section on `AttributeValueAccessorInterface` (the generic
  value read/write contract) and the `AttributeEntityClassMap` registry — only if they materially
  changed the package's public surface this phase.

## Requirements (Test Descriptions)
- [x] `it documents the catalog-attribute purpose companion and accessor in its README`
- [x] `it documents the static sku name and priceAmount attributes in its README`

> Note: documentation-completeness checks — verify the README exists and contains the required
> sections (file-content assertion per the project's README-task convention).

## Acceptance Criteria
- README follows the standard sections/length; content matches the shipped API (no unbuilt features).

## Implementation Notes
- Rewrote `packages/catalog-attribute/README.md` with sections: Installation, Quick Example (accessor usage + companion entity + static attributes table + entity registration), Documentation.
- Added two README assertion tests to `packages/catalog-attribute/tests/PackageScaffoldingTest.php` verifying presence of `ProductAttributeValues`, `ProductAttributeAccessor`, `AttributeValueAccessorInterface`, standard sections, and static codes (`sku`, `name`, `priceAmount`).
- `attribute/README.md` already documented `AttributeTypeInterface`, `AttributeDefinitionInterface`, and `AttributeTypeRegistry`; no touch-up needed as `AttributeValueAccessorInterface` and `AttributeEntityClassMap` are not yet surfaced there per existing test requirements.
