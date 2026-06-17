# Task 005: `ScopedProductAttributeAccessor` (catalog-attribute-scope)

**Status**: complete
**Depends on**: 004
**Retry count**: 0

## Description
The scope-aware read/write entry point for product attribute values: set/get a value for an explicit
signature, and `resolve` a value for the active scope. Json-backed values use the new companion +
kernel `ScopeWalker` with global fallback; Column-backed (static) values delegate to the generic
`scope.ScopeResolver` over the native property.

## Context
- Place in `packages/catalog-attribute-scope/src/ScopedProductAttributeAccessor.php`.
- Inject: `ProductAttributeDefinitions` (Phase 2 resolver — concrete `AttributeDefinition`),
  `AttributeValueValidator` (Phase 1), `AttributeDefinitionRepositoryInterface` (Phase 1, `optionsFor`),
  `ProductAttributeAccessor` (Phase 2, for the global fallback `get`), and the kernel
  `Markommerce\Scope\Resolution\ScopeWalker` + `Markommerce\Scope\Context\ScopeContext` +
  `SignatureCandidateEnumerator`, plus the generic `Markommerce\Scope\Resolver\ScopeResolver` (for the
  Column-backed path). VERIFY exact kernel signatures before coding.
- **VERIFIED kernel signatures (do not re-derive):**
  - `ScopeWalker::walk(HasScopesInterface $overrides, string $property, list<string> $axes, ScopeContext $context): ScopeWalkResult`
    — note the parameter is named `$overrides`, takes ANY `HasScopesInterface`, and reads the active
    scope from the passed `ScopeContext`; result via `$result->isFound()` / `$result->value()`.
  - `SignatureCandidateEnumerator::enumerate(list<string> $axes, ScopeContext $context): list<ScopeSignature>`
    — it reads axis paths from `ScopeContext::state()` and walks the axis hierarchy. **An axis that is
    NOT registered in the `ScopeRegistry` is silently treated as OMIT (never matches).** So for the
    Json path you do NOT need `ScopedFieldRegistry` registration, BUT the axis MUST be a real declared
    axis in the scope registry (e.g. `locale` provided by `markommerce/locale`) or resolution silently
    never matches. Unit tests must build a `ScopeRegistry`/`ScopeContext` with the axis declared.
- Axes for an attribute come from `$definition->config()['axes']` (list of axis names).
- **setScoped(Product $product, string $code, mixed $raw, ScopeSignature $signature): void**:
  resolve def (guards: not found → `AttributeDefinitionNotFoundException`; `entityType() !== 'product'`
  → reject; **`scopable` is false → reject loudly**). Gate option loading on TYPE (select/multiselect
  → `optionsFor`); `validate($def, $raw, $allowedOptions)`. For `Json` backing → companion
  `ProductScopedAttributeValues::setOverride($signature->toString(), $code, $value)` (attach the
  companion if absent).
- **Column-backed path — VERIFIED PREREQUISITE (was a latent break):** `ScopeResolver` derives axes
  from `ScopeMetadataFactory->for(Product::class)->axesForProperty($config['property'])`, which is
  EMPTY unless the native property is registered as scoped in `ScopedFieldRegistry` (via `#[Scoped]`
  or a programmatic `register()`). In this repo ONLY `catalog-locale`/`catalog-market` register
  Product native fields (`name`/`description` on `locale`, `priceAmount` on `market`), and those
  modules require `catalog-scope` (the `scopes` storage companion). Consequences:
  - `ScopeResolver::setOverride($product, $config['property'], …)` THROWS
    `ScopeContextException::propertyNotScoped` when the property is not registered, AND
  - `ScopeResolver::resolved($product, $config['property'])` returns the BASE column (empty axes →
    no candidates) — it cannot resolve any override.
  So the Column-backed scoped path only functions when `catalog-scope` + the axis-registering bridge
  (e.g. `catalog-locale`) are installed AND the native field is registered. This stays a SOFT
  integration: catalog-attribute-scope must NOT hard-depend on catalog-scope.
  - For `Column` backing in `setScoped` → delegate to `ScopeResolver::setOverride($product,
    $config['property'], $value, $signature)`. Wrap so a `ScopeContextException` for an
    unregistered/unscoped property is re-thrown LOUDLY (message/context/suggestion) telling the
    merchant to install a native-field scoping bridge (catalog-scope + catalog-locale/-market); do not
    silently swallow it.
- **getScoped(Product, code, ScopeSignature): mixed** — explicit-signature read. For `Json` backing
  read the companion directly via `$companion->override($signature->toString(), $code)` (returns the
  stored override or `null`); do NOT use the ambient-context `walk` here. For `Column` backing,
  `ScopeResolver::resolvedAt($product, $config['property'], $signature)`.
- **resolve(Product, code): mixed** — active-scope read. `Json`: `ScopeWalker::walk($companion, $code,
  $axes, $context)`; on a hit return the override, else the Phase-2 global value
  (`ProductAttributeAccessor::get($product, $code)`). `Column`: `ScopeResolver::resolved($product,
  $config['property'])` (native scoped override if the field is registered+stored → else base column).
- Does NOT persist — caller runs `ProductRepository->save($product)`.

## Requirements (Test Descriptions)
- [x] `it sets and reads a scoped Json value for a product attribute by signature`
- [x] `it resolves the scoped Json override under a matching active scope`
- [x] `it falls back to the global Phase-2 value when no scoped override matches`
- [x] `it validates and casts a scoped value via the attribute validator before storing`
- [x] `it rejects setting a scoped value on a non-scopable attribute`
- [x] `it resolves a Column-backed attribute via the generic scope resolver over the native property` (unit test must register the native property as scoped in ScopedFieldRegistry first, mirroring scope/tests/Unit/Resolver/ScopeResolverTest.php; otherwise axes are empty and resolution always returns the base column)
- [x] `it re-throws loudly when a Column-backed scoped write targets a property with no scoped-field registration`

## Acceptance Criteria
- Scoped resolution uses the kernel `ScopeWalker` (most-specific→global) with Phase-2 global fallback.
- Non-scopable scoped writes rejected loudly; scoped values validated/cast like global ones.
- Column-backed path delegates to the generic `scope.ScopeResolver` (no hard catalog-scope dependency).

## Implementation Notes
- `ScopedProductAttributeAccessor` placed at `packages/catalog-attribute-scope/src/ScopedProductAttributeAccessor.php`
- Injects `ProductAttributeDefinitions`, `AttributeValueValidator`, `AttributeDefinitionRepositoryInterface`, `ProductAttributeAccessor`, `ScopeWalker`, `ScopeContext`, `ScopeResolver`
- `setScoped`: guards (notFound→throw, notProduct→throw, notScopable→throw ScopeContextException), validates+casts, Json→companion setOverride, Column→ScopeResolver::setOverride (wraps ScopeContextException with loud re-throw)
- `getScoped`: Json→companion override() (null if no companion), Column→ScopeResolver::resolvedAt()
- `resolve`: Json→ScopeWalker::walk() then ProductAttributeAccessor::get() fallback, Column→ScopeResolver::resolved()
- Test for Column path registers `Product::name` in `ScopedFieldRegistry` and attaches `ProductScopedAttributeValues` companion (mirrors scope package ScopeResolverTest pattern)
