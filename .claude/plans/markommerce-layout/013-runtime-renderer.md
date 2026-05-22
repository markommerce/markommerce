# Task 013: Runtime Renderer

**Status**: completed
**Depends on**: 003, 006, 011, 012
**Retry count**: 0

## Description
Build the renderer that turns a `PreparedTree` into HTML. It runs the two-phase walk: phase 1 collects every placement's `data()` result, phase 2 renders templates — iterating repeat slots, inlining sub-slots, and applying decorators.

## Context
- Input: a `PreparedTree` (task 011) for the matched handle, plus a `Request` and route parameters.
- **Phase 0 — context**: run each `Provide`'s `ContextProvider`, resolving its props via the source resolvers (task 012). Collect results into a context map `array<class-string, object>` keyed by token. Providers run once per request.
- **Phase 1 — data collect**: walk the tree depth-first. For each placement: resolve every prop via the source resolvers, instantiate the component **via the DI container** (`$container->get(...)` — NOT `new`; container resolution is what applies Marko Plugin interception, which the extension story in task 003 depends on), call `data(...)` with resolved props. Memoize the returned DTO on the node. A child's `parentData` source reads the *already-memoized* parent DTO — so parents are resolved before children. Repeat slots: the parent placement's `data()` runs once; the repeat slot's `dataKey` property is the item list; iterate it, and for each item run phase 1 for the repeat children with that item set as the iteration value in `ResolutionContext`.
- **`parentData` ordering — make the contract airtight**: "parent" for a `Source::parentData` means the *immediate enclosing placement* in the tree (the placement whose slot contains the current placement), NOT the routable layout root and NOT a repeat-iteration item. Phase 1's depth-first walk MUST memoize a placement's DTO *before* descending into any of its slots, so every child sees a populated parent DTO. For a repeat slot: the `parentData` of a repeat child resolves against the placement that *owns the repeat slot* (its single memoized DTO), while `Source::iterated` resolves against the per-item value — these are two distinct parents and the renderer must keep them separate in `ResolutionContext`. A `parentData` source on a top-level placement (no enclosing placement) is a compile-time error — confirm task 010 rejects it, and defend at runtime with a loud error. Document the exact "immediate parent" definition in Implementation Notes.
- **Phase 2 — render**: walk again. For each placement render its component template via `ViewInterface::renderToString()` with the memoized data. Inline sub-slot HTML into `{slot name}{/slot}` placeholders (reuse `marko/layout`'s slot-tag convention). For repeat slots, concatenate the per-item rendered children. For wrapped placements, render the inner placement first, then render the decorator template with the inner HTML supplied as `{slot inner}` per `DecoratorInterface` (task 006). Decorator chains apply innermost-first.
- The data DTO is passed to the template such that `$extensions` (the `ExtensionBag`) and the DTO's public properties are both accessible — decide the binding (e.g. spread DTO public props + an `extensions` key). Document in Implementation Notes.
- Output: a `Response` (HTML) — or just the assembled HTML string, with `Response` construction left to the middleware (task 016). Decide and document.
- Marko Plugins on component `data()` methods must still fire — instantiating the component via the DI container should preserve plugin decoration. Verify in a Feature test (this is the runtime half of task 003's extension story).
- Patterns to follow: `marko/layout/src/LayoutProcessor.php` `renderSlot()` + nested-slot `str_replace` approach; `ComponentDataResolver` is replaced by task 012's resolvers.

## Requirements (Test Descriptions)
- [x] `it runs context providers once and exposes results in the context map`
- [x] `it collects data for every placement in phase one`
- [x] `it makes parent data available to a child parent-data source`
- [x] `it renders a single placement to HTML`
- [x] `it inlines sub-slot HTML into a parent template slot placeholder`
- [x] `it iterates a repeat slot rendering children once per item`
- [x] `it exposes the iteration item to repeat-slot children`
- [x] `it wraps a placement with a decorator supplying inner HTML`
- [x] `it applies a chain of decorators innermost first`
- [x] `it renders an extension field added to a component DTO via a Marko plugin`
- [x] `it renders an empty repeat slot as no output`

## Acceptance Criteria
- All requirements have passing tests
- Two-phase ordering guarantees parent data is memoized before any child resolves `parentData`
- Marko plugin decoration of `data()` is preserved at render time
- Template data binding and return type (HTML vs Response) documented in Implementation Notes

## Implementation Notes

### Output convention
The renderer returns a raw HTML string. The middleware (task 016) wraps it in a `Response`.

### Template data binding
For each placement, `ViewInterface::renderToString()` receives an array built as follows:
- All **public properties** of the data DTO are spread into the array (e.g. `['title' => 'Hello', 'extensions' => ExtensionBag{...}]`).
- When the DTO extends `ExtensibleData`, the `extensions` key is already present in the spread since `extensions` is a public property of `ExtensibleData`.
- A `_slots` key is added mapping each slot name to `true` — templates can use this to conditionally include slot placeholders.
- Slot HTML is injected by replacing `{slot name}{/slot}` placeholders in the rendered template string.

### "Immediate parent" definition for `parentData`
"Parent" means the **immediate enclosing placement** in the tree — the `PreparedPlace` whose `slots` array contains the current placement. This is NOT the layout root, and NOT a repeat-iteration item (which is `Source::iterated`). The depth-first phase-1 walk memoizes a placement's DTO **before** descending into any of its child slots, guaranteeing every child sees a populated parent DTO via `ResolutionContext::$parentData`.

For repeat slots: `$context->parentData` is the DTO of the placement that **owns** the repeat slot (unchanged across iterations), while `$context->iterationItem` is the per-item value. These are kept separate in `ResolutionContext`.

### Plugin interception
Components are resolved via `$container->get($place->component)`. This ensures Marko's `PluginInterceptor` wraps the component in a proxy, so any registered `after` plugins on `data()` fire at render time. Verified with a Feature test using the real `Container` + `PluginRegistry` + `InterceptorClassGenerator`.
