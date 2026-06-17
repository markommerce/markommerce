# Plan: Product Attribute Values + Static-Column Binding (Custom Attributes — Phase 2)

## Created
2026-06-15

## Status
completed

## Objective
Attach custom attribute VALUES to products (global scope), stored in a same-row companion JSON
column, with a uniform value accessor that dispatches `Json`-backed values to the companion blob
and `Column`-backed (static) values to native `Product` columns (sku/name/priceAmount).

## Related Issues
none

## Discovery Notes
Phase 2 of the `custom-attributes` meta-plan. Branched from `feature/attribute-definitions`
(Phase 1: `markommerce/attribute` + `markommerce/attribute-pgsql`), which is committed but not
yet merged to `develop` — so this branch carries Phase 1's commit until it merges.

Grounded in the existing `catalog-scope` companion pattern:
- `#[Table(extends: Product::class)]` **adds a column to the same `catalog_products` table**
  (NOT a separate table). `catalog-scope`'s `ProductScopedOverrides` + `HasScopes` trait add a
  `scopes` JSON column this way. Companions auto-attach on hydrate; `ProductRepository->save()`
  persists the companion's JSON column automatically. JSON columns auto-decode to PHP arrays via
  the Marko hydrator. Multiple extenders per entity are supported (`linkExtenders`).
- Phase-1 API to build on: `AttributeValueValidator::validate(AttributeDefinitionInterface $def,
  mixed $raw, array $allowedOptions = []): mixed`; `AttributeDefinitionRepositoryInterface`
  (`findByCode`, `optionsFor`); `AttributeDefinition::backing()` (`Column`|`Json`) + `config()`;
  the `AttributeBacking` enum; and the entityType→class map the Phase-1 service accepts for
  reserved-code checks (currently a constructor array, default empty).
- `catalog-attribute` does not exist yet. Sibling binding packages: `catalog-scope` (the
  template), `catalog-market`, `catalog-locale`, `catalog-price-index`.

Resolved decisions (clarification):
- **Storage = same-row companion column.** `ProductAttributeValues` companion adds an
  `attribute_values` JSON column to `catalog_products`; written by attaching the companion and
  calling `ProductRepository->save()`. No new table, no `attribute-pgsql` change, single-row load.
  Whole-column rewrite on save (no server-side `jsonb_set` — acceptable for Phase 2 global values).
- **Full Column read/write for static attributes.** Register opt-in `Column`-backed definitions
  for `sku`/`name`/`priceAmount` and wire the accessor to read/write the native `Product`
  properties — delivering the uniform attribute API over native columns + custom values.
- **Global scope only** (scoped values are Phase 3; they will nest under signatures in the same
  companion). Blob shape: flat `{code: value}`.
- Generic value-accessor *contract* lives in `markommerce/attribute`; the Product implementation
  in `markommerce/catalog-attribute`.
- **`all(Product)` always includes statics + defaults** (resolved): the map always emits every
  static attribute (resolved native-column value, or definition default when null) plus stored
  custom values; custom definitions with only a default but no stored value are NOT enumerated.
- **`AttributeEntityClassMap::register()` throws on duplicate** (resolved — loud errors): a
  different class for an already-registered `entityType` raises
  `DuplicateEntityClassRegistrationException`, never a silent overwrite.

## Scope

### In Scope
- New `markommerce/catalog-attribute` package.
- `ProductAttributeValues` companion (`#[Table(extends: Product)]`, `attribute_values` JSON column).
- Code-declared static (`Column`-backed) definitions for `sku`/`name`/`priceAmount`; a resolver
  merging static + DB-stored custom definitions.
- `AttributeValueAccessorInterface` (in `attribute`) + `ProductAttributeAccessor` (in
  `catalog-attribute`): validate/cast via Phase-1 validator, dispatch Json↔companion /
  Column↔native property, default fallback, guards.
- Wiring: bind the accessor; (the companion auto-links via framework entity discovery — no manual
  linkExtenders); make the Phase-1
  reserved-code check aware that `product` → `Product::class` (via a small entity-class registry
  in `attribute`).
- Integration tests (real DB) + README.

### Out of Scope
- Scoped attribute values / labels (Phase 3).
- Read model / index / faceting / search (Phases 4–6).
- Attribute values on entities other than `Product` (the accessor contract is generic; only the
  Product binding ships now).
- Admin UI / API endpoints / storefront rendering.
- Server-side `jsonb_set` partial writes (whole companion column is rewritten on save).

## Success Criteria
- [ ] Set/get custom `Json`-backed attribute values on a product; persists + reloads via `ProductRepository`.
- [ ] Set/get a `Column`-backed (static) value (e.g. `name`, `priceAmount`) through the same accessor, reading/writing the native column.
- [ ] Values are validated/cast via the Phase-1 validator (select options enforced) before storing; invalid → loud error.
- [ ] Unknown attribute code → `AttributeDefinitionNotFoundException`; wrong `entityType` rejected.
- [ ] Creating a custom product attribute whose code collides with a native column is rejected (reserved-code map wired).
- [ ] `attribute_values` column merges into `catalog_products`; integration round-trip passes.
- [ ] All tests passing (unit + integration); coverage ≥ 80%; phpcs / phpstan level 8 clean.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `markommerce/catalog-attribute` package | - | completed |
| 002 | `ProductAttributeValues` companion entity | 001 | completed |
| 003 | Static definitions + definition resolver (static + custom) | 001 | completed |
| 004 | `AttributeValueAccessorInterface` (in `attribute`) + `ProductAttributeAccessor` | 002, 003 | completed |
| 005 | Module wiring + entity-class registry (reserved-code map) | 002, 003, 004 | completed |
| 006 | Integration tests (companion round-trip, Column + Json, reserved code) | 004, 005 | completed |
| 007 | README (catalog-attribute) + attribute README touch-up | 001-006 | completed |

## Architecture Notes
- **Companion**: `ProductAttributeValues extends Entity` with `#[Table(extends: Product::class)]`
  and `#[Column(name: 'attribute_values', type: 'json', nullable: true)] public ?array $values`.
  Methods: `set(string $code, mixed $value)`, `get(string $code): mixed`, `all(): array`,
  `has(string $code): bool`, `clear(string $code)`. Mirror `HasScopes` but a flat code→value map
  (no signatures yet). **Auto-linked as a Product extender by framework entity discovery** — the
  marko `database` module's boot runs `EntityDiscovery` + `linkExtendersFrom()`, so the
  `#[Table(extends: Product::class)]` attribute alone links it (no manual `linkExtenders` call;
  this is why `catalog-scope` ships its companion with no `module.php`).
- **Static definitions**: code-declared (NOT DB rows) via a `StaticAttributeProvider` returning
  `AttributeDefinition` objects with `backing = Column`, `entityType = 'product'`, and
  `config['property']` = the `Product` property name (`sku`, `name`, `priceAmount`; verified these
  are public props on `Markommerce\Catalog\Entity\Product`, `priceAmount` being a nullable
  decimal(20,4) string). A `ProductAttributeDefinitions` resolver merges statics + DB customs
  (`AttributeDefinitionRepositoryInterface`) and resolves a definition by code, returning the
  CONCRETE `AttributeDefinition` (the accessor needs `->defaultValue`, absent from the interface,
  and `optionsFor()` is typed against the concrete entity). Statics have no DB `id` and are only
  `text`/`decimal` — never `select` — so `optionsFor()` is never invoked on them.
- **Accessor** (`ProductAttributeAccessor implements AttributeValueAccessorInterface`):
  `set(Product $product, string $code, mixed $raw)`, `get(Product $product, string $code): mixed`,
  `all(Product $product): array`, `clear(Product $product, string $code)`. On set: resolve def
  (guards: not found, wrong entityType) → load options if select → `validate()`/cast → dispatch:
  `Column` → set the native property named in `config['property']`; `Json` → companion `set`. On
  get: dispatch read; fall back to the definition's `defaultValue` (cast) when nothing stored.
  Persistence is the caller's `ProductRepository->save($product)`.
- **Reserved-code wiring**: introduce a small `AttributeEntityClassMap` singleton in `attribute`
  (registered in `attribute/module.php`; modules call `register('product', Product::class)` at boot).
  Verified Phase-1 signature: `AttributeDefinitionService::__construct(..., array $entityTypeMap = [])`,
  auto-wired (not bound). Make the change BACKWARD-COMPATIBLE: KEEP `$entityTypeMap` and ADD an
  optional `?AttributeEntityClassMap $entityClassMap = null`; in `guardReservedCode` resolve
  `$this->entityTypeMap[$type] ?? $this->entityClassMap?->all()[$type] ?? null`. This keeps all 8
  Phase-1 `AttributeDefinitionServiceTest` cases and the `ModulePhpTest` auto-wire case passing
  with NO Phase-1 test edits. `catalog-attribute` registers `'product' => Product::class` at boot.
- No `attribute-pgsql` change: values persist on `catalog_products` via the catalog repository.
- Standards: PHP 8.5, no `final`, `declare(strict_types=1)`, constructor injection, `@throws`,
  PHP 8.5 `array_*` idioms.

## Risks & Mitigations
- **Phase-1 service map vs registry**: adding `product → Product` may require touching the
  Phase-1 `AttributeDefinitionService`. Mitigation: introduce the entity-class registry in
  `attribute` and have the service consult it; keep the change minimal and re-run the Phase-1
  suite (task 005/006) to confirm no regression.
- **Dynamic property access for Column backing**: reading/writing `$product->{$property}` must
  use the property name from `config['property']` (public Product props), not magic methods.
  Validate the property exists; fail loudly otherwise.
- **decimal Column values**: `priceAmount` is a precision-safe string; the DecimalType cast
  returns a string — assert it round-trips onto the native column without float coercion.
- **Schema column merge**: the `attribute_values` column only exists on `catalog_products` when
  `ProductAttributeValues` is linked as an extender. Linking is automatic via framework discovery
  (`database` module boot → `linkExtendersFrom`), so the integration test just needs a `StoreProfile`
  that includes `markommerce/catalog-attribute` + `marko/database-pgsql` (and the attribute-pgsql
  closure for definition rows); the harness then provisions the merged column. Model the real-DB
  setup on `catalog-market`/`attribute-pgsql` integration tests, NOT on catalog-scope's
  fake-connection `CompanionPersistenceTest`.
- **Multiple companions**: `ProductScopedOverrides` (catalog-scope) and `ProductAttributeValues`
  can both extend Product; ensure linking one doesn't break the other (test with catalog-scope
  absent — catalog-attribute must not depend on catalog-scope).
