# Task 015: Scaffold markommerce/frontend-demo Composer package

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description

Create the PHP-side scaffold for `markommerce/frontend-demo` at `packages/frontend-demo/`: composer.json (type `marko-module`), an empty `module.php` (filled in task 018), `src/` namespaced under `Markommerce\FrontendDemo\`, `tests/`, and a slim `README.md` placeholder. Add a `frontend_demo.enabled` config entry defaulting to `false` so production sites can disable the demo route entirely. Register the package as a `require-dev` of the repo-root `composer.json` (dev-only — the demo must not ship in production).

## Context

- Composer name: `markommerce/frontend-demo`. Namespace: `Markommerce\FrontendDemo\`. Type: `marko-module`.
- Required Composer deps: `php: ^8.5`, `marko/core: self.version`, `marko/config: self.version`, `marko/routing: self.version`, `marko/view: self.version`, `marko/view-latte: self.version`, `marko/layout: self.version`, `markommerce/frontend: self.version`. `marko/config` is required for reading the `frontend_demo.enabled` flag in the gating middleware (task 018).
- Dev deps: `marko/testing: self.version`, `pestphp/pest: ^4.0`.
- `extra.marko.module: true`.
- Repo-root composer.json: add `"markommerce/frontend-demo": "self.version"` to `require-dev` (not `require`).
- Reference pattern: `/home/michal/www/marko/marko/packages/vite/composer.json` (NOT `packages/core/composer.json` — that's a placeholder library, not a marko-module).
- Related files: `packages/frontend-demo/{composer.json, module.php, README.md}`, `packages/frontend-demo/src/.gitkeep`, `packages/frontend-demo/tests/Unit/.gitkeep`, `packages/frontend-demo/config/frontend_demo.php`.

## Requirements (Test Descriptions)

- [ ] `it has a composer.json declaring name markommerce/frontend-demo and type marko-module`
- [ ] `it requires markommerce/frontend, marko/core, marko/routing, marko/view, marko/view-latte, marko/layout all at self.version`
- [ ] `it autoloads Markommerce\\FrontendDemo\\ from src/`
- [ ] `it autoloads Markommerce\\FrontendDemo\\Tests\\ from tests/`
- [ ] `it sets extra.marko.module to true`
- [ ] `it declares type marko-module so it matches the convention used by every other Marko module package`
- [ ] `it requires marko/config so the route-gating middleware (task 018) can read the enabled flag from the config repository`
- [ ] `it provides config/frontend_demo.php with the enabled key defaulting to false`
- [ ] `the repo-root composer.json now requires markommerce/frontend-demo in require-dev`
- [ ] `it has a placeholder README.md pointing at the docs site`
- [ ] `composer validate exits 0 on the new package manifest`

## Acceptance Criteria

- `composer install` (dev mode) resolves the new package.
- Production `composer install --no-dev` does not pull the demo.
- PHPStan and PHPCS clean on the empty scaffold.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
