# Devil's Advocate Review: markommerce-layout (tasks 023–032)

## Critical (Must fix before building)

### 1. Task 023's exception list is incomplete vs. tasks 026–030
Tasks 026, 028, 029, and 030 reference exception types that 023 never defines, and 023 only lists four. Concrete gaps:
- **Task 026** says "Duplicate context tokens [during inherits merge] are a compile error (raises `LayoutException` — reuse existing context-conflict exception or add one in 023 if missing)." Neither exists in 023 today and `UnknownContextException` (the only existing context exception) is for *missing* tokens, not *duplicates*. Add `DuplicateContextTokenException` to 023.
- **Task 028** says "Loud error at compile time if attempted" for chained handle providers — no exception is defined in 023 to cover this. Add `ChainedHandleProviderException` to 023.
- **Task 029** says missing dynamic-handle key throws "`UnknownDynamicHandleException` or reuse 023's `DynamicHandleConflictException`" — "missing handle" and "conflict" are different errors. Add `UnknownDynamicHandleException` to 023.
- **Task 028** says provider class not implementing `HandleProvider` throws `InvalidLayoutFileException`. `InvalidLayoutFileException` exists but its current static factory `forWrongType()` is about file-return-type mismatches. Add a `forInvalidHandleProvider()` factory there, or add a dedicated exception in 023.

**Fix**: extend 023 to include the five missing exceptions/factories.

### 2. Canonical resolution order is contradicted across tasks 025/026/027
The three tasks describe partial orders that, when stacked, do not agree:
- Task 025: extends → own ops → extensions
- Task 026: extends → inherits → own ops → extensions
- Task 027: extends → inherits → default-prepend → own ops → extensions

But task 025's tests (`it applies layout operations before applying extension-file operations`) and 025's "implicit priority 0" rule assume that `own operations` step is fixed before tasks 026/027 layer in. A worker building 025 first may write tests that 026 then has to rewrite.

**Fix**: declare the canonical resolution order in `_plan.md` once, and reference it from 025/026/027. Update 025's requirements to leave room for inheritance/default merge steps that 026/027 insert; update 026/027 to confirm they only inject one step into a fixed order.

### 3. Default-handle merge interacts with handle-inheritance in undefined ways
Task 027 says "default merge happens AFTER each sibling's extends: and inherits: are resolved." But task 026 resolves inheritance *recursively* — child inherits parent's resolved tree. If default is merged into every handle, then:
- Either the parent already has default merged when child inherits it → default placements appear twice (once via inheritance, once via own merge),
- Or default is excluded from the inheritance-merge step → child sees parent's *pre-default* tree, which contradicts "every handle has default merged."

**Fix**: specify in task 027 that default merging is applied **only once, at the end of compilation**, against each handle's already-resolved (post-inherits, post-own-ops, post-extension-ops) tree. Update task 026 to clarify that the inherits merge sees the parent's resolved-but-not-yet-default-merged tree. Equivalently, hold default merge as the very last compile step before artifact emission and operate over the in-memory ResolvedLayout map, not over the parent during inherits resolution.

### 4. Task 029 "fall through" requirement is broken
Requirement: "*it falls through to next middleware when a base tree declares no providers and the artifact has no base entry*"

The middleware *already* falls through when there is no base entry (`!isset($artifact[$handleKey])` in `MarkommerceLayoutMiddleware`). The new code path runs when the base entry exists. The condition as written is incoherent: "no providers AND no base entry" can never both fall through differently from existing behavior.

**Fix**: replace this requirement with what's actually meant: "*it renders the base tree unchanged when the base tree declares no providers*" (i.e., the new provider-collection path is a no-op when `handleProviders` is empty).

### 5. Task 028 needs to extend PreparedTree and the artifact emitter
Task 028's context says "Compiled artifact: each PreparedTree carries its declared ProvideHandle list as a sidecar." But neither `PreparedTree` nor `PhpCodeEmitter` is listed in touch points or test cases, and the existing artifact emitter is a closed registry — adding an unsupported value object type throws `RuntimeException` from `PhpCodeEmitter`. A worker building 028 may add the field and not touch the emitter, breaking compile.

**Fix**: explicitly enumerate in 028:
- Add `handleProviders: list<ProvideHandle>` to `PreparedTree`.
- Register `ProvideHandle` in `PhpCodeEmitter` via a new emit case (mirror `emitProvide`).
- Add a test that the artifact serializes/deserializes a `PreparedTree` with handle providers round-trip.

### 6. Task 029 doesn't run dynamic-handle context providers
Resolution order in 029 step 3 runs `ContextProvider`s for the *base* tree, then step 4 invokes `HandleProvider`s, then step 7 merges dynamic trees. But dynamic handles can carry their own `Provide` entries (task 028 doesn't forbid it). After merging dynamic trees, those new context providers are never run → any dynamic placement that depends on a dynamic context token resolves with a missing token at render time.

**Fix**: either (a) document that dynamic handles cannot carry `Provide` entries and add a compile-time validation in 028 or 030, or (b) re-run context providers from dynamic trees after merge and before render. Option (a) is simpler in v1 and matches the "providers run AFTER ContextProviders" constraint; add it as a requirement in 030.

## Important (Should fix before building)

### 7. `ProvideHandle::props` resolution context is undefined
Task 028 says props are resolved by `SourceResolver`. But `ResolutionContext` requires `parentData`, `iterationItem`, `placementChain`, etc. Handle providers run after context providers but before any placement render — there is no parentData, no iteration. Allowed source types must therefore be a strict subset (`route`, `query`, `context`, `service`, literal). `Source::parentData`/`Source::iterated` are nonsensical here.

**Fix**: add to task 028 requirements: "rejects `parentData` and `iterated` sources in `ProvideHandle::props` at compile time, with `InvalidSourceTypeException`." Specify the synthetic `ResolutionContext` used (no parentData, no iterationItem, no parent chain).

### 8. Layout files with `handle: 'default'` will appear in the artifact unless explicitly stripped
`LayoutDiscovery::discover()` will pick up a `default.php` layout file just fine; `ResolutionPhase::resolve()` currently only skips layouts with `handle === null`. Without a stripping step, `array<handleKey, PreparedTree>` will have a `'default'` entry. The middleware will never match `'default'` against a route, so it's harmless functionally — but task 027's "it omits the default key from the final runtime artifact" requirement will fail unless someone removes it.

**Fix**: add to task 027 implementation notes / context: "After applying default merge to every other handle, remove the `'default'` entry from the resolved-layouts map before artifact emission."

### 9. Task 026 doesn't specify interaction between `extends:` and `inherits:` on the same Layout
A single layout can plausibly declare both: `extends: OneColumnLayout::class` (structural shell) and `inherits: 'other_handle'` (sibling handle's resolved tree). The merge interaction needs spec — is it extends-then-inherits-then-own-ops, or inherits-first? The slot-merge rule (parent appended, child appended) gets weird if the structural shell already has content for the same slot.

**Fix**: add explicit ordering to task 026 context: "Per-handle resolution order: (1) resolve own `extends:` chain (structural shell), (2) resolve `inherits:` parent handle recursively, (3) append parent handle's slot entries on top of shell entries, (4) append own slot entries, (5) merge context (parent first, then own), (6) apply own operations, (7) apply extension-file operations, (8) default-handle merge." Add a test that exercises both `extends` and `inherits` on the same layout.

### 10. Task 025's "own operations run at priority 0" rule conflicts with existing same-priority detection
`ResolutionPhase::checkConflicts()` throws `ExtensionConflictException` when two operations at the same priority touch the same anchor. If layout's own operations run at "implicit priority 0" *and* an extension file also runs at priority 0 *and* both target the same name, this would falsely trip a conflict — even though the design intent is "own ops run first, then extensions." The current conflict check is per-priority-group; running own ops as a separate pre-pass avoids this.

**Fix**: add to task 025 implementation notes: "own operations are applied as a dedicated pass *before* extension priority sorting, not as a priority-0 member of the extension group. The 'implicit priority 0' phrasing in the task description is wording-only; do not insert own ops into the priority-grouped queue."

### 11. Task 027 must say default cannot declare `handleProviders` (or must say it can)
Task 027 forbids `extends:` and `inherits:` on default. It is silent on `handleProviders:`. Allowing default to declare handle providers means every page acquires those providers — likely desirable, but it doubles the implementation surface and complicates 029's "providers on the base tree" wording.

**Fix**: add to task 027 a requirement: either "forbids handleProviders on the default handle" (throw DefaultHandleConflictException) **or** "default handle's handleProviders are propagated to every sibling tree's handleProviders list at merge time" — pick one. Recommendation: forbid in v1, defer to v2.

### 12. Task 030's static-vs-runtime split needs a concrete signal
Task 030 says "for each ProvideHandle whose returned handle list can be inferred (because the provider class declares it in PHP attributes or a constant), validate the pairwise conflict at compile time." Neither a constant convention nor an attribute is defined anywhere. A worker has no hook to read.

**Fix**: add to task 030 context: "static-known providers expose a `public const string[] STATIC_HANDLES = […]` or a `#[ProvidesHandles('a', 'b')]` attribute. Pick one in implementation; document the chosen mechanism." Alternatively, defer the static-conflict detection to a follow-up and keep 030 entirely runtime — then 030 shrinks to "record base placement names in PreparedTree for runtime collision check."

### 13. `ProvideHandle` value object location & symmetry with `Provide`
Task 028 doesn't say where `ProvideHandle` lives. `Provide` lives at `packages/layout/src/Provide.php` (root namespace). `HandleProvider` interface lives at `packages/layout/src/Contracts/HandleProvider.php`. Make `ProvideHandle` follow `Provide`'s location, not the contracts dir.

**Fix**: add to task 028: "`ProvideHandle` value object lives at `packages/layout/src/ProvideHandle.php` in `Markommerce\Layout\` namespace, mirroring `Provide`."

### 14. Task 029 dedup + ordering semantics for multi-provider returns
Step 5 says "deduplicate, preserving first-seen order." But step 7 says "fold all dynamic trees" — if two providers return the same handle key, the second is dropped after dedup; the resulting merge order is deterministic but tied to *provider declaration order*. Test case "it preserves declaration order when multiple providers return overlapping handles" only confirms order, not what happens to the dropped duplicate's operations. Are they discarded silently? That's a silent-failure violation of the loud-error contract.

**Fix**: clarify in task 029: "duplicate handle keys returned across providers are deduplicated silently — this is intended, because the same handle's tree is idempotent. There is no operation-merge between dynamic-handle returns from different providers."

### 15. Existing layouts in catalog and theme-blank don't break, but task ordering risks rework
The existing `category_show.php` and (eventual) theme-blank LayoutDefinitions don't declare `inherits` or `operations` — task 024's backward-compat requirement covers them. But if task 024 changes `Layout`'s constructor signature, any *positional* construction in tests will break. The task notes layout-demo's `layout_demo.php` (named args, safe). Quick check confirms catalog/category_show also uses named args. theme-blank layouts are `LayoutDefinition` classes — they return `new Layout(...)` too, with named args. So positional risk is low — but the task should call this out so workers don't grep for positional calls and miss the audit.

**Fix**: task 024 already covers this in context line; expand requirements to include "grep packages/*/layout/ and packages/*/src/Layout/ for any `new Layout(` calls and verify all use named arguments before adding fields."

### 16. CompileIfStaleMiddleware does not need changes — confirm in plan
The existing middleware globs `packages/*/layout/*.php` and `packages/*/layout/extensions/*.php`. New default/inheritance/dynamic-handle files all live under `packages/*/layout/`, so stale detection picks them up automatically. The plan should explicitly state "no change to CompileIfStaleMiddleware is required for tasks 023–032" so a worker doesn't speculatively edit it.

**Fix**: add a note to `_plan.md` "Added scope" section confirming no middleware change is required.

### 17. Task 031 demo must exercise failure modes the requirements list ignores
Task 031 lists three happy-path renders and one "raises a clear error if layout:compile fails." None of:
- circular inheritance,
- unknown parent handle,
- default declaring extends/inherits,
- chained handle providers,
- cross-handle placement name collisions

…are exercised. The plan's success criteria for tasks 023–030 require throwing each named exception with location + suggestion, but if the demo never tickles them, integration coverage is missing.

**Fix**: add to task 031 a `tests/Feature/HandleSystemFailureModesTest.php` that constructs broken layouts (via temp directories or test fixtures) and asserts each new exception is thrown. Or move that coverage into the per-feature task (026/027/028/030) and document in 031 that failure-mode coverage lives there.

### 18. Task 032 byte-for-byte requirement is unrealistic
"Every code example in the new section matches a file in packages/layout-demo/ byte-for-byte" — docs typically reformat or excerpt examples (e.g., elide unrelated slots). Strict byte-for-byte is impractical and will cause CI churn.

**Fix**: weaken to "every code example is a verbatim excerpt or full copy of a file in packages/layout-demo/, with line-comment-only differences allowed." Or drop the matcher entirely and rely on docs review.

## Minor (Nice to address)

### 19. `'default'` as magic string
The string literal `'default'` is sprinkled across compiler, validation, and runtime. Promote to a class constant `Layout::HANDLE_DEFAULT = 'default'` (or similar) for type-safety, refactor safety, and grep-ability.

### 20. `Layout::$handle === null` semantics
With `inherits:` added, the existing skip-rule (`$layout->handle === null` is non-routable shell) becomes subtler — a parent referenced by `inherits:` is a *named* handle, not a handle-less shell. Confirm in 026 that `inherits:` lookup is by handle string only, not by `LayoutDefinition` class. (It is, per the task text — just noting.)

### 21. Task 028's "chaining forbidden" check is in the wrong task
Task 028 forbids chained handle providers. But "chained" can only be detected after all layouts are resolved (a handle provider returns handle X; X's resolved tree itself declares handleProviders). That cross-handle check belongs in 030 (which already has "validation also covers: a dynamic handle that itself declares handleProviders"). Task 028 can only enforce the local check: a `Layout(handle: …, handleProviders: …)` is fine; checking that the *target* of a provider doesn't itself declare providers is a 030 concern.

### 22. Task 029's TreeMerger should be tested with idempotency
If merging the same dynamic tree twice (e.g., via two providers returning the same key, before dedup) would produce duplicate placements, the merger's idempotency matters. The dedup step in 029 protects against this — but a unit test on `TreeMerger::merge()` itself with the same addition twice would catch regressions if the dedup step ever moves or is bypassed.

## Questions for the Team

1. **Should `'default'` be a typed sentinel?** Magic string vs `Layout::HANDLE_DEFAULT` constant vs dedicated `DefaultHandle` sentinel object. Recommendation: class constant on `Layout`.

2. **Should the default handle support `handleProviders`?** Forbidding it is simpler; allowing it makes "site-wide dynamic handles" possible (e.g., authenticated-user handle on every page).

3. **Static-handle discoverability for compile-time validation in task 030**: PHP attribute (`#[ProvidesHandles('a', 'b')]`) vs class constant (`public const STATIC_HANDLES = […]`)? Recommendation: PHP attribute for richer metadata.

4. **Should dynamic handles be allowed to carry their own `Provide` entries?** Allowing them means re-running context providers post-merge (timing complexity). Forbidding them keeps the resolution pipeline linear. Recommendation for v1: forbid; defer.

5. **Task 031 failure-mode coverage location**: in the demo (integration-style) or in per-feature tasks (unit-style)? Recommendation: per-feature unit tests in 023/026/027/028/030; demo focuses on happy paths.
