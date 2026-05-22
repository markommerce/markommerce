# Plan: markommerce/layout

## Created
2026-05-22

## Status
completed

## Objective
Build a new `markommerce/layout` package — a typed, compile-validated, extensible layout system — to replace `marko/layout` for commerce pages. Components become placement-agnostic, slots support iteration, layouts are pure value-object trees, and third-party modules can add/remove/replace/wrap placements via a closed vocabulary of typed operations.

## Related Issues
none

## Discovery Notes

### Reference / prior art
- `marko/layout` (under `../marko/packages/layout`) — the system being replaced. Three concrete failures we are fixing: components are bound to one handle+slot at the class level (no reuse), data only flows from request/route (no parent→child props), slots are static lists (no iteration).
- `marko/cli` provides `#[Command]` + `CommandInterface`. Auto-discovered from any Marko module. New CLI commands plug in here — no new binary needed.
- `marko/core/Discovery/ClassFileParser` + `marko/core/Module/ModuleRepositoryInterface` already enumerate modules and parse files. Reused for layout auto-discovery.
- `marko/view/ViewInterface::renderToString()` is the rendering primitive. Unchanged.
- `markommerce/catalog`'s `ProductGridComponent` and `CategoryController` are the first real consumers and will be migrated in this plan.
- `markommerce/theme-blank` ships `OneColumnLayout` / `TwoColumnLayout` using `marko/layout`'s `#[Layout]` attribute today; both migrate to the new `LayoutDefinition` interface in this plan.

### Decisions locked during clarification
1. **Component data shapes are typed DTOs** (e.g., `ProductCardData`) extending a shared `ExtensibleData` base. Third-party modules add fields via the `ExtensionAttribute` pattern (`$data->extensions->get(ReviewStarsExtension::class)`), populated by Marko Plugins on `data()`. Reuses Marko's existing Plugin mechanism — no new extensibility primitive.
2. **`extends:` references a `LayoutDefinition` class**, not a string. The class implements `public static function define(): Layout` and is resolved at compile time. `OneColumnLayout` / `TwoColumnLayout` become `LayoutDefinition` implementations.
3. **Dev-middleware only — no separate watcher process in v1.** `CompileIfStaleMiddleware` stats source files on each request in dev mode and recompiles synchronously if any layout source is newer than the artifact. Watcher CLI deferred to a follow-up plan.
4. **Partial replacement of `marko/layout` in markommerce.** `markommerce/catalog` and `markommerce/theme-blank` are migrated off `marko/layout` in this plan and `marko/layout` is dropped from *their* composer dependencies. IMPORTANT: `marko/layout` is NOT fully removed from the monorepo — `markommerce/theme-blank-demo` and `markommerce/frontend-demo` still require it and still use its `#[Layout]`/`#[Component]` attributes (their migration is out of scope). So `marko/layout` stays installed and its global `LayoutMiddleware` stays active. The `marko` repo is on the **`local-develop`** branch, which added **module-declared global middleware**: a package's `module.php` may declare a `globalMiddleware` key (class-strings or `['class' => X, 'priority' => N]`), resolved by `Marko\Core\Module\GlobalMiddlewareResolver` (sorted by priority ascending, deduped by class). The old hardcoded `GLOBAL_MIDDLEWARE` const is gone — even `marko/layout`'s `LayoutMiddleware` now self-declares at priority 30. Therefore `MarkommerceLayoutMiddleware` IS a global middleware, declared via `markommerce/layout`'s own `module.php` at priority 30. It coexists with `marko/layout`'s `LayoutMiddleware`: for any route at most one renders — once `CategoryController` drops `#[Layout]`, `LayoutMiddleware` falls through, and `MarkommerceLayoutMiddleware` falls through for routes with no compiled layout. No per-route `#[Middleware]` wiring needed.
5. **Compiled artifact path:** `var/cache/markommerce/layouts.php` regenerated on deploy / on dev-middleware stale detection. NOTE: the project `.gitignore` has no `var/` entry today — task 011 (or task 001) must add `var/` to `.gitignore` so the artifact is not committed.

### Defaulted decisions (defaults documented; can be revisited in implementation if needed)
- **Iteration tokens** are empty marker classes with a `#[IteratesOver(Product::class)]` attribute the compiler reads.
- **Required props** are detected by `data()` parameters having no default value.
- **Placement name format**: regex `^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$` (e.g., `catalog.product_card`). Validated at compile.
- **Decorator contract**: a class implementing `DecoratorInterface` with `#[Component(template: …)]`. Template must include a `{slot inner}` placeholder. Decorators cannot rewrite the wrapped component's props.
- **Auto-discovery paths**: `packages/*/layout/*.php` for layouts, `packages/*/layout/extensions/*.php` for extensions. File contents declare handle/target; filename is not parsed.
- **Single package** `markommerce/layout` with a `Contracts/` namespace for interfaces other modules depend on. No separate driver package in v1.
- **Context provider chaining** (one `Provide` depending on another) is disallowed in v1; loud error if attempted.
- **`Source::parentData`** implies two-phase render: parent's `data()` runs first, result is memoized, children read from it.
- **Page metadata** (title, meta, canonical) deliberately out of scope.

## Scope

### In Scope
- Package scaffolding (composer, module.php, directory layout, contracts namespace)
- Core value-object types: `Layout`, `Place`, `Slot` (keyed + repeat), `Provide`, `LayoutDefinition` interface, `ContextProvider` interface
- `Source` vocabulary: `route`, `query`, `context`, `iterated`, `parentData`, `service`, literal
- Extensible-data primitives: `ExtensibleData`, `ExtensionAttribute`, `ExtensionBag`
- Iteration token attribute: `#[IteratesOver(ItemType::class)]`
- Decorator interface (typed wrap contract)
- Extension operation value objects: `InsertBefore`, `InsertAfter`, `Append`, `Prepend`, `Remove`, `Replace`, `MergeProps`, `ReplaceProps`, `WrapWith`; container `LayoutExtension` with `priority`
- Exception catalog: every named error from the brief, each extending `MarkoException` with static factories
- Auto-discovery of layout/extension files across modules
- Compiler:
  - Resolution phase: load layouts, resolve `extends` chain, apply extensions in priority order (conflicts at same priority → loud error), produce intermediate tree
  - Validation phase: type checks (prop ↔ source), anchor existence, name uniqueness/format, repeat-slot data-key existence, dangling anchors, context/iteration reference resolution
  - Artifact emission: write fully-resolved `PreparedTree` per handle to `var/cache/markommerce/layouts.php`
- Source runtime resolvers (one per `Source` kind)
- Runtime renderer: load artifact, two-phase walk (data collect → template render), repeat-slot iteration, decorator application
- CLI command: `layout:compile`
- Dev middleware: `CompileIfStaleMiddleware`
- Routing integration: `MarkommerceLayoutMiddleware` replacing `marko/layout`'s middleware in commerce flows
- Catalog migration: `CategoryController`, `ProductGridComponent`, `category_show.php` layout file
- theme-blank migration: `OneColumnLayout` / `TwoColumnLayout` become `LayoutDefinition` implementations
- README

### Out of Scope
- Scope-awareness (storefront/admin/store_view filtering on placements)
- Per-placement fragment caching
- Server-side hydration data for web components
- Streaming/async data fetch
- Standalone `layout:watch` CLI watcher (dev-middleware only in v1)
- Page metadata (title, meta description, canonical) as a first-class layout concept
- Context provider chaining (Provide depending on another Provide)
- Migration tooling from `marko/layout` to `markommerce/layout` (catalog and theme-blank are migrated manually here; `markommerce/theme-blank-demo` and `markommerce/frontend-demo` keep using `marko/layout` and are NOT migrated in this plan — they continue to work via the still-active `marko/layout` global middleware)
- String-keyed base layouts (only class-based `LayoutDefinition` in v1)

### Added scope (tasks 023-032 — multi-handle system)
- Default handle: a `Layout(handle: 'default', …)` whose placements/operations are compile-time-merged into every other handle's tree. No runtime cost beyond the existing single-tree lookup.
- Handle inheritance: a `Layout` can declare `inherits: 'other_handle'`. The parent tree is flattened into the child at compile time. Single-parent only; cycles are a loud compile error. Multi-parent inheritance is explicitly deferred.
- Dynamic handles: a layout can declare `handleProviders: [new ProvideHandle(provider: SomeHandleProvider::class, props: …)]`. At runtime, after context providers run, providers return additional handle keys whose precompiled trees are merged into the base before rendering.
- Layout files can now carry **operations** alongside placements (new `Layout::$operations` field). This makes a layout file a first-class participant in the extension vocabulary — e.g., a `catalog.product_listing` layout can `Remove` a default-handle placement it inherits.
- `LayoutExtension` is unchanged. Files in `layout/extensions/` still target a handle from outside.

#### Canonical resolution order (tasks 025/026/027)
For each handle `H` that is NOT `'default'`:

1. **Resolve `H`'s own `extends:` chain** (structural shell from `LayoutDefinition`).
2. **Resolve `H`'s `inherits:` parent** recursively, parent fully resolved through steps 1–6 first. The parent's pre-default-merge resolved tree is what `inherits:` consumes (see step 8 below for why).
3. **Merge parent's slot entries** on top of the shell's slot entries.
4. **Merge parent's context providers** ahead of `H`'s own.
5. **Default-merge Half A**: prepend `'default'`'s slot placements + context providers into `H`'s tree. (Lets steps 6–7 below operate on default-contributed placements.)
6. **Apply `H`'s own `Layout::$operations`** (task 025).
7. **Apply matching extension-file operations** in priority order (existing behavior).
8. **Default-merge Half B**: after every handle has gone through steps 1–7, apply `'default'`'s **operations** (and `LayoutExtension` files targeting `'default'`) across every sibling handle. Strip the `'default'` entry from the resolved-layouts map. (Runs as the final compile step — see task 027.)

At runtime, after step 8's artifact is loaded:

9. **Match route → base handle key** (task 016, unchanged).
10. **Run base tree's context providers** (renderer Phase 0).
11. **Run base tree's `handleProviders`**, collect returned handle keys, dedup, fetch each from artifact (task 029).
12. **`TreeMerger` folds dynamic trees into the base**: append their slot entries, concatenate their context providers, apply their operations on top (task 029).
13. **Renderer Phase 0/1/2** runs on the merged tree (renderer unchanged).

No change is required to `CompileIfStaleMiddleware` for tasks 023–032: it already globs `packages/*/layout/*.php` and `packages/*/layout/extensions/*.php`, which covers all new layout files (`default.php`, inheriting layouts, layouts with `handleProviders`).

## Success Criteria
- [ ] `markommerce/catalog` `composer.json` no longer requires `marko/layout`
- [ ] `markommerce/theme-blank` `composer.json` no longer requires `marko/layout`
- [ ] `vendor/bin/marko layout:compile` produces `var/cache/markommerce/layouts.php` from real layout files
- [ ] Category page route renders end-to-end via the new system, including iterated `ProductCard` placements with `StockBadge` sub-slots
- [ ] A demo layout extension in tests (insert + wrap + merge-props) takes effect on the resolved tree
- [ ] All ten named exceptions from tasks 001–022 are thrown with location + suggestion when their condition is triggered (covered by tests)
- [ ] All seven new handle-system exceptions from task 023 (`CircularInheritanceException`, `UnknownParentHandleException`, `DefaultHandleConflictException`, `DynamicHandleConflictException`, `UnknownDynamicHandleException`, `DuplicateContextTokenException`, `ChainedHandleProviderException`) are thrown with location + suggestion when their condition is triggered (covered by tests in tasks 023/026/027/028/029/030)
- [ ] `CompileIfStaleMiddleware` triggers recompile when a layout source is touched in dev
- [ ] All tests passing; PHPStan level 8 clean; 80%+ coverage on the new package

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php, directory layout, Contracts namespace) | none | completed |
| 002 | Exception catalog (LayoutException base + all 10 named exceptions with static factories) | 001 | completed |
| 003 | Extensible data primitives (ExtensibleData, ExtensionAttribute, ExtensionBag) | 001 | completed |
| 004 | Core value objects: Layout, Place, Slot (keyed + repeat), Provide, LayoutDefinition, ContextProvider interfaces | 001 | completed |
| 005 | Source vocabulary value objects + Source factory | 004 | completed |
| 006 | Iteration token attribute (#[IteratesOver]) + DecoratorInterface | 004 | completed |
| 007 | Extension operation value objects + LayoutExtension container | 004 | completed |
| 008 | Layout/extension auto-discovery scanner | 002, 004, 007 | completed |
| 009 | Compiler — resolution phase (extends chain + extension application by priority) | 005, 007, 008 | completed |
| 010 | Compiler — validation phase (types, anchors, names, repeat-slot data-key, context/iteration scope) | 002, 006, 009 | completed |
| 011 | Compiled artifact: PreparedTree value objects + writer/reader | 009 | completed |
| 012 | Source runtime resolvers (one strategy per Source kind) | 005 | completed |
| 013 | Runtime renderer (two-phase walk, repeat-slot iteration, decorator application) | 003, 006, 011, 012 | completed |
| 014 | CLI command `layout:compile` | 010, 011 | completed |
| 015 | Dev middleware `CompileIfStaleMiddleware` | 014 | completed |
| 016 | Routing integration: `MarkommerceLayoutMiddleware` | 013 | completed |
| 017 | `markommerce/catalog` migration (drop marko/layout, port ProductGridComponent, create category_show.php, update CategoryController) | 016, 018 | completed |
| 018 | `markommerce/theme-blank` migration (all 5 layout classes → LayoutDefinition) | 004, 009 | completed |
| 019 | Package README | 001-018 | completed |
| 020 | Migrate `markommerce/frontend-demo` off `marko/layout` | 019 | completed |
| 021 | Create `markommerce/layout-demo` showcase module | 020 | completed |
| 022 | Create docs guide for layouts | 021 | completed |
| 023 | Handle-system exception catalog (CircularInheritance, UnknownParentHandle, DefaultHandleConflict, DynamicHandleConflict) | none | completed |
| 024 | Extend `Layout` value object with `operations` and `inherits` fields | 023 | completed |
| 025 | Compiler applies `Layout::$operations` after `extends:` chain | 024 | completed |
| 026 | Handle inheritance: flatten parent tree into child, single-parent with cycle detection | 025 | completed |
| 027 | Default handle: compile-time merge of `'default'` placements/operations into every other handle | 026 | completed |
| 028 | `HandleProvider` contract + `ProvideHandle` declaration in `Layout` | 024 | completed |
| 029 | Middleware invokes `HandleProvider`s and merges multiple trees at runtime | 027, 028 | completed |
| 030 | Cross-handle conflict validation (placement-name + context-token collisions) | 029 | completed |
| 031 | Layout-demo exercises default + inherits + dynamic handles end-to-end | 030 | completed |
| 032 | Document default / inheritance / dynamic handles in guide and API reference | 031 | completed |

## Architecture Notes

### Package layout
```
packages/layout/
  composer.json
  module.php
  src/
    Contracts/                 # Interfaces other modules depend on
      LayoutDefinition.php
      ContextProvider.php
      DecoratorInterface.php
      ExtensionAttribute.php
    Attributes/
      IteratesOver.php
    Source/                    # Source value objects + factory
    Operation/                 # Extension operation value objects
    Compiler/                  # Resolution + validation passes
    Runtime/                   # Renderer + source resolvers
    Cache/                     # PreparedTree + artifact reader/writer
    Discovery/                 # Layout file scanner
    Command/                   # CLI commands
    Middleware/                # CompileIfStale + MarkommerceLayoutMiddleware
    Exception/                 # MarkoException-extending classes
    Layout.php / Place.php / Slot.php / Provide.php / LayoutExtension.php
    ExtensibleData.php / ExtensionBag.php
  tests/
    Unit/
    Feature/
```

### Compilation pipeline
```
[ layout/*.php source files ]
        │  (Discovery)
        ▼
[ Layout, LayoutExtension instances ]
        │  (Resolution: extends chain + extension application by priority)
        ▼
[ Resolved tree per handle ]
        │  (Validation: types, anchors, names, scopes)
        ▼
[ Validated tree per handle, or loud errors ]
        │  (Artifact emission)
        ▼
[ var/cache/markommerce/layouts.php → PreparedTree[handleKey] ]
        │  (Runtime: MarkommerceLayoutMiddleware on a route hit)
        ▼
[ Two-phase render: data collect → template render → Response ]
```

### Two-phase render
Phase 1 walks the tree calling each placement's `data()` (with Sources resolved), memoizing the result on the node. Phase 2 walks again rendering templates — repeat-slots iterate their `data()` array, decorators wrap children, sub-slots inline. This separation lets `Source::parentData` work without recursion-time evaluation.

### Plugin-based DTO extension
Third-party modules extend a typed DTO via `ExtensibleData` + `ExtensionAttribute` + a Marko `#[Plugin]` on the component's `data()` method. Plugin returns a new DTO with `$data->withExtension(...)`. Templates read extensions with `$extensions->get(SomeExtension::class)?->field`.

### Loud-error contract
Every layout exception extends `MarkoException` and carries `message`, `context` (placement chain or file path), `suggestion`. Static factories per failure mode. No silent catch-and-continue in the compiler.

## Risks & Mitigations
- **PHP `clone(obj, [...])` named-arg form unfamiliar.** Mitigation: `ExtensibleData::withExtension()` hides it; README example.
- **Auto-discovery scans every PHP file under `packages/*/layout/`.** Mitigation: small file count; compile is a build step. Future: incremental compile.
- **Compile errors surface at deploy time, not PR time.** Mitigation: `composer test` runs `layout:compile` in CI on every PR (task 014 acceptance criteria).
- **Two-phase render ordering subtleties with `Source::parentData`.** Mitigation: "parent" is defined as the immediate enclosing placement (task 013); parent `data()` is memoized before descending into slots; `parentData` reads the memoized DTO; top-level `parentData` is a compile error (task 010).
- **Migrating catalog + theme-blank in the same plan as building the system.** Mitigation: migration tasks (017, 018) sit last, depend on renderer + middleware, are atomic dependency swaps. Task 017 depends on 018 (its layout `extends:` a theme-blank `LayoutDefinition`).
- **Marko Plugin return-value mutation requires an *after* plugin and breaks for `readonly` component classes.** Verified against `marko/core/Plugin/`: after-plugins receive `($result, ...$args)` and can replace the DTO; the concrete-subclass interception strategy throws for `readonly` classes, so plugin-extensible components must be non-readonly and resolved via the container. Mitigation: tasks 003/013/017 carry these constraints explicitly.
- **`MarkommerceLayoutMiddleware` registration.** The `marko` repo's `local-develop` branch added module-declared global middleware (`globalMiddleware` key in `module.php`, resolved by `GlobalMiddlewareResolver`, priority-sorted). Mitigation: `MarkommerceLayoutMiddleware` self-declares as a global middleware at priority 30 (task 016); it coexists with `marko/layout`'s still-active `LayoutMiddleware` and at most one renders per route.
- **Artifact serialization of `readonly` value objects.** `var_export` + `__set_state` fails for readonly props. Mitigation: task 011 emits explicit `new ClassName(...)` constructor-call source.
- **`clone(obj, [...])` requires PHP 8.4+.** Mitigation: project targets PHP 8.5+ per `code-standards.md`.
- **`marko/layout` is not fully removed from the monorepo.** `theme-blank-demo` and `frontend-demo` still depend on it. Their migration is explicitly out of scope; this is acceptable because the two layout systems coexist.
