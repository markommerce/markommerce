# Task 013: `attribute` `module.php` — bindings, singletons, boot

**Status**: pending
**Depends on**: 004, 005, 006, 007, 008, 012
**Retry count**: 0

## Description
Wire the `attribute` package as a Marko module: bind the service/validator interfaces, register
the `AttributeTypeRegistry` as a singleton, and in `boot` register all eight built-in types into
the registry. This makes the kernel usable and the type set Preference-overridable.

## Context
- Pattern: `packages/config/module.php` (`bindings`/`singletons`/`boot` with `ContainerInterface`)
  and `packages/catalog/module.php` (boot that registers into a registry, e.g.
  `CategorySortOrderRegistry->register(...)`).
- `singletons`: `AttributeTypeRegistry`.
- `bindings`: the `attribute` (interface/kernel) module binds only what it can satisfy itself —
  i.e. concrete kernel services that take constructor-injected deps. The
  `AttributeDefinitionRepositoryInterface` → PgSql impl binding lives in `attribute-pgsql`'s
  `module.php` (task 014), NOT here, because the impl is in the driver package (interface/driver
  split). For `AttributeDefinitionService` and `AttributeValueValidator`, bind a concrete class
  (or a `*Interface` only if task 012/011 actually defined one — they currently expose concrete
  classes, so a plain class binding/auto-resolution is sufficient). Do not invent interfaces that
  the earlier tasks did not produce.
- `boot`: resolve `AttributeTypeRegistry` and `register()` `TextType`, `IntType`, `DecimalType`,
  `BoolType`, `DateType`, `SelectType`, `MultiselectType`, `EntityRefType`. Bind the populated
  registry instance back into the container (`$container->instance(...)`) following the config
  boot pattern.
- Downstream override: a consumer registering a type with an existing `code()` in their own boot
  replaces the built-in (verified by task 008's override semantics).

## Requirements (Test Descriptions)
- [ ] `it registers all eight built-in attribute types in the registry after boot`
- [ ] `it resolves the AttributeTypeRegistry as a singleton from the container`
- [ ] `it binds the definition service interface to its implementation`
- [ ] `it lets a downstream registration override a built-in type code`

## Acceptance Criteria
- After boot, `registry->all()` contains the eight built-in type codes.
- Module loads under the Marko module system without errors.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
