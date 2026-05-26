# Task 005: Document the registry and contribution paths

**Status**: completed
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Update scope's documentation to describe the new field-metadata model:
the `ScopedFieldRegistry`, the lazy attribute scan that feeds it, and
the bridge-package contribution pattern via `module.php`. The docs
should give a downstream package author enough information to write a
bridge in P2 without reading scope's source.

## Context
- Related files:
  - `packages/scope/README.md` — primary doc to update. Follow `docs/DOCS-STANDARDS.md`.
  - `docs/src/content/docs/packages/scope.md` — sister docs-site page; the `doc-updater` agent will sync this in the post-implementation pipeline, but the README is the authoritative source.
  - `packages/catalog/src/Entity/Product.php` — reference for an attribute-based example
- Read `docs/DOCS-STANDARDS.md` first for tone, structure, and formatting expectations.

## Requirements (Test Descriptions)
This task is documentation-only and has no automated tests. The checklist below
serves as acceptance criteria.

- [x] Section titled "Field metadata" added to `packages/scope/README.md`.
- [x] Section explains that `ScopedFieldRegistry` is the read-time source of truth.
- [x] Section describes the lazy attribute-scan path with a short code example using `#[Scoped]`.
- [x] Section describes the programmatic registration path with a `module.php` boot-callback example. The example uses constructor-injected `ScopedFieldRegistry` via the boot closure's parameter (matching the auto-inject pattern Marko uses) rather than service-locator-style `$container->get(...)`, because service location is forbidden by code-standards.
- [x] Section names the loud-failure mode: unknown axes throw `UnknownAxisException` at registration time.
- [x] Section documents the cache-staleness contract: once `ScopeMetadataFactory::for($class)` has been called for a class, subsequent `register()` calls against that class do NOT update the cached metadata. All bridge contributions MUST happen at boot, before any request handling.
- [x] Section documents the empty-axes-no-op rule for both the attribute and the programmatic path.
- [x] Section explicitly states which method on `ScopeMetadataFactory` consumers should call (`for($entityClass)`) and that the public API is unchanged.
- [x] Section uses the term "property" consistently when referring to scoped attributes on entities (matching the `ScopeMetadata` public vocabulary).

## Acceptance Criteria
- README section complete, written in the project's documentation tone (see `docs/DOCS-STANDARDS.md`).
- All code examples in the README are syntactically valid PHP and use real class names from this plan.
- No emojis or marketing language.
- Existing scope README structure preserved — the new section is appended in the natural location next to other metadata/configuration content.

## Implementation Notes
Added `## Field metadata` section to `packages/scope/README.md` immediately before
the `## Documentation` link. The section contains three subsections:
- "Attribute path (lazy scan)" — explains the reflection scan with a `Product`
  entity example using `#[Scoped(axes: ['locale'])]`, and notes the empty-axes
  no-op rule for the attribute path.
- "Programmatic path (bridge `module.php`)" — shows a `module.php` boot closure
  that type-hints `ScopedFieldRegistry` directly (auto-injected by the container),
  documents the empty-axes no-op for `register()`, and names `UnknownAxisException`
  as the loud-failure on unknown axes.
- "Cache-staleness contract" — describes the frozen-after-first-call behaviour and
  the requirement that all bridge registrations happen at boot.
