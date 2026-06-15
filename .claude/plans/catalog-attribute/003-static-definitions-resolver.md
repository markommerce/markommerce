# Task 003: Static definitions + definition resolver

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Provide code-declared `Column`-backed (static) attribute definitions for the opt-in `Product`
native columns, and a resolver that merges those statics with DB-stored custom definitions so the
accessor can look up any product attribute by code uniformly.

## Context
- Place in `packages/catalog-attribute/src/Definition/`.
- `StaticAttributeProvider`: returns a `list<AttributeDefinition>` of code-declared statics for
  products — `sku`, `name`, `priceAmount` — each with `backing = 'Column'` (the
  `AttributeBacking::Column` string form), `entityType = 'product'`, and
  `config = ['property' => '<Product property name>']` (`sku`/`name`/`priceAmount`). Set a sensible
  `type` per column (sku/name → `text`, priceAmount → `decimal`). These are NOT persisted — they
  are in-memory definition objects. (Use the Phase-1 `Markommerce\Attribute\Entity\AttributeDefinition`
  + `AttributeBacking`.)
- `ProductAttributeDefinitions` resolver: `findByCode(string $code): ?AttributeDefinition`
  — checks the static provider first, then the DB via `AttributeDefinitionRepositoryInterface`
  (`findByCode('product', $code)`). Inject the repository interface + the static provider.
  - **Return the CONCRETE `Markommerce\Attribute\Entity\AttributeDefinition`, NOT the
    `AttributeDefinitionInterface`.** The accessor (task 004) needs `->defaultValue` (only on the
    entity, not the interface) for default fallback, and `AttributeDefinitionRepositoryInterface::optionsFor()`
    is typed against the concrete `AttributeDefinition`. The repo's `findByCode` already returns
    `?AttributeDefinition`; statics are `AttributeDefinition` instances too — so the concrete type
    flows end-to-end with no interface gymnastics.
- The resolver is the single definition lookup the accessor (task 004) uses.
- Do NOT mutate `attribute_definitions` rows for statics; statics live only in code. Statics have
  NO DB `id` (it stays `null`) and NO options — they are only ever `text`/`decimal` types, never
  `select`/`multiselect`, so `optionsFor()` is never called on a static (which would otherwise
  query `attribute_id IS NULL`). The accessor must gate `optionsFor()` on the definition's TYPE,
  not its presence.

## Requirements (Test Descriptions)
- [x] `it provides Column-backed static definitions for sku name and priceAmount`
- [x] `it marks static definitions with the product entity type and a config property mapping`
- [x] `it resolves a static definition by its code`
- [x] `it resolves a custom definition from the repository when not static`
- [x] `it returns null when no static or custom definition matches the code`
- [x] `it prefers a static definition over a custom one with the same code`

## Acceptance Criteria
- Statics are code-declared, `Column`-backed, carry the `config['property']` mapping.
- The resolver merges static + custom lookups with statics taking precedence.

## Implementation Notes
- `StaticAttributeProvider` in `src/Definition/StaticAttributeProvider.php` returns 3 in-memory `AttributeDefinition` instances (`sku`, `name`, `priceAmount`) with `backing='Column'`, `entityType='product'`, `type='text'` (sku/name) or `'decimal'` (priceAmount), and `config=['property' => '<propName>']`.
- `ProductAttributeDefinitions` in `src/Definition/ProductAttributeDefinitions.php` takes `AttributeDefinitionRepositoryInterface` and `StaticAttributeProvider` via constructor injection; `findByCode()` checks statics first (using `array_find`), falls through to `findByCode('product', $code)` on the repository.
- A hand-written `FakeAttributeDefinitionRepository` was added to `tests/Support/` mirroring the one in `packages/attribute/tests/Support/`.
