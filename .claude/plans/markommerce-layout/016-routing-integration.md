# Task 016: Routing Integration — `MarkommerceLayoutMiddleware`

**Status**: completed
**Depends on**: 013
**Retry count**: 0

## Description
Create the middleware that connects routing to the layout renderer. On a matched route it looks up the compiled `PreparedTree` for the route's handle, runs the controller action for side effects, and returns the rendered layout as the response. It is registered as a **module-declared global middleware**.

## Context
- Implement as a `marko/routing` `MiddlewareInterface` (`handle(Request, callable $next): Response`).
- **Middleware registration — module-declared global middleware (the `marko` repo is on the `local-develop` branch):** Marko's `local-develop` branch added module-declared global middleware. A package's `module.php` may declare a `globalMiddleware` key — a list of either class-strings or `['class' => Foo::class, 'priority' => N]` arrays. `Marko\Core\Module\GlobalMiddlewareResolver` collects these from every loaded module, sorts by `priority` ascending (lower priority value = runs earlier), and deduplicates by class. There is NO hardcoded global middleware list anymore — even `marko/layout`'s own `LayoutMiddleware` is now declared this way (priority 30) in `packages/layout/module.php`. So `MarkommerceLayoutMiddleware` IS registered as a global middleware — by adding a `globalMiddleware` entry to this package's `module.php`. It is NOT applied per-route via `#[Middleware]`.
- **Priority:** declare `MarkommerceLayoutMiddleware` at priority **30** (the same "layout" tier as `marko/layout`'s `LayoutMiddleware`; built-ins on `local-develop` are page-cache 10, session 20, layout 30). Document the chosen priority in Implementation Notes.
- **Coexistence with `marko/layout`'s `LayoutMiddleware`:** `marko/layout` stays installed in the monorepo (`theme-blank-demo` / `frontend-demo` still require it), so its global `LayoutMiddleware` is still active alongside `MarkommerceLayoutMiddleware`. They must not double-render. This is naturally safe: once `CategoryController` drops its `#[Layout]` attribute (task 017), `marko/layout`'s `LayoutResolver` throws `LayoutNotFoundException` for that route and `LayoutMiddleware` cleanly falls through via `$next`. Symmetrically, `MarkommerceLayoutMiddleware` falls through for any route with no compiled `PreparedTree`. For any given route at most one of the two ever renders. Verify the no-double-render behavior in a Feature test.
- Flow (mirrors `marko/layout/src/Middleware/LayoutMiddleware.php`):
  1. Match the request to a route (`RouteMatcherInterface`). No match → `$next($request)`.
  2. Compute the `handleKey` from the matched route's controller + action (same format as task 009 — reuse it).
  3. Load the artifact (task 011 reader) and look up the `PreparedTree` for that handle. If none exists, call `$next($request)` and return — routes without a markommerce layout fall through to normal dispatch (and to `marko/layout`'s middleware).
  4. If a layout exists: run the controller action via `$next($request)` for side effects (authorization, redirects). If the controller returns a redirect or non-200 short-circuit response, honor it and return that instead of rendering the layout. Decide how a controller signals "stop, don't render the layout" (e.g. any non-null non-200, or a redirect) and document.
  5. Otherwise hand the `PreparedTree`, `Request`, and route parameters to the renderer (task 013) and return the resulting HTML `Response`.
- Register the middleware binding in the package `module.php` (`bindings`/`singletons` so the container resolves it) AND declare it under the `globalMiddleware` key.
- The artifact must exist at runtime — in production it is built at deploy; in dev `CompileIfStaleMiddleware` (task 015) ensures freshness. If the artifact is missing entirely, throw a clear error suggesting `layout:compile`.
- Patterns to follow: `marko/layout/src/Middleware/LayoutMiddleware.php`; `marko/layout/module.php` on `local-develop` for the `globalMiddleware` declaration shape; `Marko\Core\Module\GlobalMiddlewareResolver` for resolution semantics.

## Requirements (Test Descriptions)
- [ ] `it renders the layout for a route that has a compiled handle`
- [ ] `it falls through to normal dispatch for a route with no layout`
- [ ] `it runs the controller action before rendering the layout`
- [ ] `it honors a controller redirect instead of rendering the layout`
- [ ] `it passes route parameters through to the renderer`
- [ ] `it throws a clear error when the compiled artifact is missing`
- [ ] `it is declared as a global middleware in module.php`
- [ ] `it does not double-render when marko/layout's LayoutMiddleware is also active`

## Acceptance Criteria
- All requirements have passing tests
- `MarkommerceLayoutMiddleware` is declared in `module.php` under `globalMiddleware` with an explicit priority
- Routes without a markommerce layout are unaffected (clean fall-through)
- Controller side effects (auth, redirects) are honored before render
- Controller short-circuit signal + chosen priority documented in Implementation Notes
- Coexistence with the still-active `marko/layout` global `LayoutMiddleware` verified — no double render

## Implementation Notes
(Left blank - filled in by programmer during implementation)
