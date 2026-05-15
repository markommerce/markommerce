# Task 018: Demo route, Layout, Controller, gating middleware, and Latte template

**Status**: completed
**Depends on**: 013, 014, 015, 016, 017
**Retry count**: 0

## Description

Wire the demo from PHP to pixel: a `DemoLayout` component (using `marko/layout` attributes) declaring a `content` slot, a `DemoController` whose `index()` method is annotated `#[Get('/markommerce/_demo')]` and `#[Middleware([EnsureFrontendDemoEnabledMiddleware::class])]`, a `DemoCounterComponent` rendering into the `content` slot, a Latte template at `packages/frontend-demo/resources/views/counter.latte` that embeds `<markommerce-counter start-value="0"></markommerce-counter>`, and a `base.latte` layout calling `{vite()}` in `<head>`. The middleware gates the route at runtime by checking `frontend_demo.enabled` and returning a 404 Response before the controller (and the LayoutMiddleware) runs. Update `module.php` to declare bindings. Cover with Pest feature tests.

## Context

- **Gating mechanism — important:** verified against `marko/layout/src/Middleware/LayoutMiddleware.php`, the middleware ignores the controller's return value when `#[Layout]` is present (the layout always renders). Returning a 404 Response from the controller body therefore does **not** disable the route. The gate must run BEFORE LayoutMiddleware. Implementation: a dedicated `EnsureFrontendDemoEnabledMiddleware` registered on the controller via the routing-level `#[Middleware]` attribute — when the config flag is false it short-circuits with a 404 Response; when true it calls `$next($request)`.
- **`#[Get]` is `Attribute::TARGET_METHOD`** (verified against `marko/routing/src/Attributes/Get.php`). It goes on the controller's action method, not the class.
- Files (PHP):
  - `packages/frontend-demo/src/Layout/DemoLayout.php` — `#[Component(template: 'frontend-demo::layout/base', slots: ['content'])]`
  - `packages/frontend-demo/src/Component/DemoCounterComponent.php` — `#[Component(template: 'frontend-demo::counter', handle: 'frontend_demo_counter', slot: 'content')]`
  - `packages/frontend-demo/src/Middleware/EnsureFrontendDemoEnabledMiddleware.php` — `implements Marko\Routing\Middleware\MiddlewareInterface`. Constructor injects `FrontendDemoConfig`. `handle()` returns `new Response(body: 'Not Found', statusCode: 404)` when disabled, otherwise `$next($request)`.
  - `packages/frontend-demo/src/Controller/DemoController.php` — class has `#[Layout(DemoLayout::class)]`; method `public function index(): Response` has `#[Get('/markommerce/_demo')]` and `#[Middleware([EnsureFrontendDemoEnabledMiddleware::class])]`. Returns a no-op Response (LayoutMiddleware ignores it but the framework requires a return).
  - `packages/frontend-demo/src/Config/FrontendDemoConfig.php` — small `readonly class` reading `frontend_demo.enabled` from `ConfigRepositoryInterface`.
  - `packages/frontend-demo/module.php` — empty array or middleware binding only; controllers/components/layouts are auto-discovered.
- Files (templates):
  - `packages/frontend-demo/resources/views/layout/base.latte` — defines page chrome, calls `{vite 'packages/frontend-demo/resources/js/main.ts'}` in `<head>`, defines `{block content}{/block}`. **Also imports Open Props** via a `<link rel="stylesheet" href="/build/assets/open-props.{hash}.css">` — Open Props is bundled into the Vite output via the demo's `main.ts` import (`import 'open-props/style.css'`), so `{vite()}` emits the link tag automatically with the right order (Vite emits stylesheet links before the script tag).
  - `packages/frontend-demo/resources/views/counter.latte` — embeds `<markommerce-counter start-value="0"></markommerce-counter>` plus a small JS snippet logging the `markommerce:counter:changed` event for visual confirmation.
- Templates resolve as `frontend-demo::layout/base` and `frontend-demo::counter` because the module name is `markommerce/frontend-demo` and `ModuleTemplateResolver` matches last segments (verified in `marko/view/src/ModuleTemplateResolver.php`).
- Config: `frontend_demo.enabled` lives in `packages/frontend-demo/config/frontend_demo.php` (from task 015) and defaults to `false`.
- Tests:
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php` — boots the app with `frontend_demo.enabled = true`, hits `/markommerce/_demo`, asserts 200 + rendered counter markup + Vite tags in `<head>`.
  - Same file: boots the app with `frontend_demo.enabled = false`, hits `/markommerce/_demo`, asserts 404 short-circuited by the middleware.
  - Booting a Marko app in tests follows the pattern in `/home/michal/www/marko/marko/packages/core/tests/Unit/ApplicationTest.php`.

## Requirements (Test Descriptions)

- [ ] `it returns 200 OK when frontend_demo.enabled is true and the demo route is requested`
- [ ] `it returns 404 when frontend_demo.enabled is false because EnsureFrontendDemoEnabledMiddleware short-circuits before LayoutMiddleware runs`
- [ ] `the #[Get] attribute is placed on the DemoController action method (not the class)`
- [ ] `the EnsureFrontendDemoEnabledMiddleware is registered via #[Middleware([...])] on the controller action method`
- [ ] `it embeds the <markommerce-counter> element in the rendered response body`
- [ ] `it includes the marko/vite generated script and link tags in the response head when the route is enabled`
- [ ] `it uses the DemoLayout component as the layout for the route`
- [ ] `it composes the DemoCounterComponent into the content slot of DemoLayout`
- [ ] `the demo main.ts imports open-props/style.css so Vite emits the Open Props stylesheet link in the head`
- [ ] `it loads the @markommerce/frontend cascade layers and tokens CSS in the head before component-level CSS`
- [ ] `the controller and middleware inject FrontendDemoConfig via constructor and read the enabled flag from there`
- [ ] `it follows project naming conventions: the FrontendDemoConfig parameter is named frontendDemoConfig`

## Acceptance Criteria

- Feature tests pass.
- PHPStan level 8 clean.
- PHPCS clean.
- Manual smoke test (requires the consuming app — typically the playground — to install `markommerce/frontend-demo` as a path repository, set `frontend_demo.enabled=true`, and run with `MARKOMMERCE_CONSUMER_PUBLIC=../playground/public/build`): `docker compose exec node npm run dev`, then visit `http://localhost:8000/markommerce/_demo` — counter renders, increments, suffix mixin shows, browser console logs each `markommerce:counter:changed` event. Document this end-to-end smoke-test procedure in the demo's docs page (task 020).

## Implementation Notes

(Left blank — filled in by programmer during implementation)
