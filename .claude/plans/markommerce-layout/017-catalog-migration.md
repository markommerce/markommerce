# Task 017: markommerce/catalog Migration

**Status**: completed
**Depends on**: 016, 018
**Retry count**: 0

## Description
Migrate `markommerce/catalog` off `marko/layout` onto the new `markommerce/layout` system. Port `ProductGridComponent` to the new placement-agnostic component model with a typed data DTO, create the `category_show.php` layout file, update `CategoryController`, and drop the `marko/layout` dependency.

## Context
- `ProductGridComponent` currently (`packages/catalog/src/Component/ProductGridComponent.php`) carries `#[Component(template:..., handle:[CategoryController::class,'show'], slot:'content')]` from `marko/layout` and its `data(int $id)` re-fetches the category itself.
- New model:
  - The component keeps a template binding but loses `handle`/`slot` — placement moves to the layout file.
  - `data()` returns a typed DTO extending `ExtensibleData` (task 003), not `array<string,mixed>`. Define `ProductGridData` and `ProductCardData` (and any other needed DTOs) with typed public properties.
  - The product list must be exposed as a DTO property so a repeat slot can iterate it; PHPDoc-annotate it `@var list<...>` per task 010's supported forms.
  - Category loading moves to a `CategoryDataProvider implements ContextProvider` (task 004) declared via `Provide(CategoryToken::class, ...)` so components receive the category as context instead of re-fetching it.
- Create `packages/catalog/layout/category_show.php` returning a `new Layout(...)` for `[CategoryController::class, 'show']`. It should `extends:` a theme-blank `LayoutDefinition` (task 018 — use `Markommerce\ThemeBlank\Layout\OneColumnLayout`, which is the real class name), declare the `CategoryToken` provider, place a product grid in the `content` slot, and use a `Slot::repeat` for product cards. Include at least one sub-slot (e.g. `badges` with a stock badge) to exercise nesting. Model it on the example tree in `_plan.md`.
- Create the component classes the layout references that don't exist yet (e.g. `ProductCard`, `StockBadge`, a `CategoryToken` marker, a `ProductIteration` token with `#[IteratesOver(Product::class)]`). Keep them minimal but real — they must compile and render.
  - IMPORTANT: any component exposing a plugin-extensible `data()` (per task 003) MUST NOT be a `readonly class` — Marko's concrete-subclass plugin interception throws for readonly classes. `ProductCard`, `ProductGridComponent`, and `StockBadge` are plain (non-readonly) classes; only their data DTOs are `readonly`.
- Update `CategoryController`:
  - Drop the `#[Layout(OneColumnLayout::class)]` attribute from `marko/layout` and its `use Marko\Layout\Attributes\Layout;` / `use Markommerce\ThemeBlank\Layout\OneColumnLayout;` imports.
  - Do NOT add a `#[Middleware]` attribute. `MarkommerceLayoutMiddleware` is a module-declared **global** middleware (task 016 — declared via the `globalMiddleware` key in `markommerce/layout`'s `module.php`, supported on the `marko` repo's `local-develop` branch). It runs for every route and falls through for routes with no compiled layout, so `CategoryController` needs no per-route wiring.
  - The controller action stays for side effects (404 when category missing) but no longer returns rendered HTML — the layout owns rendering.
- Remove `marko/layout` from `packages/catalog/composer.json` `require`. Add `markommerce/layout`.
- Update existing catalog tests that referenced the old component/layout shape:
  - `tests/Unit/PackageScaffoldingTest.php` — its `it('requires the marko routing, view, view-latte and layout packages')` test asserts `marko/layout` is required; change it to assert `markommerce/layout` instead.
  - `tests/Unit/Component/ProductGridComponentTest.php` — currently reflects on the `marko/layout` `#[Component]` attribute (`handle`/`slot`) and asserts `data()` returns an array with `category`/`products`/`resolvedNames`/`resolvedDescs` keys; rewrite for the placement-agnostic component + typed DTO shape.
  - `tests/Feature/CategoryControllerTest.php` — currently builds a `LayoutProcessor`/`LayoutMiddleware` pipeline by hand and asserts the `#[Layout]` attribute; rewrite to drive the route through the globally-registered `MarkommerceLayoutMiddleware` + the compiled artifact. The `it('declares theme-blank's OneColumnLayout via the Layout attribute')` test must be removed/replaced.
- `vendor/bin/marko layout:compile` must succeed with the new catalog layout present.
- Patterns to follow: the worked example in `_plan.md`; `packages/catalog` existing structure.

## Requirements (Test Descriptions)
- [ ] `it defines a category_show layout for the CategoryController show action`
- [ ] `it compiles the category_show layout without error`
- [ ] `it exposes the product grid component without a hardcoded handle or slot`
- [ ] `it returns a typed ProductGridData DTO from the grid component data method`
- [ ] `it loads the category via a context provider instead of inside the component`
- [ ] `it renders the category page with a grid of product cards`
- [ ] `it renders a stock badge sub-slot inside each product card`
- [ ] `it returns 404 from the controller when the category does not exist`
- [ ] `it no longer carries the marko/layout Layout attribute on CategoryController`
- [ ] `it no longer depends on marko/layout in composer.json`

## Acceptance Criteria
- All requirements have passing tests
- `packages/catalog/composer.json` requires `markommerce/layout` and no longer requires `marko/layout`
- `CategoryController` no longer carries `#[Layout]` and needs no `#[Middleware]` attribute (the layout middleware is global)
- `layout:compile` succeeds with the catalog layout
- The category page renders end-to-end through `markommerce/layout`
- `PackageScaffoldingTest`, `ProductGridComponentTest`, and `CategoryControllerTest` updated and passing

## Implementation Notes
(Left blank - filled in by programmer during implementation)
