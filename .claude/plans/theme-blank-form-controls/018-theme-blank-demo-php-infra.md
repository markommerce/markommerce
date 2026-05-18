# Task 018: PHP Infrastructure for `theme-blank-demo`

**Status**: completed
**Depends on**: 017
**Retry count**: 0

## Description
Create the PHP-side plumbing for the new package: config wrapper, route-gating middleware, layout component, controller, and the showcase component (which composes the view into the layout's content slot). The route exposed here is `GET /markommerce/_demo/theme-blank`. All classes follow the equivalent classes in `frontend-demo` exactly — only names and the route path change.

## Context
- Mirror the corresponding classes in `packages/frontend-demo/src/`:
  - `Config/FrontendDemoConfig.php`
  - `Middleware/EnsureFrontendDemoEnabledMiddleware.php`
  - `Layout/DemoLayout.php`
  - `Controller/DemoController.php`
  - `Component/DemoCounterComponent.php`
- Related files (CREATE):
  - `packages/theme-blank-demo/src/Config/ThemeBlankDemoConfig.php`
  - `packages/theme-blank-demo/src/Middleware/EnsureThemeBlankDemoEnabledMiddleware.php`
  - `packages/theme-blank-demo/src/Layout/ThemeBlankDemoLayout.php`
  - `packages/theme-blank-demo/src/Controller/ThemeBlankDemoController.php`
  - `packages/theme-blank-demo/src/Component/ThemeBlankShowcaseComponent.php`
- Patterns to follow: each class is a near-line-by-line clone of the matching frontend-demo class with `FrontendDemo` → `ThemeBlankDemo` and the route URL changed from `/markommerce/_demo` to `/markommerce/_demo/theme-blank`.
- Test scaffolding: tests for the routing/middleware behaviour live in `tests/Feature/ThemeBlankDemoControllerTest.php` and are populated by task 019 (which adds the view) and task 021 (which migrates per-element assertions). This task only needs unit-level attribute/reflection tests.

## Class Shapes

### `ThemeBlankDemoConfig`

```php
<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class ThemeBlankDemoConfig
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function isEnabled(): bool
    {
        if (!$this->configRepository->has('theme_blank_demo.enabled')) {
            return false;
        }

        return $this->configRepository->getBool('theme_blank_demo.enabled');
    }
}
```

### `EnsureThemeBlankDemoEnabledMiddleware`

Mirror `EnsureFrontendDemoEnabledMiddleware`, swap the injected config:

```php
readonly class EnsureThemeBlankDemoEnabledMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ThemeBlankDemoConfig $themeBlankDemoConfig,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->themeBlankDemoConfig->isEnabled()) {
            return new Response('Not Found', 404);
        }
        return $next($request);
    }
}
```

### `ThemeBlankDemoLayout`

```php
#[Component(template: 'theme-blank-demo::layout/base', slots: ['content'])]
class ThemeBlankDemoLayout {}
```

### `ThemeBlankDemoController`

```php
#[Layout(ThemeBlankDemoLayout::class)]
class ThemeBlankDemoController
{
    #[Get('/markommerce/_demo/theme-blank')]
    #[Middleware([EnsureThemeBlankDemoEnabledMiddleware::class])]
    public function index(): void {}
}
```

### `ThemeBlankShowcaseComponent`

```php
#[Component(template: 'theme-blank-demo::showcase', handle: 'default', slot: 'content')]
class ThemeBlankShowcaseComponent {}
```

## Requirements (Test Descriptions)

Tests go in `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` (new file). These are reflection-level tests; route-behaviour tests are added in task 019.

- [ ] `it the #[Get] attribute is placed on the ThemeBlankDemoController action method with path /markommerce/_demo/theme-blank`
- [ ] `it the EnsureThemeBlankDemoEnabledMiddleware is registered via #[Middleware([...])] on the controller action method`
- [ ] `it uses the ThemeBlankDemoLayout component as the layout for the route`
- [ ] `it composes the ThemeBlankShowcaseComponent into the content slot of ThemeBlankDemoLayout`
- [ ] `it the EnsureThemeBlankDemoEnabledMiddleware injects ThemeBlankDemoConfig via constructor and reads the enabled flag from there` (the controller itself takes NO constructor dependencies — mirror `DemoController` exactly which has no constructor)
- [ ] `it follows project naming conventions: the ThemeBlankDemoConfig constructor parameter on EnsureThemeBlankDemoEnabledMiddleware is named themeBlankDemoConfig`
- [ ] `it ThemeBlankDemoConfig.isEnabled() returns false when theme_blank_demo.enabled config key is absent`
- [ ] `it ThemeBlankDemoConfig.isEnabled() returns the config repository's boolean value when the key is present`

## Acceptance Criteria
- All requirements have passing tests
- All classes follow code standards: `declare(strict_types=1)`, no `final`, constructor injection, interface parameter naming (`themeBlankDemoConfig`), no traits, no magic methods
- `composer test` continues to pass for both `frontend-demo` and the new `theme-blank-demo` (no regressions)
- `phpstan` passes on the new files
- `phpcs` passes on the new files

## Implementation Notes
(Left blank — filled in by programmer during implementation)
