# Plan: Scoped Attribute Values + Translatable Option Labels (Custom Attributes — Phase 3)

## Created
2026-06-15

## Status
completed

## Objective
Resolve product custom-attribute VALUES and select-option LABELS per scope (locale/market/…) with
global fallback, reusing the existing scope-signature resolution kernel. Two new packages:
`markommerce/attribute-scope` (kernel: scoped option labels) and `markommerce/catalog-attribute-scope`
(product scoped values). Purely additive — no changes to the Phase-1/2 packages.

## Related Issues
none

## Discovery Notes
Phase 3 of the `custom-attributes` meta-plan. Branched from `feature/catalog-attribute` (carries
Phase 1 + Phase 2 commits). Grounded in the existing scope machinery:
- **Reusable resolution kernel** (`packages/scope`): `SignatureCandidateEnumerator::enumerate(axes,
  ScopeContext)` (most-specific→global candidates); `ScopeWalker::walk(HasScopesInterface $overrides,
  string $property, array $axes, ScopeContext)` → first matching override or `notFound()` (verified
  param name is `$overrides`, not `$storage`);
  `ScopeResolver::resolved(Entity, property)` (generic: finds a `HasScopesInterface` companion,
  walks, falls back to the base property); `ScopedFieldRegistry`; the singleton `ScopeContext`
  (tests set it via `context->in(axis, path)`; HTTP middleware populates it at runtime).
- **`catalog-scope` is storage-only**: `ProductScopedOverrides` companion (`scopes` JSON column on
  `catalog_products`, shape `{signature: {property: value}}`, via the `HasScopes` trait).
- **`HasScopes` trait hardcodes a `scopes` column** → a new companion must use a DIFFERENT column
  name and implement `HasScopesInterface` explicitly (no trait reuse). `ScopeWalker` works on any
  `HasScopesInterface`, so a code-keyed companion resolves for free.
- **No `-pgsql` package needed**: scoped data rides in JSON columns provisioned from entities
  (catalog-scope has no `-pgsql` sibling). The meta-plan's tentative `attribute-scope-pgsql` is dropped.
- Phase-1 `AttributeDefinition` has a `scopable` bool + `config()` JSONB; Phase-2 `ProductAttributeValues`
  is a flat `{code: value}` companion with `ProductAttributeAccessor` (Json↔companion, Column↔native prop).
- `AttributeOption` (Phase 1, `attribute_options` table) has a single global `label`.

Resolved decisions (clarification):
- **Scoped product values → new companion** `ProductScopedAttributeValues` in `catalog-attribute-scope`
  (own `scoped_attribute_values` JSON column, shape `{signature: {code: value}}`); Phase-2 package
  untouched. Resolution falls back to the Phase-2 global value.
- **Translatable option labels are IN scope** (Phase 3): `AttributeOptionScopedLabels` companion in
  `attribute-scope` + a scoped label resolver.
- **Static / Column-backed attributes scoped "manually" via the existing mechanism**: the scoped
  accessor resolves Column-backed attributes through the generic `scope.ScopeResolver` over the
  native property (reflecting a manually-set `ProductScopedOverrides` override if present, else base
  column). No new static-scoping path; soft integration (no hard `catalog-scope` dependency — the
  generic resolver discovers whatever `HasScopes` companion exists).
- Axes per attribute live in `config['axes']` (list of axis names); resolution is PHP-side; both new
  companions implement `HasScopesInterface` so `ScopeWalker` resolves them.

## Scope

### In Scope
- New `markommerce/attribute-scope`: `AttributeOptionScopedLabels` companion + `ScopedOptionLabelResolver`.
- New `markommerce/catalog-attribute-scope`: `ProductScopedAttributeValues` companion +
  `ScopedProductAttributeAccessor` (setScoped/getScoped/resolve) with global + Column fallback.
- Validation/cast of scoped values via the Phase-1 validator; guards (non-scopable → reject).
- Module wiring (bind resolvers/accessors; companions linked); integration tests; READMEs.

### Out of Scope
- Read model / index / faceting / search (Phases 4–6) — incl. SQL-side COALESCE resolution (PHP-side only here).
- Scoping entities other than `Product` for values (the kernel option-label path is generic; product values are Product-only).
- Admin UI / API / storefront.
- A new static-attribute scoping path (statics reuse catalog-scope's existing native-field overrides).
- Changes to the Phase-1/2 packages (Phase 3 is additive).

## Success Criteria
- [ ] Set a scoped Json value for a product attribute under a signature; `resolve` returns it under a matching scope and the Phase-2 global value otherwise.
- [ ] Scoped resolution uses the kernel (`ScopeWalker`/`SignatureCandidateEnumerator`) with most-specific→global ordering.
- [ ] A select option's label resolves per scope (override → base label).
- [ ] Setting a scoped value on a non-scopable attribute is rejected loudly; scoped values are validated/cast like global ones.
- [ ] Column-backed (static) attributes resolve their scoped value via the generic `scope.ScopeResolver` (native override → base column) **when a native-field scoping bridge is present** (catalog-scope + an axis-registering bridge like catalog-locale); absent that bridge the read falls back to the base column and a scoped write throws loudly. (Verified: `ScopeResolver` derives axes from `ScopedFieldRegistry`, which is populated only by catalog-locale/-market, both of which require catalog-scope.)
- [ ] New JSON columns merge into `catalog_products` / `attribute_options`; integration round-trips pass.
- [ ] All tests passing (unit + integration); coverage ≥ 80%; phpcs / phpstan level 8 clean.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `attribute-scope` + `catalog-attribute-scope` packages | - | completed |
| 002 | `AttributeOptionScopedLabels` companion (attribute-scope) | 001 | completed |
| 003 | `ScopedOptionLabelResolver` (attribute-scope, via ScopeWalker) | 001, 002 | completed |
| 004 | `ProductScopedAttributeValues` companion (catalog-attribute-scope) | 001 | completed |
| 005 | `ScopedProductAttributeAccessor` (setScoped/getScoped/resolve) | 004 | completed |
| 006 | Module wiring for both packages | 002, 003, 004, 005 | completed |
| 007 | Integration tests (scoped value + label round-trip, Column fallback) | 005, 006 | completed |
| 008 | READMEs for both packages + docs touch-up | 001-007 | completed |

## Architecture Notes
- **`HasScopesInterface` companions, custom column names** (avoid the `scopes` collision):
  - `AttributeOptionScopedLabels` (`#[Table(extends: AttributeOption::class)]`, `scoped_labels` JSON,
    shape `{signature: {'label': value}}`) implements `HasScopesInterface` with property `'label'`.
  - `ProductScopedAttributeValues` (`#[Table(extends: Product::class)]`, `scoped_attribute_values`
    JSON, shape `{signature: {code: value}}`) implements `HasScopesInterface` with property = code.
  - Implement the interface explicitly (NOT the `HasScopes` trait, which hardcodes `scopes`).
- **Resolution reuses `scope.ScopeWalker`** + `SignatureCandidateEnumerator` (both in `packages/scope`)
  — do NOT depend on config-scope's `OverrideMatcher`. Axes come from `config()['axes']` (label axes
  from the option's attribute definition).
- **`ScopedProductAttributeAccessor`**: `setScoped(Product, code, value, ScopeSignature)`,
  `getScoped(Product, code, ScopeSignature)`, `resolve(Product, code)` (active `ScopeContext`).
  - Guards: non-scopable definition → reject (loud); validate/cast via Phase-1 `AttributeValueValidator`
    (load options for select). Json-backed: ScopeWalker over the companion (axes from config) → override,
    else Phase-2 `ProductAttributeAccessor::get` (global). Column-backed: delegate to generic
    `scope.ScopeResolver::resolved($product, $config['property'])` (native scoped override → base column).
  - **Verified kernel signatures:** `ScopeWalker::walk(HasScopesInterface $overrides, string $property,
    list<string> $axes, ScopeContext $context): ScopeWalkResult` (result via `isFound()`/`value()`);
    `SignatureCandidateEnumerator::enumerate(list<string> $axes, ScopeContext): list<ScopeSignature>`
    reads paths from `ScopeContext::state()` and OMITs any axis not declared in the `ScopeRegistry`.
    The Json path needs NO `ScopedFieldRegistry` registration (axes come straight from `config['axes']`),
    but the axis MUST be a real declared axis in the scope registry or resolution silently never matches.
  - **Column-backed caveat (verified):** `ScopeResolver` derives axes from `ScopedFieldRegistry`
    (`axesForProperty`), populated only by catalog-locale/-market (which require catalog-scope). So
    `setOverride` on an unregistered native property THROWS `propertyNotScoped` and `resolved` returns
    the base column. The accessor must re-throw such writes loudly with an install-the-bridge suggestion;
    catalog-attribute-scope itself stays free of a catalog-scope `require`.
  - Does NOT persist — caller runs `ProductRepository->save($product)`.
- **No hard `catalog-scope` dependency**: Column-backed scoped resolution uses the generic
  `scope.ScopeResolver`, which finds whatever `HasScopesInterface` companion is present (catalog-scope's
  `ProductScopedOverrides` if installed); absent it, falls back to the base column.
- Companions auto-link via entity discovery; add an explicit `linkExtenders` in module boot so they
  also link under the integration-test harness (the lesson from Phase 2).
- Standards: PHP 8.5, no `final`, `declare(strict_types=1)`, constructor injection, `@throws`.

## Risks & Mitigations
- **`ScopeWalker`/`HasScopesInterface` API drift**: verify the exact `ScopeWalker::walk` + interface
  signatures against `packages/scope` at implementation time; cover with unit tests using a hand-set
  `ScopeContext` (`context->in(axis,path)`), mirroring `config-scope`'s resolver tests.
- **Column-name collision**: both new companions MUST use non-`scopes` column names; assert the column
  merges into the parent table in tests (mirror catalog-scope's `CompanionPersistenceTest`).
- **Column-backed scoping needs a HasScopes companion AND a scoped-field registration** (VERIFIED):
  static scoped *writes* require a native-field override companion (catalog-scope) AND the property to
  be registered in `ScopedFieldRegistry` (only catalog-locale/-market do this). Without registration,
  `ScopeResolver::setOverride` throws `propertyNotScoped` and `resolved` returns the base column.
  Static scoping is a soft integration — reads fall back to the base column when no bridge exists; do
  NOT hard-require catalog-scope. The Column integration scenario is therefore split out (see task 007).
- **Integration profile axis declaration** (VERIFIED): the `locale` axis comes from `markommerce/locale`;
  `SignatureCandidateEnumerator` silently OMITs any axis not declared in the scope registry. The task-007
  profile MUST root `markommerce/locale` and use `withLocales('default','en','de')` or scoped resolution
  never matches. Set the active scope via `BootedStore::inScope()` (handles `clearAll`).
- **Axes source**: `config['axes']` must list valid declared axes; a scoped write under an axis not in
  the attribute's axes (or a non-scopable attr) is rejected loudly.
- **Harness linking**: replicate Phase-2's explicit `linkExtenders` in boot so companions provision
  under the test harness.
