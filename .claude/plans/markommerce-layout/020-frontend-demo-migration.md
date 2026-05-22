# Task 020: Migrate `markommerce/frontend-demo` off `marko/layout`

**Status**: completed
**Depends on**: 019
**Retry count**: 0

## Description
`markommerce/frontend-demo` still uses `marko/layout`'s `#[Layout]` and `#[Component]` attributes. Migrate it to use `markommerce/layout` so it is a real working example of the new system.

## Context
- `DemoController` has `#[Layout(DemoLayout::class)]` (from `marko/layout`).
- `DemoLayout` is a class with `#[Component(template: 'frontend-demo::layout/base', slots: ['content'])]` — the old per-class wiring.
- `DemoCounterComponent` has `#[Component(template: 'frontend-demo::counter', handle: [DemoController::class, 'index'], slot: 'content')]` — placement declared on the component itself.
- After migration: layout placement is declared in `packages/frontend-demo/layout/demo.php`, components are plain classes, `marko/layout` is removed from `composer.json`.
- The `layout/base.latte` template remains unchanged — it already renders `{slot content}{/slot}` which is the only slot needed, and is compatible with `markommerce/layout`'s renderer (see `Renderer::inlineSlots` and `marko/view-latte`'s `SlotExtension`, which both treat `{slot name}{/slot}` as the placeholder syntax).
- `frontend-demo::layout/base` IS the full HTML document (doctype, head, body) — it is the top-level layout, NOT an extension of `OneColumnLayout`. Use `extends: null` and set `template: 'frontend-demo::layout/base'` directly. (theme-blank's `OneColumnLayout` would inject a second outer shell via `theme-blank::layout/1column` → `theme-blank::layout/base` and produce a double `<html>` document; we want the existing single-shell behaviour.)
- `DemoCounterComponent` needs no `data()` method — the counter template takes no server-side props.
- Existing test assertions for `#[Layout(DemoLayout::class)]` and `#[Component(slot: 'content')]` must be replaced with new assertions:
  - Layout file at `packages/frontend-demo/layout/demo.php` exists and returns a `Layout` with the correct handle.
  - `DemoController` has no `#[Layout]` attribute.
  - `DemoCounterComponent` has no `#[Component]` attribute.
  - End-to-end render tests (200 response, `<markommerce-counter` in body) keep passing.
- `DemoControllerTest.php` wires up `marko/layout`'s `LayoutMiddleware` manually. After migration, it must wire `MarkommerceLayoutMiddleware` (from `markommerce/layout`) and the layout compiler/artifact reader instead.
- `module.php` in `frontend-demo` does not currently declare layout middleware — after migration `MarkommerceLayoutMiddleware` is already auto-registered by `markommerce/layout`'s own `module.php` as a global middleware at priority 30. The test harness, however, builds its own router manually, so the test must wire `MarkommerceLayoutMiddleware` explicitly (same pattern as the catalog test).

## Requirements (Test Descriptions)
- [x] `it has a layout file that returns a Layout for DemoController::index`
- [x] `it places DemoCounterComponent in the content slot`
- [x] `it drops the #[Layout] attribute from DemoController`
- [x] `it drops the #[Component] attribute from DemoCounterComponent`
- [x] `it returns 200 OK when the demo route is requested` (existing, must keep passing)
- [x] `it embeds the <markommerce-counter> element in the rendered response body` (existing, must keep passing)
- [x] `it returns 404 when frontend_demo.enabled is false` (existing, must keep passing — but now with MarkommerceLayoutMiddleware in the global pipeline; the EnsureFrontendDemoEnabledMiddleware short-circuits the controller call inside the pipeline, MarkommerceLayoutMiddleware honors the 404 status and returns it as-is)
- [x] `it composer.json no longer lists marko/layout in require` (replaces the existing assertion in ComposerManifestTest.php that requires marko/layout)
- [x] `it composer.json lists markommerce/layout in require at self.version`

### Existing tests that MUST be deleted or rewritten (they reference removed code)
The following tests in `tests/Feature/DemoControllerTest.php` reference `marko/layout` attributes and classes and will fail post-migration. Each must be deleted or rewritten:
- `it uses the DemoLayout component as the layout for the route` — references `Marko\Layout\Attributes\Layout` and `DemoLayout::class`. **DELETE**; replace with the new "has a layout file that returns a Layout..." test above.
- `it composes the DemoCounterComponent into the content slot of DemoLayout` — references `Marko\Layout\Attributes\Component` and reads `$componentAttr->slot`. **DELETE**; replace with the new "places DemoCounterComponent in the content slot" test above (which reads the slot from the layout file, not the attribute).

### Existing tests in `tests/Unit/ComposerManifestTest.php` that MUST be updated
- The assertion at line ~38 `expect($manifest['require']['marko/layout'])->toBe('self.version');` MUST be removed. Replace with `expect($manifest['require'])->not->toHaveKey('marko/layout');` and `expect($manifest['require']['markommerce/layout'])->toBe('self.version');`. Update the surrounding `it(...)` description to reflect the new contract.

## Acceptance Criteria
- `marko/layout` is removed from `packages/frontend-demo/composer.json`
- `markommerce/layout` is added to `packages/frontend-demo/composer.json` requires
- `DemoLayout.php` is deleted
- `packages/frontend-demo/layout/demo.php` exists and returns the correct `Layout`
- `DemoController` has no `marko/layout` imports or attributes
- `DemoCounterComponent` has no `marko/layout` imports or attributes
- All existing end-to-end tests pass
- `composer test` green; PHPStan level 8 clean

## Implementation Notes
- The test helper `demoTestBuildRouter` in `DemoControllerTest.php` needs significant rewrite: drop `marko/layout` classes (`LayoutMiddleware`, `LayoutResolver`, `HandleResolver`, `ComponentCollector`, `DiscoveringComponentCollector`, `ComponentDataResolver`, `LayoutProcessor`) and wire `markommerce/layout`'s runtime stack — mirror how `CategoryControllerTest.php` builds it. Specifically:
  - Build the artifact in-memory via `new Compiler(new LayoutDiscovery($moduleRepository), new ResolutionPhase(), new ValidationPhase(), new PreparedTreeBuilder())` and call `$compiler->compile()` to get `array<string, PreparedTree>`. DO NOT write to disk.
  - Wrap the resulting array in an anonymous `ArtifactReaderInterface` (same as `CategoryControllerTest.php` lines 254-262). The test does not need `ArtifactWriterInterface`.
  - Construct `Renderer` with the `LatteView` and the container.
  - Construct `MarkommerceLayoutMiddleware($matcher, $artifactReader, $renderer)`.
  - Register `DemoCounterComponent::class` in the container (or let `CoreContainer::resolve()` autoresolve it — it is parameterless so autoresolution works).
- Drop the `withLayoutMiddleware: false` parameter from `demoTestBuildRouter`. With the new layout middleware, the 404 case works correctly through the normal pipeline: `EnsureFrontendDemoEnabledMiddleware` short-circuits with a 404, and `MarkommerceLayoutMiddleware` sees the non-2xx status and returns it as-is (see `MarkommerceLayoutMiddleware::handle()` short-circuit branch).
- `DemoCounterComponent` becomes: `class DemoCounterComponent {}` — no methods, no attributes.
- Layout file path: `packages/frontend-demo/layout/demo.php`.
- The layout file: `handle: [DemoController::class, 'index']`, `extends: null`, `context: []`, `template: 'frontend-demo::layout/base'`, `slots: ['content' => [new Place(component: DemoCounterComponent::class, name: 'frontend_demo.counter', props: [], slots: [], template: 'frontend-demo::counter')]]`. The placement name MUST follow the format `^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$` (enforced by `ValidationPhase`).
- Template name in the `Place`: `frontend-demo::counter` (already correct in the existing `counter.latte`).
- Root template name in the `Layout`: `frontend-demo::layout/base` (the existing `base.latte` already uses `{slot content}{/slot}` which is compatible with the renderer).
- `MarkommerceLayoutMiddleware` is auto-registered as global middleware at priority 30 via `packages/layout/module.php` once `markommerce/layout` is in the require list. The test harness still has to register it manually because it builds a `Router` directly (it does not bootstrap the full module discovery pipeline).
- `CompileIfStaleMiddleware` is NOT needed in the test harness because the test builds the artifact in-memory before each request. Do not wire it.
