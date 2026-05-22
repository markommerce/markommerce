# Task 029: Middleware collects dynamic handles + runtime tree merge

**Status**: completed
**Depends on**: 027, 028
**Retry count**: 0

## Description
After the base handle is matched, run the `HandleProvider`s declared on its `PreparedTree`, collect their returned handle keys, look up each one in the artifact, and merge them into the base tree at render time. The merge produces a single in-memory `PreparedTree` for the renderer — no changes needed in the renderer itself.

## Context
- Touch points:
  - `packages/layout/src/Middleware/MarkommerceLayoutMiddleware.php` — invoke handle providers, resolve their `props`, build the active handle list.
  - New `packages/layout/src/Runtime/TreeMerger.php` — pure function: `merge(PreparedTree $base, list<PreparedTree> $additions): PreparedTree`.
  - `Renderer::render()` continues to receive one `PreparedTree`.
  - The renderer's `buildContextMap()` already loops over `$tree->context`. As long as `TreeMerger` concatenates context entries from dynamic trees onto the base tree's `$context`, no renderer change is needed for dynamic-handle context providers — they will be picked up in Phase 0 just like the base's.
- Resolution order per request:
  1. Match route → base handle key.
  2. Read artifact, fetch base tree. If no base tree exists, fall through to `$next($request)` (existing behavior, unchanged).
  3. If the base tree declares no `handleProviders`, skip steps 4–6 and proceed to step 8 with the base tree as the only input (fast path / behavior identical to today).
  4. Build a partial context map by running the base tree's `ContextProvider`s (mirroring the renderer's Phase 0 — but using a dedicated context-resolver helper so this is not duplicated). For each `ProvideHandle` on the base tree, resolve `props` against this context map, instantiate the provider via the container, call `provide()`, accumulate returned handle keys.
  5. Deduplicate the dynamic handle list, preserving first-seen order.
  6. For each dynamic handle key, fetch its tree from the artifact. Missing key → throw `UnknownDynamicHandleException` (defined in task 023).
  7. Fold all dynamic trees into the base via `TreeMerger`.
  8. Render the merged tree.
- Merge rule: dynamic-handle placements are **appended** to the base tree's matching slots. Dynamic-handle operations apply on top of base placements. Context providers from dynamic handles are concatenated onto the base's context list (so they run in the renderer's Phase 0 alongside base context providers). Duplicate tokens between base and dynamic are caught in task 030's validation pass.
- A dynamic handle's tree was already compile-time merged with `default` and its `inherits:` chain in earlier tasks. The merger here only combines the request's collected trees.
- Dedup semantics: duplicate handle keys returned across multiple providers are deduplicated silently — the same handle's tree is idempotent (re-applying its operations produces the same outcome only when the operations are themselves idempotent; document this as a "dedup is required, do not bypass it" rule). There is no operation-merge between dynamic-handle returns from different providers.
- A `HandleProvider::provide()` that throws propagates as-is — the middleware does not catch.

## Requirements (Test Descriptions)
- [ ] `it invokes each HandleProvider declared on the base tree with resolved props`
- [ ] `it merges every returned handle's tree into the base tree before rendering`
- [ ] `it appends dynamic-handle placements to base slot entries`
- [ ] `it preserves declaration order when multiple providers return overlapping handles`
- [ ] `it deduplicates handle keys returned by multiple providers`
- [ ] `it renders the base tree unchanged when the base tree declares no handleProviders`
- [ ] `it falls through to next middleware when no base tree exists in the artifact (unchanged from existing behavior)`
- [ ] `it concatenates dynamic-handle context providers onto the base tree context so the renderer phase 0 picks them up`
- [ ] `it throws UnknownDynamicHandleException when a provider returns a handle key not present in the artifact`
- [ ] `TreeMerger::merge is idempotent for identical additions (sanity: merging the same dynamic tree twice produces the same result as merging it once when the base/addition are constructed as such)`

## Acceptance Criteria
- All requirements have passing tests
- Renderer changes are minimal (ideally zero — the merger does the work)
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in during implementation)
