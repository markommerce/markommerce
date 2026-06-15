# Task 004: `AttributeValueAccessorInterface` + `ProductAttributeAccessor`

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Define the entity-agnostic value-accessor contract in `markommerce/attribute`, and implement it
for products in `markommerce/catalog-attribute`. The accessor is the single read/write entry point
for attribute values: it validates/casts via the Phase-1 validator and dispatches on `backing`
(`Json` → companion blob, `Column` → native `Product` property).

## Context
- **Contract (in `packages/attribute/src/Contracts/AttributeValueAccessorInterface.php`)** — generic,
  so other entities can implement it later. Parametrize by the entity object:
  - `set(object $entity, string $code, mixed $raw): void`
  - `get(object $entity, string $code): mixed`
  - `all(object $entity): array<string, mixed>`
  - `clear(object $entity, string $code): void`
  (Keep it minimal; `@throws` the relevant Phase-1 exceptions.)
- **Impl (in `packages/catalog-attribute/src/ProductAttributeAccessor.php`)** implements the
  interface; typehints `Product` internally (guard the `object` is a `Product`). Inject:
  `ProductAttributeDefinitions` (task 003), `AttributeValueValidator` (Phase 1),
  `AttributeDefinitionRepositoryInterface` (Phase 1, for `optionsFor` on select types).
- The resolver (task 003) returns the CONCRETE `AttributeDefinition`, so the accessor can read
  `->defaultValue` and pass the concrete entity to `optionsFor()`. Validate/cast still goes through
  the `AttributeDefinitionInterface`-typed `AttributeValueValidator::validate()` (the entity
  implements it).
- **set**: resolve definition (task 003). Guards: not found → `AttributeDefinitionNotFoundException`;
  definition `entityType() !== 'product'` → reject (loud). Gate option loading on the definition's
  TYPE: only for `select`/`multiselect` call `optionsFor($def)` (map options to their `value`
  strings) and pass to `validate($def, $raw, $allowedOptions)`; otherwise `validate($def, $raw)`.
  Never call `optionsFor()` on a static (they are text/decimal only — see task 003). Then dispatch:
  - `backing() === Column` → set the native property: `$product->{$def->config()['property']} = $value;`
    (the cast value; for `priceAmount` this is a precision-safe string). Validate the property exists.
  - `backing() === Json` → companion `ProductAttributeValues::set($code, $value)`, attaching the
    companion to the product if not already present (`$product->companion(...)` / `attachCompanion`).
- **get**: resolve def; dispatch read (`Column` → native property; `Json` → companion `get`); when
  nothing stored, fall back to the definition's `->defaultValue` (the concrete entity's raw `?string`
  default). Cast the raw default through the type/validator so it returns the typed value (e.g. a
  `decimal` default becomes a precision-safe string, an `int` an int); a `null` default → null.
  For `Column` backing the "nothing stored" check is the native property being `null` (note `sku`/`name`
  are non-nullable `string` with `''` default — decide whether `''` counts as "stored"; document it).
- **all** (decided contract): return a `{code: value}` map that **always includes every static
  attribute** (`sku`/`name`/`priceAmount`) resolved via `get()` — i.e. the native-column value, or
  the definition's default when the column is null — **plus every stored custom value**. So statics
  appear even when no value was explicitly set (their resolved/default value), while custom
  attributes appear only when stored (we do NOT enumerate all custom definitions here). Implement by
  iterating the static provider's codes through `get()` and merging the companion's stored map.
- **clear**: `Column` is non-clearable (or sets the property to null where nullable — document the
  choice); `Json` → companion `clear`.
- The accessor does NOT persist — the caller runs `ProductRepository->save($product)`.

## Requirements (Test Descriptions)
- [ ] `it sets and gets a Json-backed value on the product companion`
- [ ] `it sets a Column-backed value onto the native product property`
- [ ] `it gets a Column-backed value from the native product property`
- [ ] `it validates and casts the value via the attribute validator before storing`
- [ ] `it enforces select option membership using options loaded for the definition`
- [ ] `it throws AttributeDefinitionNotFoundException for an unknown code`
- [ ] `it falls back to the definition default when no value is stored`
- [ ] `it includes every static attribute in all even when no value is explicitly stored`
- [ ] `it includes stored custom values alongside statics in all`

## Acceptance Criteria
- One accessor handles both backings via the uniform contract; values validated/cast before storage.
- `ProductAttributeAccessor implements AttributeValueAccessorInterface`.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
