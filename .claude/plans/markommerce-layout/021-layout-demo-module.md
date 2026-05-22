# Task 021: Create `markommerce/layout-demo` module

**Status**: completed
**Depends on**: 020
**Retry count**: 0

## Description
Create a new `markommerce/layout-demo` package that showcases the `markommerce/layout` system end-to-end: how to define a layout, use context providers, iteration slots, typed DTOs, and all major extension operations. This is the canonical reference for third-party developers learning the extension system.

## Context
- Package lives at `packages/layout-demo/`.
- The demo must be self-contained: it defines its own domain objects, context provider, controller, layout file, and one or more layout extension files.
- The package is **PHP/HTML only** — no JavaScript, no Vite assets, no `package.json`. Server-rendered HTML is sufficient to demonstrate the layout system.
- Use `markommerce/theme-blank`'s `OneColumnLayout` as the base layout to demonstrate the `extends:` mechanism. `OneColumnLayout::define()` returns a `Layout` with `template: 'theme-blank::layout/1column'` and `slots: ['content' => []]`, so the demo layout fills the `content` slot.
- The demo should showcase:
  1. **Layout definition** — a `Layout` returned from `layout/*.php` with `extends: OneColumnLayout::class`, a context `Provide`, and a repeat slot.
  2. **Typed DTOs** — component classes with `data()` methods returning typed `readonly` DTOs. At least two components: a gallery component (receives context object, returns a DTO containing an iterable) and an item component (receives iterated entity).
  3. **Extension operations** — a separate `layout/extensions/*.php` file demonstrating at minimum `InsertBefore`, `WrapWith`, and `MergeProps`. The extension targets placements defined in the base layout file.
  4. **`Source` vocabulary** — exercises all six source types: `Source::route()` (e.g. an optional `?page` query param via `Source::query()`), `Source::context()`, `Source::iterated()`, `Source::parentData()`, and `Source::service()` (e.g. resolve a formatting helper service). Every source kind must appear at least once in the layout file or extensions.
- The module is guarded by a config flag `layout_demo.enabled` (same pattern as `frontend-demo`) via an `EnsureLayoutDemoEnabledMiddleware`. Mirror the existing `EnsureFrontendDemoEnabledMiddleware` exactly: per-route middleware declared with `#[Middleware([...])]` on the controller action, returns `Response('Not Found', 404)` when disabled, otherwise calls `$next($request)`. `MarkommerceLayoutMiddleware` honors the 404 short-circuit.
- A `Compiler::compile()` call must succeed with this module loaded — meaning all component classes, token classes, iteration classes, and templates referenced in the layout files actually exist.
- Tests must cover:
  - A feature test (`LayoutDemoControllerTest.php`) that compiles the layout in-memory, boots a minimal router with `MarkommerceLayoutMiddleware`, and asserts the response renders expected HTML fragments from the demo components (200 case) and that disabling the config flag returns 404.
  - Unit tests asserting the layout file returns the expected `Layout` structure (handle, extends, slots, context).
  - A `ComposerManifestTest.php` asserting `composer.json` is well-formed.
  - A `ReadmeTest.php` asserting the package README exists and contains required content.

## Requirements (Test Descriptions)
- [x] `it has a composer.json with name markommerce/layout-demo`
- [x] `it composer.json requires marko/config, marko/core, marko/routing, marko/view, marko/view-latte, markommerce/layout, markommerce/theme-blank at self.version`
- [x] `it has a config/layout_demo.php file with enabled defaulting to false`
- [x] `it has a layout file that returns a Layout for the demo controller handle`
- [x] `it has a Layout that extends OneColumnLayout`
- [x] `it has a Layout with a context Provide`
- [x] `it has a Layout with a repeat slot`
- [x] `it has an extension file that applies InsertBefore`
- [x] `it has an extension file that applies WrapWith`
- [x] `it has an extension file that applies MergeProps`
- [x] `it returns 200 OK when the demo route is requested and layout_demo.enabled is true`
- [x] `it renders expected HTML fragments from the demo components`
- [x] `it returns 404 when layout_demo.enabled is false`
- [x] `it has a README`
- [x] `it compiles to a valid PreparedTree (Compiler::compile() returns the demo handle without throwing)`
- [x] `it the repo-root composer.json requires markommerce/layout-demo in require-dev at self.version`

## Acceptance Criteria
- `packages/layout-demo/composer.json` exists with correct name, `marko-module` type, and requires `marko/config`, `marko/core`, `marko/routing`, `marko/view`, `marko/view-latte`, `markommerce/layout`, `markommerce/theme-blank` at `self.version`
- `packages/layout-demo/module.php` exists (returns `[]` or a minimal bindings array — DI for components and providers is autoresolved from the container; the existing global middleware from `markommerce/layout` handles the rendering)
- `packages/layout-demo/config/layout_demo.php` exists and returns `['enabled' => false]`
- `packages/layout-demo/src/Config/LayoutDemoConfig.php` exists (mirroring `FrontendDemoConfig`)
- `packages/layout-demo/src/Middleware/EnsureLayoutDemoEnabledMiddleware.php` exists (mirroring `EnsureFrontendDemoEnabledMiddleware`)
- A controller class (e.g. `Markommerce\LayoutDemo\Controller\LayoutDemoController`) with a `#[Get('/markommerce/_demo/layout')]` route attribute on its action method, and `#[Middleware([EnsureLayoutDemoEnabledMiddleware::class])]`. The action returns `void` (the layout renderer takes over) or `Response::html('', 200)`.
- Latte template files at `packages/layout-demo/resources/views/...` for each component referenced by the layout. Without templates the renderer cannot produce HTML.
- Layout file at `packages/layout-demo/layout/layout_demo.php` compiles without error (the `Compiler::compile()` call inside the test produces a non-empty `array<string, PreparedTree>` containing the demo handle key)
- Extension file at `packages/layout-demo/layout/extensions/layout_demo_extension.php` applies correctly (operations take effect in the resolved tree, verified by asserting rendered HTML fragments)
- The repo-root `composer.json` `require-dev` block contains `markommerce/layout-demo: self.version`
- End-to-end test passes (200 response with expected HTML; 404 when `layout_demo.enabled` is false)
- `composer test` green; PHPStan level 8 clean

## Implementation Notes
- Domain: pick something simple, e.g. a "Gallery" with a list of "Items" (no real DB — return static fixtures from the context provider).
- Item entity: `readonly class Item { public function __construct(public int $id, public string $label) {} }` — public properties on a non-readonly class are fine too; the renderer reads via reflection.
- Iteration token: `class ItemIteration {}` with `#[IteratesOver(Item::class)]`.
- Data flow (mirrors the catalog pattern: ContextProvider returns the domain entity; the component's `data()` consumes it and returns a typed DTO):
  - `GalleryEntity` (domain entity returned by the context provider) with at least a `title: string` and `items: list<Item>`.
  - `GalleryToken` (empty marker class used as the context key).
  - `GalleryContextProvider implements ContextProvider` — its `provide(array $props): GalleryEntity` returns a fixture `GalleryEntity` with 2-3 items.
  - `GalleryComponent` with `data(GalleryEntity $gallery): GalleryData` returning a DTO with a `title: string` and `items: list<Item>` property (so the repeat slot's `dataKey: 'items'` lands on a `list<Item>` property — required by `ValidationPhase::checkRepeatItemType`, which parses the `@var list<Item>` docblock on the DTO property).
  - `ItemComponent` with `data(Item $item): ItemData` returning a DTO with the per-item fields rendered by the template.
- The `Place` for `GalleryComponent` uses `props: ['gallery' => Source::context(GalleryToken::class)]`.
- The `Place` for `ItemComponent` (inside `Slot::repeat`) uses `props: ['item' => Source::iterated(ItemIteration::class)]`.
- All six `Source` kinds must appear at least once:
  - `Source::route('id', 'int')` — e.g. pass a gallery ID through the route; adjust the route to `/markommerce/_demo/layout/{id}` so the context provider receives it.
  - `Source::query('page', 1, 'int')` — e.g. pass a current page number to `GalleryComponent` for display in the template.
  - `Source::context(GalleryToken::class)` — pass the gallery entity to `GalleryComponent`.
  - `Source::iterated(ItemIteration::class)` — pass the current item to `ItemComponent`.
  - `Source::parentData('title', 'string')` — e.g. an item-level placement reads the gallery title from `GalleryData` as a subtitle prefix.
  - `Source::service(SomeFormatterInterface::class)` — e.g. pass a label formatter service to `ItemComponent`. Define a minimal `LabelFormatterInterface` (single method `format(string $label): string`) and a concrete implementation; bind it in `module.php`.
- Placement names MUST match the regex `^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$` (e.g., `layout_demo.gallery`, `layout_demo.item`). Single-segment names like `gallery` will fail compile validation.
- Latte templates required (one per component):
  - `packages/layout-demo/resources/views/gallery.latte` — renders the gallery wrapper with `{slot items}{/slot}` (the slot name must match the repeat slot key in the layout file).
  - `packages/layout-demo/resources/views/item.latte` — renders a single item using DTO public properties (e.g. `{$label}`).
  - Plus any decorator templates referenced by `WrapWith`.
- The decorator class used by `WrapWith` must implement `DecoratorInterface` (`template(): string` and `wrap(string $innerHtml, array $data): string`) AND its template MUST contain a literal `{slot inner}` placeholder (validated by `DecoratorTemplateValidator` at compile time — a missing placeholder throws `MissingSlotInnerException`).
- The extension file adds a decorative "Featured" badge `InsertBefore` an item component (anchor matches the item placement's `name`), wraps the gallery with a `WrapWith` decorator (the `name` arg matches the gallery placement's `name`), and `MergeProps` an additional CSS class prop onto one named placement. All operation `name`/`anchorName` arguments MUST exactly match a placement `name` defined in the base layout — otherwise `DanglingAnchorException` is thrown at compile.
- Follow the same test-harness pattern as `DemoControllerTest.php` (after its migration in task 020) for the end-to-end test. In particular: build the artifact in-memory via `Compiler::compile()`, wrap it in an anonymous `ArtifactReaderInterface`, do NOT write to disk.
- Add `layout-demo` to the monorepo `composer.json` `require-dev`. The existing `repositories` block already has `packages/*` so no change to repositories is needed; only the require-dev entry.
- Package README: slim format matching the project standard — one-liner description, install command, quick example, link to docs.
