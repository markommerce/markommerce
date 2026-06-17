# Task 005: Module wiring + entity-class registry (reserved-code map)

**Status**: pending
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Wire `catalog-attribute` as a Marko module: bind the accessor, and make the Phase-1 reserved-code
check aware that `product → Product::class` via a small entity-class registry contributed at boot.
The companion does NOT need manual linking — it auto-links via framework entity discovery.

## Context
- **Companion linking is AUTOMATIC — do NOT add a `linkExtenders` call.** Verified: the marko
  `database` module's `boot` (`marko/packages/database/module.php`) runs `EntityDiscovery` across
  vendor/modules/app and calls `EntityMetadataFactory::linkExtendersFrom($entityClasses)`. Any class
  with `#[Table(extends: Product::class)]` is auto-discovered and linked. This is exactly why
  `catalog-scope` ships its `ProductScopedOverrides` companion with NO `module.php` at all. So
  `catalog-attribute`'s `module.php` is needed ONLY for the binding + the entity-class registry
  registration; the companion linking happens for free at framework boot.
- **Entity-class registry (in `packages/attribute/`)**: Phase-1's `AttributeDefinitionService`
  takes the entityType→class map as a constructor array `array $entityTypeMap = []` (verified
  signature) and is NOT bound in `attribute/module.php` (`'bindings' => []`) — it is auto-wired by
  the container, resolving `$entityTypeMap` to the empty default. Introduce an injectable singleton
  `Markommerce\Attribute\Registry\AttributeEntityClassMap` with
  `register(string $entityType, string $entityClass): void` and `all(): array<string, class-string>`.
  Register it as a singleton in `attribute/module.php`.
  - **Duplicate registration throws (decided — loud errors).** `register()` must reject a second
    registration for an already-registered `entityType` by throwing a new
    `Markommerce\Attribute\Exceptions\DuplicateEntityClassRegistrationException` (extends
    `MarkoException`; static factory `forEntityType(string $entityType, string $existing, string
    $attempted)` with named `message`/`context`/`suggestion`), rather than silently overwriting.
    Place it alongside the Phase-1 exceptions in `packages/attribute/src/Exceptions/`. Registering
    the SAME `(entityType, class)` pair again may be treated as idempotent (no-op) or also throw —
    pick one and document it; a DIFFERENT class for an existing entityType must throw.
  - **Backward-compatible change to `AttributeDefinitionService`**: KEEP the existing
    `array $entityTypeMap = []` constructor param, and ADD an optional injected
    `?AttributeEntityClassMap $entityClassMap = null` (nullable so direct construction in the
    existing Phase-1 unit tests still works without passing it). In `guardReservedCode`, resolve the
    entity class as `$this->entityTypeMap[$entityType] ?? $this->entityClassMap?->all()[$entityType] ?? null`
    (constructor array wins; registry fills in container-wired consumers). This preserves ALL 8
    existing `AttributeDefinitionServiceTest` cases (they pass `entityTypeMap:` directly) and the
    `ModulePhpTest` auto-wire case — NO edits to Phase-1 tests should be required. Re-run the Phase-1
    attribute suite to confirm green.
- **`catalog-attribute/module.php`**:
  - `singletons`: none required here (the registry singleton lives in `attribute/module.php`).
  - `boot(ContainerInterface $container)` (or inject `AttributeEntityClassMap` directly): call
    `$entityClassMap->register('product', Product::class)`. If a static-definition registry is used
    instead of constructing `StaticAttributeProvider` in-place, register the statics here too.
  - `bindings`: `AttributeValueAccessorInterface` → `ProductAttributeAccessor`.
- Do NOT depend on `catalog-scope`; `catalog-attribute` must work whether or not catalog-scope is
  installed (both may extend Product independently via their own discovered companions).

## Requirements (Test Descriptions)
- [ ] `it auto-discovers ProductAttributeValues as a Product extender via linkExtendersFrom on the discovered entity list` (assert via `EntityMetadataFactory::linkExtendersFrom([Product::class, ProductAttributeValues::class])` then `parse(Product)->extenders` contains it — mirror catalog-scope's `CompanionPersistenceTest`; NOT a module-boot assertion)
- [ ] `it binds AttributeValueAccessorInterface to ProductAttributeAccessor`
- [ ] `it registers the product entity class in the attribute entity-class map after boot`
- [ ] `it makes the definition service reject a custom product code that collides with a native column when the registry maps product to Product`
- [ ] `it throws DuplicateEntityClassRegistrationException when a different class is registered for an existing entity type`

## Acceptance Criteria
- Module boots without error; accessor bound; `'product' => Product::class` registered in the map.
- Companion auto-links via discovery (no manual linkExtenders in module.php).
- Creating a custom `product` attribute named `sku`/`name`/etc. is rejected (reserved) via the wired map.
- Phase-1 attribute package suite still green after the backward-compatible service change (no Phase-1 test edits).

## Implementation Notes
(Left blank - filled in by programmer during implementation)
