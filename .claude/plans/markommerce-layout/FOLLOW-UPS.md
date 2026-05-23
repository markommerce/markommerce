# Follow-ups: markommerce/layout

Features identified after the initial plan completed. Each entry is a candidate for promotion to its own plan when prioritized.

## 1. Default handle

**Goal**: A layout or extension that applies to every request, regardless of route. Magento equivalent: the `default` handle, which wraps all pages with a global shell.

**Use cases**
- Site-wide header/footer placements injected once, applied everywhere.
- Storefront-wide notices (cookie banners, maintenance warnings) added by a single extension targeting `default` instead of every route's handle.
- A baseline shell that every commerce page inherits without having to declare `extends:` in each layout.

**Current state**
- `Layout::$handle` accepts `string` in addition to the controller-action pair, so a string handle like `'default'` is already type-valid.
- `MarkommerceLayoutMiddleware` only computes one handle key (`ControllerFQCN::action`) per request. No code path produces or matches a `default` handle at runtime.
- Extension matching in `ResolutionPhase` is exact-string equality; the resolved tree for a route never merges in another tree.

**Open design questions**
- Should `default` apply at compile time (every route's resolved tree gets `default`'s placements/extensions merged into it) or at runtime (middleware looks up both keys and renders them in sequence / merged)? Compile-time is simpler and matches current cache-once philosophy; runtime is more flexible but breaks the "look up one tree, render it" pattern.
- What is the merging rule? Is `default` a layout (placements go into matching slots, extensions apply over the top) or just a bag of extensions that target every handle? Magento conflates the two.
- Does `default` participate in the `extends:` chain, or is it orthogonal?

**Estimated effort**: small if compile-time only (loop over resolved trees and apply `default` extensions). Medium if `default` is allowed to contribute placements that interact with `extends:` chains.

## 2. Dynamic handles

**Goal**: Allow handles to be computed at request time based on context — entity type, customer group, store view, etc. Magento equivalent: handles like `catalog_product_view_type_configurable` added by the product controller after loading the product.

**Use cases**
- A product page renders differently for `SimpleProduct` vs `ConfigurableProduct` vs `BundleProduct` without each needing a separate controller. The controller loads the product, then declares `catalog.product.type.configurable` as an active handle, and an extension targeting that handle adds option pickers, variant matrices, etc.
- Customer-group-specific layouts: a logged-in B2B customer sees pricing tiers added via `customer.group.b2b` handle.
- A/B-test variants where the chosen branch contributes a handle that adds the variant's placements.

**Current state**
- No mechanism for adding handles during the request. The middleware is the only place handle keys are computed, and it uses a single static formula.
- The compiled artifact is a flat `array<handleKey, PreparedTree>`. There is no concept of merging multiple trees on a single request.

**Open design questions**
- Where do dynamic handles come from? Options: (a) a `HandleProvider` interface registered per route, (b) controller actions return a list of additional handles alongside the response, (c) a request attribute that anything in the middleware chain can append to. Option (a) is the most "framework-y" — fits with `ContextProvider`. Option (c) is the most flexible but easiest to misuse.
- How do dynamic handles interact with `Provide` / context? The handle producer probably needs access to resolved context (you can only know the product type after the product is loaded). Implies handle resolution happens after context providers run.
- Are dynamic handles layout-contributing (they bring their own placements) or extension-only (they only contribute extensions to the base route's tree)? Extension-only is much simpler. Layout-contributing implies merging slots from multiple trees.
- Validation: dynamic handles can't be statically validated against routes, so the compiler can't catch "extension targets nonexistent handle" errors for them. Need a separate validation strategy.

**Dependencies**: realistically requires [#1 Default handle](#1-default-handle) to land first, since dynamic handles use the same multi-tree merging machinery.

**Estimated effort**: medium-large. The bulk is the handle-collection pipeline (interface + middleware integration) and the tree-merging logic.

## 3. Handle inheritance

**Goal**: Express "handle B inherits from handle A" so an extension targeting A automatically applies wherever B is active. Magento equivalent: handle update XML files that contain `<update handle="..."/>` declarations.

**Use cases**
- A `catalog.product.type.configurable` handle inherits from `catalog.product.view` — anything added to the generic product view applies to configurable products too, without duplication.
- A theme can declare its mobile-specific handle inherits from the desktop one, then override or add only what differs.
- Layered storefront variations: `storefront.checkout.express` inherits from `storefront.checkout`, which inherits from `storefront`.

**Current state**
- The `extends:` mechanism on `Layout` is the closest thing — but it's for shell/structure inheritance (a layout extends another `LayoutDefinition`), not for handle-name inheritance. Extensions don't follow the `extends:` chain.
- Each handle's resolved tree is independent; extensions targeting one handle never bleed into another.

**Open design questions**
- Where is the inheritance declared? On the handle itself (some new value object), or on each extension (a `inheritsFrom:` flag)? Magento puts it on the layout update file.
- Is this a separate concept from dynamic handles, or just a different way to express the same merging? Arguably `extends: 'catalog.product.view'` on a configurable-product layout is the same as saying "the configurable handle inherits the view handle's extensions."
- Multiple inheritance: can a handle inherit from multiple parents? Magento allows it; gets messy.
- Compile-time only or also dynamic? If a dynamic handle can declare inheritance from a static one, the runtime merger needs to walk that chain.

**Dependencies**: builds on [#1 Default handle](#1-default-handle) infrastructure. Likely best designed alongside [#2 Dynamic handles](#2-dynamic-handles) since they share the merge semantics.

**Estimated effort**: medium. The hardest part is deciding the data model; the merging code is mostly the same machinery as the other two.

## Notes

- All three features share the same underlying need: **a single request resolving more than one handle and merging the results**. They should probably be designed together even if shipped incrementally.
- A reasonable sequencing: ship #1 first as a forcing function for the multi-tree merge machinery, then layer #2 (dynamic) and #3 (inheritance) on top.
- Compile-time validation gets harder with each feature. Worth deciding upfront which validations stay at compile time and which become runtime warnings.
