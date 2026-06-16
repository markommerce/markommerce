# Task 003: `ScopedOptionLabelResolver` (attribute-scope)

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Resolve a select option's display label for the active scope: a per-signature override (from the
`AttributeOptionScopedLabels` companion) if one matches, else the option's base `label`. Reuses the
kernel `ScopeWalker` + `SignatureCandidateEnumerator`.

## Context
- Place in `packages/attribute-scope/src/ScopedOptionLabelResolver.php`.
- Pattern: `config-scope`'s resolver flow + `packages/scope/src/Resolution/ScopeWalker.php`
  (`walk(HasScopesInterface $storage, string $property, array $axes, ScopeContext): ScopeWalkResult`)
  and `packages/scope/src/Resolver/ScopeResolver.php`. STUDY them. Inject the kernel `ScopeWalker`
  (or `ScopeResolver`) + `ScopeContext`.
- **VERIFIED kernel signature:** `ScopeWalker::walk(HasScopesInterface $overrides, string $property,
  list<string> $axes, ScopeContext $context): ScopeWalkResult` — property is the literal `'label'`;
  result via `isFound()`/`value()`. An axis not declared in the `ScopeRegistry` is silently OMITted,
  so unit tests must build a registry/context with the axis declared.
- **Axes source — VERIFIED:** `AttributeOption` (Phase 1) carries only `attributeId`, `value`,
  `label`, `position`; it has NO link to its definition's `config['axes']`. To derive axes from the
  owning attribute you must load the definition. So the resolver has two clean shapes — pick ONE and
  state it in Implementation Notes:
  - (A) **Explicit axes (preferred, no extra dep):** `resolve(AttributeOption $option,
    AttributeOptionScopedLabels $labels, list<string> $axes): string` — caller passes the owning
    attribute's `config()['axes']`. Keeps the resolver pure and easy to unit test.
  - (B) **Self-deriving:** also inject `AttributeDefinitionRepositoryInterface` and load the
    definition via `$repository->find($option->attributeId)` (from the extended `RepositoryInterface`),
    reading `config()['axes']`. Throw loudly if the definition is missing.
  Walk candidates for property `'label'`; on a match return the override, else return `$option->label`.
- Use the singleton `ScopeContext` for the active scope (tests set it via `context->in(axis, path)`).
- Read-only resolution; no persistence.

## Requirements (Test Descriptions)
- [x] `it returns the base label when no scoped override matches the active scope`
- [x] `it returns the most-specific matching scoped label override`
- [x] `it falls back to the base label for an axis path with no override`
- [x] `it resolves using the axes declared for the owning attribute`

## Acceptance Criteria
- Resolution uses the kernel candidate enumeration (most-specific→global) and falls back to the base label.
- No dependency on config-scope's `OverrideMatcher`.

## Implementation Notes
- Used shape (B): self-deriving axes via injected `AttributeDefinitionRepositoryInterface`
- `resolve(AttributeOption, AttributeOptionScopedLabels, ScopeContext): string`
- Delegates to `ScopeWalker::walk()` with property `'label'` and axes from `definition->config()['axes']`
- Falls back to `$option->label` when `ScopeWalkResult::isFound()` returns false
- Test helpers mirror the pattern from `config-scope/tests/Unit/ScopedConfigResolverTest.php`
