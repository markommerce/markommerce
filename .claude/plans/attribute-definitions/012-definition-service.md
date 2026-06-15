# Task 012: Definition service + repository interface (CRUD + guards)

**Status**: pending
**Depends on**: 002, 008, 009, 010
**Retry count**: 0

> Note: the service does CRUD over *definitions and options* and enforces structural guards
> (duplicate/reserved code, unknown type, options-only-for-select). It does NOT validate
> attribute *values* — `AttributeValueValidator` (task 011) is a Phase-2 concern, so 011 is not a
> dependency here.

## Description
Implement `AttributeDefinitionRepositoryInterface` (+ an in-memory fake for unit tests) and
`AttributeDefinitionService`, the runtime CRUD entry point for merchants to create/update/delete
attribute definitions and their select options. The service enforces the guards: duplicate code
per entity type, reserved-code collision, unknown type, and option validity.

## Context
- Repository interface in `packages/attribute/src/Contracts/`, extending Marko
  `RepositoryInterface<AttributeDefinition>` (pattern: `ProductRepositoryInterface`). Add at
  minimum:
  - `findByCode(string $entityType, string $code): ?AttributeDefinition`.
  - Option access: `optionsFor(AttributeDefinition $definition): list<AttributeOption>`,
    `saveOption(AttributeOption $option): void`, `deleteOptionsFor(AttributeDefinition
    $definition): void`. (Keep option persistence behind this interface so the in-memory fake and
    the PgSql driver in task 014 share one contract; the cascade delete is FK-enforced in pgsql
    but the fake must mimic it — see task 014 / `_plan.md` Risks.)
- In-memory fake in `tests/` (pattern: `FakeProductRepository`) for unit-testing the service
  without a DB; the fake must mimic FK cascade (deleting a definition drops its options).
- **Shared repository contract suite (parity guard, shared with task 014):** create a reusable
  test that asserts `AttributeDefinitionRepositoryInterface` behavior given a repository factory —
  e.g. a function `attributeDefinitionRepositoryContract(callable $makeRepo): void` (or a Pest
  `describe` closure parametrized by the factory) in a shared, autoloadable location in the
  `attribute` package (e.g. `packages/attribute/tests/Contract/`). It must cover: `save` then
  `find`/`findByCode`; `findByCode` miss returns null; `optionsFor` round-trip; `saveOption`;
  `deleteOptionsFor`; and definition delete cascading to options. **Task 012 runs this suite
  against the in-memory fake (unit tier); task 014 runs the *same* suite against the PgSql repo
  (integration tier).** Confirm cross-package test autoload during implementation (attribute-pgsql
  must be able to load the suite from the `attribute` package — wire via composer `autoload-dev` /
  the monorepo bootstrap; note if it needs adjustment).
- Service in `packages/attribute/src/Services/AttributeDefinitionService.php`, injecting the
  repository interface, `AttributeTypeRegistry`, and `ReservedCodeProvider`.
- The service needs to know which entity class backs an `entityType` to compute reserved codes.
  For Phase 1, accept an injected `array<string entityType, class-string>` map (empty/default is
  fine; Product entry is added in Phase 2). When a definition's entityType has no mapped class,
  skip reserved-code checking (no native columns to clash with) — document this.
- Guards on create/update:
  - Unknown `type` (not in registry) → `UnknownAttributeTypeException`.
  - Reserved code (collides with mapped entity's columns) → `ReservedAttributeCodeException`.
  - Duplicate `(entityType, code)` → `DuplicateAttributeCodeException`.
  - Options only permitted for select/multiselect types; invalid otherwise.
- Delete removes the definition and its options.

## Requirements (Test Descriptions)
- [ ] `it creates an attribute definition with a valid code and registered type`
- [ ] `it rejects a definition whose type is not registered`
- [ ] `it rejects a definition whose code collides with a reserved entity column`
- [ ] `it rejects a duplicate code within the same entity type`
- [ ] `it allows the same code under a different entity type`
- [ ] `it attaches select options to a select definition`
- [ ] `it deletes a definition and its options`
- [ ] `it satisfies the attribute definition repository contract with the in-memory fake`

## Acceptance Criteria
- All guards enforced via the loud domain exceptions.
- Service depends only on the repository interface, not a concrete driver.
- A reusable repository contract suite exists and passes against the in-memory fake; it is
  authored so task 014 can run the identical suite against the PgSql driver.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
