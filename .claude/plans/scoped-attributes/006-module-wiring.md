# Task 006: Module wiring for both packages

**Status**: pending
**Depends on**: 002, 003, 004, 005
**Retry count**: 0

## Description
Wire `attribute-scope` and `catalog-attribute-scope` as Marko modules: bind the resolver/accessor,
and ensure the two new companions link to their parents under both real boot and the test harness.

## Context
- Pattern: `packages/config-scope/module.php` (bindings/boot) and the Phase-2 `catalog-attribute/module.php`
  (which adds an explicit `EntityMetadataFactory::linkExtenders(Parent, [Companion])` in boot so the
  companion links under the integration-test harness — REPLICATE that here, the lesson from Phase 2).
- `attribute-scope/module.php`:
  - `boot`: `linkExtenders(AttributeOption::class, [AttributeOptionScopedLabels::class])`.
  - `bindings`/`singletons`: register `ScopedOptionLabelResolver` (constructor-injected; a plain class
    binding / auto-resolution suffices unless it needs an interface — do not invent interfaces the
    earlier tasks didn't produce).
- `catalog-attribute-scope/module.php`:
  - `boot`: `linkExtenders(Product::class, [ProductScopedAttributeValues::class])`.
  - register `ScopedProductAttributeAccessor`.
  - If a scopable-axes registration into `ScopedFieldRegistry` is warranted for future SQL faceting,
    register scopable attributes' axes here — otherwise note it's deferred (PHP-side resolution reads
    axes from `config['axes']` directly and needs no registry).
- Do NOT depend on `catalog-scope`.

## Requirements (Test Descriptions)
- [ ] `it links AttributeOptionScopedLabels as an extender of AttributeOption after boot`
- [ ] `it links ProductScopedAttributeValues as an extender of Product after boot`
- [ ] `it resolves the ScopedOptionLabelResolver from the container`
- [ ] `it resolves the ScopedProductAttributeAccessor from the container`

## Acceptance Criteria
- Both modules boot without error; both companions link to their parents (assert via
  `EntityMetadataFactory::linkExtenders(...)` + `parse(Parent)->extenders`, mirroring catalog-scope's
  companion test).
- No dependency on `catalog-scope`.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
