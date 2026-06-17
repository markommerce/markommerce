# Task 002: `ServedScopesProviderInterface` + cartesian provider (core)

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
In `markommerce/indexer`, define the contract that enumerates the scope signatures an index
materializes, and a default implementation producing the cartesian product of a GIVEN axis set's
paths (bounded), so indexers know which scopes to build rows for.

## Context
- Pattern: `packages/catalog-price-index/src/Contracts/IndexedMarketsProviderInterface.php` +
  `packages/catalog-price-index-market/src/ScopedIndexedMarketsProvider.php` (single-axis version) —
  this generalizes it to multiple axes. STUDY `packages/scope/src/Registry/ScopeRegistryInterface.php`
  (`listAxes()`, `getAxis()`, `getHierarchy()`), `ScopeHierarchy::paths()`,
  `Markommerce\Scope\Signature\ScopeSignature` (`fromArray`, `toString`), and CRITICALLY
  `packages/scope/src/Signature/SignatureCandidateEnumerator.php`.
- **CRITICAL — signatures are PER-ATTRIBUTE-AXIS, not a single global cartesian.** A given attribute
  declares only a SUBSET of axes (`AttributeDefinition::config()['axes']`). If the provider enumerated
  the global cartesian of ALL declared axes, it would (a) produce redundant rows for irrelevant axes
  and (b) DIVERGE from the reader's lookup signature (the reader computes candidates from the
  attribute's OWN axes — see task 008). Therefore `signatures()` MUST take the axis subset:
  `signatures(array $axes): list<ScopeSignature>` (`list<string> $axes`). The attribute indexer calls
  it once per attribute with that attribute's `config()['axes']`; the price indexer calls it with
  `['market']`.
- **Reuse the kernel enumerator, do NOT hand-roll the cartesian.** `SignatureCandidateEnumerator`
  already produces the exact bounded, walk-up, default-filtered, ksorted cartesian of `ScopeSignature`s
  that the live resolution path (`ScopeWalker::walk`) consumes, with a built-in cap (default 256) and
  an `E_USER_WARNING` when capped. The default provider should DELEGATE to it (or replicate its
  semantics precisely) so the materialized signatures are byte-identical to what the reader/live path
  enumerate. `SignatureCandidateEnumerator::enumerate(array $axes, ScopeContext $context)` enumerates
  from a context's active state; the served-scopes provider instead needs the FULL set of declared
  paths per axis (`ScopeHierarchy::paths()`, minus each axis `default`), so build the cartesian over
  those declared paths and construct `ScopeSignature` objects the same way (ksorted axis→path map).
- Place in `packages/indexer/src/`.
- `ServedScopesProviderInterface::signatures(array $axes): list<ScopeSignature>` — the non-empty
  signatures to materialize for the given axis subset (NOT including the base/global pass; the base
  pass is the empty-string `''` signature handled by the indexer separately — note `ScopeSignature`
  CANNOT represent the empty signature, its constructor throws `InvalidSignatureException` on `[]`).
- `CartesianServedScopesProvider implements ServedScopesProviderInterface`: inject
  `ScopeRegistryInterface`; for the given `$axes`, enumerate non-default declared paths per axis
  (`getHierarchy($axis)->paths()` minus `getAxis($axis)->default`), build the cartesian product,
  map to `ScopeSignature`s. **Bound the result** with a documented cap (typed constant, e.g.
  `MAX_SIGNATURES`); when the product would exceed it, `log()`/`trigger_error` what was dropped (no
  silent truncation) and return the capped set. Return `[]` when `$axes` is empty (global-only).
- Overridable via `#[Preference]` downstream (a domain may supply a narrower "served" set).

## Requirements (Test Descriptions)
- [ ] `it returns an empty signature list when given no axes`
- [ ] `it returns one signature per non-default path for a single given axis`
- [ ] `it returns the cartesian product of paths across multiple given axes`
- [ ] `it excludes each axis default path from the signatures`
- [ ] `it produces signatures whose toString matches ScopeSignature ksorted serialization`
- [ ] `it caps the signature count and logs when the cartesian product exceeds the maximum`

## Acceptance Criteria
- `signatures(array $axes)` takes the axis subset; cartesian over the GIVEN axes only (per-attribute),
  documented bound with a loud log when capped.
- Produced `ScopeSignature::toString()` values are byte-identical to what `SignatureCandidateEnumerator`
  / `ScopeWalker` produce for the same axis+path (ksorted `axis:path|axis:path`) — verified by test.
- No dependency on any domain (catalog/attribute) package — pure scope-kernel consumer.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
