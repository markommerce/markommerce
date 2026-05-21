# Task 015: ProductGrid component + grid/item templates with image placeholder

**Status**: complete
**Depends on**: 014a, 014b
**Retry count**: 0

## Description
Add a `ProductGridComponent` (marko/layout `#[Component]`) that fills the `content` slot of theme-blank's `OneColumnLayout` (added in task 014a) with the category heading and a responsive product grid built on theme-blank's `<mk-grid>` web component. Each product is rendered via a reusable `product-grid-item.latte` partial that includes a hardcoded placeholder image. This task wires the *rendering pieces* only — the controller still uses the old direct-render path until task 016.

## Context
- **What "component" means here**: `marko/layout`'s `#[Component]` attribute is built for slot composition — a single instance is attached to a slot on a Layout. It is NOT designed for per-item iteration over a collection. So:
  - The **product grid** is a real `#[Component]` PHP class (`ProductGridComponent`), attached to the `content` slot of `Markommerce\ThemeBlank\Layout\OneColumnLayout` (added in task 014a). Its `data()` method fetches the category and its products.
  - The **product grid item** is a Latte partial template (`resources/views/components/product-grid-item.latte`) included from the grid template via `{include 'catalog::components/product-grid-item' product => $product, name => $resolvedNames[$product->id]}` for each product. No PHP class — adding one would be ceremony, since marko/layout would never instantiate it.
  - If the user later wants the item to be a real `#[Component]` (e.g. to attach mixins), that becomes a follow-up — for this task, partial-template-with-include is the idiomatic fit.
- **Reference patterns**:
  - `packages/frontend-demo/src/Component/DemoCounterComponent.php` — minimal `#[Component]` marker class, no `data()` method because the demo template is static.
  - `packages/playground/.../PostController.php` + post-show component pair (see task 014a's discovery notes for marko/layout) — `data(int $id): array` signature; the parameter name `$id` matches the route's `{id}` placeholder and is bound automatically by `ComponentDataResolver`.
  - `packages/theme-blank-demo/resources/views/showcase.latte` lines 21–27 — example `<mk-grid min="10rem" gap="3">` usage from the live showcase.
  - `packages/theme-blank/resources/css/components/mk-grid.css` — confirms `mk-grid` reads `min` (via `--mk-grid-min`) and `gap` (0–9, mapped to `--mk-space-*`).
- **PHP class**: `packages/catalog/src/Component/ProductGridComponent.php`.
  - Attribute: `#[Component(template: 'catalog::components/product-grid', handle: 'default', slot: 'content')]`.
  - Constructor injects `CategoryRepositoryInterface`, `CategoryAssignmentService`, and `Markommerce\Scope\Resolver\ScopeResolver` (the same three dependencies that currently live on `CategoryController` from task 011). Follow the interface-parameter-naming rule from `code-standards.md`.
  - Method `data(int $id): array` — fetches the category, fetches its products via `categoryAssignmentService->productsInCategory($id)`, resolves each product's name via `scopeResolver->resolved($product, 'name')` and description via `scopeResolver->resolved($product, 'description')`, and returns:
    ```php
    return [
        'category'        => $category,
        'products'        => $products,
        'resolvedNames'   => $resolvedNames,        // [productId => resolvedName]
        'resolvedDescs'   => $resolvedDescs,        // [productId => resolvedDescription]
    ];
    ```
  - **404 handling**: if `categoryRepository->find($id)` returns `null`, throw `CategoryNotFoundException` (it already exists from task 002). Task 016 is responsible for ensuring the framework turns this into a 404 response when Layout is active — call it out there, not here. For *this* task's tests, asserting the exception is thrown is sufficient.
  - Non-final class. `declare(strict_types=1)`. `@throws` for `CategoryNotFoundException` and `Marko\Database\Exceptions\RepositoryException`.
- **Grid template**: `packages/catalog/resources/views/components/product-grid.latte`. Structure:
  ```latte
  <mk-stack gap="5">
      <mk-heading size="2xl"><h1>{$category->name}</h1></mk-heading>
      {if count($products) > 0}
          <mk-grid min="14rem" gap="4">
              {foreach $products as $product}
                  {include 'catalog::components/product-grid-item', product => $product, name => $resolvedNames[$product->id], description => $resolvedDescs[$product->id]}
              {/foreach}
          </mk-grid>
      {else}
          <mk-text variant="muted">No products found in this category.</mk-text>
      {/if}
  </mk-stack>
  ```
  - Use theme-blank's existing primitives (`mk-stack`, `mk-heading`, `mk-grid`, `mk-text`) rather than introducing new catalog-specific layout components. `mk-grid` provides the responsive `auto-fit / minmax` layout — pick `min="14rem"` for a sensible default, easy to tune.
- **Item template**: `packages/catalog/resources/views/components/product-grid-item.latte`. Structure:
  ```latte
  <article class="catalog-product-card">
      <img class="catalog-product-card__image"
           src="https://placehold.co/400x400?text={$product->sku}"
           alt="{$name}"
           width="400" height="400" loading="lazy">
      <mk-stack gap="2">
          <mk-heading level="3" size="md">{$name}</mk-heading>
          {if $description}
              <mk-text variant="small">{$description}</mk-text>
          {/if}
      </mk-stack>
  </article>
  ```
  - The hardcoded `https://placehold.co/...` URL parameterised by SKU gives every card a different placeholder image without depending on a real image field — that's intentional and explicitly per the user's instruction ("hardcoded for now"). When the catalog gets a real `image` field, this is the one line that changes.
  - `width`/`height` attributes set an aspect ratio so the grid doesn't reflow on image load (CLS-friendly — matches the playwright-CLS work in the `theme-blank-feedback` plan).
  - `loading="lazy"` is the right default for below-the-fold cards.
- **Catalog-local CSS**: add `packages/catalog/resources/css/components/product-card.css` with `@layer components` styling for `.catalog-product-card` (border, padding, rounded corners) and `.catalog-product-card__image` (full-width image, `aspect-ratio: 1`, `object-fit: cover`). Use theme-blank tokens (`--mk-color-*`, `--mk-space-*`, `--mk-radius-*`) — do not hardcode colors/spacing. Import this CSS from `packages/catalog/resources/js/index.ts` (the file scaffolded in task 014b) so the cascade-layer ordering is correct. Catalog has no `main.ts`; the cascade-layer bootstrap lives in theme-blank's `index.ts` (task 014b). Because catalog's `index.ts` is imported by the Vite scanner *after* theme-blank's bootstrap has run, the `@layer components` rule lands in the same `components` layer alongside `mk-*` component CSS — correctly ordered.
- **Project rules**: `declare(strict_types=1)`, no `final`, `@throws` annotations, no traits.

## Requirements (Test Descriptions)
- [x] `it declares ProductGridComponent with a Component attribute pointing at the catalog product-grid template in the content slot of theme-blank's OneColumnLayout`
- [x] `it injects the category repository, assignment service, and scope resolver`
- [x] `it returns category, products, and resolved name/description maps from data() for an existing category`
- [x] `it returns an empty products array from data() when the category has no assigned products`
- [x] `it throws CategoryNotFoundException from data() when the category id does not exist`
- [x] `it renders the category name as the page heading via mk-heading`
- [x] `it renders products inside an mk-grid element`
- [x] `it renders one product-grid-item per product, including the resolved name`
- [x] `it renders the placeholder image src with the product SKU in the URL`
- [x] `it renders a muted empty state when the category has no products`
- [x] `it resolves product names through ScopeResolver rather than the raw column value`

## Acceptance Criteria
- All requirements have passing tests
- `ProductGridComponent` is constructor-injected with interfaces, not concrete classes
- Templates use theme-blank's `mk-*` web components (no raw CSS grid in the latte) and reference catalog tokens via CSS layer 'components'
- The product-grid-item partial is reusable — its template renders correctly when passed `product`, `name`, `description` directly (no implicit dependency on grid-template variables)
- Code follows project standards

## Notes for Implementer
- Use the task-006 in-memory fakes for `CategoryRepositoryInterface`, `ProductRepositoryInterface`, and `ProductCategoryAssignmentRepositoryInterface` in unit tests for `data()`. For ScopeResolver, build the real stack via the `buildResolverStack()` helper documented in task 011's notes.
- For template rendering tests, render via `LatteEngineFactory` (no Vite) and assert on output substrings: `<mk-grid`, `<mk-heading`, the placeholder image URL containing the SKU, the resolved name appearing somewhere in the output. Avoid full HTML structural assertions — they break easily.
- Do NOT remove the standalone `category.latte` or change the controller in this task. Task 016 owns that swap.
- If `LayoutProcessor`'s `data()` method discovery requires the method to be public and match the route parameter names exactly, double-check by reading `marko/packages/layout/src/ComponentDataResolver.php` (the discovery notes in task 014a reference it).
