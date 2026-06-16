# Task 008: Live-fallback reader (`IndexedAttributeReader`)

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Implement the correctness-preserving reader: return the indexed resolved value for a product
attribute under the active scope when an index row is present, else fall back to live resolution via
the Phase-3 scoped accessor. This makes reads correct regardless of index freshness — the meta-plan's
headline property.

## Context
- Place in `packages/catalog-attribute-index/src/IndexedAttributeReader.php`.
- Inject: `ProductAttributeIndexRepository` (task 006), `ScopedProductAttributeAccessor` (Phase 3),
  `ProductAttributeDefinitions` (for the attribute definition: type + `config()['axes']`),
  `Markommerce\Scope\Signature\SignatureCandidateEnumerator` (Phase-0 kernel — the SAME enumerator
  `ScopeWalker::walk` uses), and `ScopeContext`.
- **CRITICAL — signature lookup MUST mirror live resolution, not recompute a single signature.**
  The live path (`ScopedProductAttributeAccessor::resolve` → `ScopeWalker::walk`) does NOT match one
  signature; it enumerates ordered candidate signatures via
  `SignatureCandidateEnumerator::enumerate($def->config()['axes'] ?? [], $scopeContext)` (walk-up,
  default-filtered, ksorted, capped) and returns the FIRST override match. The reader MUST do the same
  against the index:
  - Compute `$candidates = $enumerator->enumerate($axes, $scopeContext)` (may be `[]` when no axes
    are active / attribute non-scopable).
  - For each candidate `ScopeSignature` in order, look up index rows by `$signature->toString()`; the
    FIRST candidate with a row wins. If none of the candidates has a row, fall back to the base `''`
    signature row.
  - This guarantees the reader returns the indexed value for exactly the same signature the live path
    would have resolved — otherwise reads silently fall through to the live accessor and the index is
    defeated. Add a test asserting reader-vs-live signature agreement.
- `resolve(Product $product, string $code): mixed`:
  - Resolve the candidate-signature list as above; find the first signature (then `''`) with index
    rows via the repository.
  - Multiselect: `value_kind === 'multiselect'` → COLLECT all member rows for that
    `(productId, code, signature)` (the repo `findValues(...)` returning a list) and reconstruct the
    array; single-valued kinds → one row, cast its typed column back per `value_kind`.
  - If NO row exists for any candidate or base (not indexed / stale / scope not materialized),
    delegate to `ScopedProductAttributeAccessor::resolve($product, $code)` (live) and return that.
- Read-only; never writes the index (no lazy reindex this phase — manual rebuild only).
- Document: this reader is the single read path that guarantees correctness while the index may be
  stale (manual-trigger model); Phase 5 faceting queries the index table directly for aggregates.

## Requirements (Test Descriptions)
- [ ] `it returns the indexed value when an index row exists for a candidate signature`
- [ ] `it walks candidate signatures in resolution order and returns the first matching row`
- [ ] `it falls back to the base empty signature row when no scoped candidate matches`
- [ ] `it reconstructs the typed value from value_kind and the typed columns`
- [ ] `it collects multiselect member rows into an array value`
- [ ] `it falls back to the live scoped accessor when no index row exists for any candidate`
- [ ] `it enumerates candidate signatures via SignatureCandidateEnumerator from the scope context`
- [ ] `it returns the same signature the live ScopeWalker path would resolve`

## Acceptance Criteria
- Candidate signatures enumerated via `SignatureCandidateEnumerator` (identical to the live path);
  first matching index row wins, else base `''`, else live fallback.
- Indexed value when present; live `ScopedProductAttributeAccessor::resolve` fallback when absent.
- Correctness is independent of index freshness (proven by a missing-row test that still returns the live value).

## Implementation Notes
(Left blank - filled in by programmer during implementation)
