# Task 004: `ProductScopedAttributeValues` companion (catalog-attribute-scope)

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Create a companion that adds a `scoped_attribute_values` JSON column to the `catalog_products` table,
holding per-signature overrides of custom (`Json`-backed) attribute values, keyed by attribute code.
Implements `HasScopesInterface` so the kernel `ScopeWalker` resolves it.

## Context
- Pattern: `packages/catalog-scope/src/Entity/ProductScopedOverrides.php` + `HasScopesInterface`. STUDY.
- Place in `packages/catalog-attribute-scope/src/Entity/ProductScopedAttributeValues.php`.
- `#[Table(extends: \Markommerce\Catalog\Entity\Product::class)]`.
- `#[Column(name: 'scoped_attribute_values', type: 'json', nullable: true)] public ?array $scopedValues = null;`
  — shape `{ "signature": { "code": value, ... }, ... }`.
- **Implement `HasScopesInterface` EXPLICITLY** (NOT the `HasScopes` trait — it hardcodes `scopes`,
  which collides with catalog-scope's column on `catalog_products`). The interface `property` param =
  the attribute code. Provide `setOverride(sig, code, value)`, `override(sig, code)`, `hasOverride(sig,
  code)`, `clearOverride(sig, code)`, `overrides()` on `$scopedValues`; `ksort` for determinism.
- Values stored here are already-cast (the accessor in task 005 validates/casts before calling set).

## Requirements (Test Descriptions)
- [x] `it maps ProductScopedAttributeValues to the catalog_products table via the scoped_attribute_values column`
- [x] `it sets and reads a value override for a signature and code`
- [x] `it reports whether a signature and code has an override`
- [x] `it clears an override and nulls the column when empty`
- [x] `it implements HasScopesInterface`
- [x] `it uses a distinct column name from the catalog-scope scopes column`

## Acceptance Criteria
- Column targets `catalog_products` (extends Product); implements `HasScopesInterface`; non-`scopes` column name.
- Override map is `{signature: {code: value}}`, deterministic.

## Implementation Notes
- Implemented `HasScopesInterface` explicitly (no trait) using `$scopedValues` backed by `#[Column(name: 'scoped_attribute_values', ...)]` to avoid collision with catalog-scope's `scopes` column.
- `ksort` applied after every write for deterministic ordering; column nulled when all overrides are cleared.
