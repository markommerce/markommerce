# Task 002: `AttributeOptionScopedLabels` companion (attribute-scope)

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a companion that adds a `scoped_labels` JSON column to the `attribute_options` table, holding
per-signature label overrides for a select option. Implements `HasScopesInterface` so the kernel
`ScopeWalker` can resolve it.

## Context
- Pattern: `packages/catalog-scope/src/Entity/ProductScopedOverrides.php` (companion via
  `#[Table(extends:)]`) and `packages/scope/src/Storage/HasScopes.php` / `HasScopesInterface`. STUDY both.
- Place in `packages/attribute-scope/src/Entity/AttributeOptionScopedLabels.php`.
- `#[Table(extends: \Markommerce\Attribute\Entity\AttributeOption::class)]`.
- `#[Column(name: 'scoped_labels', type: 'json', nullable: true)] public ?array $scopedLabels = null;`
  — shape `{ "signature": { "label": "<text>" }, ... }`.
- **Implement `HasScopesInterface` EXPLICITLY** (do NOT use the `HasScopes` trait — it hardcodes a
  `scopes` column which would collide). Provide `setOverride(sig, property, value)`, `override(sig,
  property)`, `hasOverride(sig, property)`, `clearOverride(sig, property)`, `overrides()` operating on
  `$scopedLabels` (the only `property` used is `'label'`). `ksort` for determinism.

## Requirements (Test Descriptions)
- [ ] `it maps AttributeOptionScopedLabels to the attribute_options table via the scoped_labels column`
- [ ] `it sets and reads a label override for a signature`
- [ ] `it reports whether a signature has a label override`
- [ ] `it clears a label override and nulls the column when empty`
- [ ] `it implements HasScopesInterface`

## Acceptance Criteria
- Column targets `attribute_options` (extends AttributeOption); implements `HasScopesInterface`.
- Uses a non-`scopes` column name; no trait.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
