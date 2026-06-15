# Task 002: `ProductAttributeValues` companion entity

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the `ProductAttributeValues` companion that adds an `attribute_values` JSON column to the
`catalog_products` table (same-row companion), holding a flat `{code: value}` map of custom
(`Json`-backed) attribute values. Provides typed accessors over the map.

## Context
- Pattern: `packages/catalog-scope/src/Entity/ProductScopedOverrides.php` +
  `packages/scope/src/Storage/HasScopes.php`. STUDY both. (No `final`; extends
  `Marko\Database\Entity\Entity`.)
- Place in `packages/catalog-attribute/src/Entity/ProductAttributeValues.php`.
- `#[Table(extends: \Markommerce\Catalog\Entity\Product::class)]`.
- `#[Column(name: 'attribute_values', type: 'json', nullable: true)] public ?array $values = null;`
  (hydrator auto-decodes JSON → array, like `HasScopes::$scopes`).
- Methods (NO traits — explicit methods on the class, matching the "no traits" rule; the
  `HasScopes` trait is pre-existing scope code, do not add new traits):
  - `set(string $code, mixed $value): void` — flat map, `ksort` for determinism.
  - `get(string $code): mixed` — value or null.
  - `has(string $code): bool`.
  - `all(): array<string, mixed>` — the whole map (default `[]`).
  - `clear(string $code): void` — unset; null the column when empty.
- Flat map only — NO scope signatures (Phase 3). Values are already-cast/serialized scalars or
  arrays (the accessor in task 004 validates/casts before calling `set`).
- **NO module-boot linking needed for the extender.** The marko `database` module's boot already
  runs `EntityDiscovery` (vendor/modules/app) + `EntityMetadataFactory::linkExtendersFrom()`, so any
  class carrying `#[Table(extends: Product::class)]` is auto-discovered and linked as a Product
  extender at framework boot — exactly how `catalog-scope`'s `ProductScopedOverrides` works WITHOUT
  a `module.php`. Do not add a manual `linkExtenders` call. (See `marko/packages/database/module.php`.)

## Requirements (Test Descriptions)
- [ ] `it maps ProductAttributeValues to the catalog_products table via the attribute_values column`
- [ ] `it sets and gets a value by code`
- [ ] `it reports whether a code has a stored value`
- [ ] `it returns all stored values as a code-keyed map`
- [ ] `it clears a stored value and nulls the column when empty`

## Acceptance Criteria
- Entity metadata parses; the `attribute_values` column targets `catalog_products` (extends Product).
- Accessors operate on the flat map deterministically.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
