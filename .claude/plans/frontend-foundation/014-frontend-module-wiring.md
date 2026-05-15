# Task 014: Wire markommerce/frontend module.php (+ Latte engine preference)

**Status**: completed
**Depends on**: 003, 013

**Retry count**: 0

## Description

Replace the placeholder `packages/frontend/module.php` with a real Marko module manifest. The kernel registers its `ViteExtension` with the Latte engine by shipping `Markommerce\Frontend\View\Latte\MarkommerceLatteEngineFactory` — a subclass of `Marko\View\Latte\LatteEngineFactory` annotated `#[Preference(LatteEngineFactory::class)]` — whose `create()` calls `parent::create()` and then adds `ViteExtension` to the returned `Latte\Engine`. The module also ships a default `vite.*` config block at `packages/frontend/config/vite.php`. Verify the wiring with a Pest feature test that boots a minimal Marko app and renders a template using `{vite()}`.

## Context

- **Why a Preference, not a `latte.extensions` config:** verified against the marko/view-latte source — `Marko\View\Latte\LatteEngineFactory` is `readonly class` and hard-codes `addExtension(new SlotExtension())`. There is no discovery mechanism for module-provided Latte extensions. Marko's `#[Plugin]` attribute cannot intercept `readonly class` via the concrete-subclass strategy (verified in `marko/core/src/Plugin/InterceptorClassGenerator.php` — throws `PluginException::cannotInterceptReadonly()`), and the factory is not bound to an interface. The clean workaround is `#[Preference]`-based subclassing.
- File: `packages/frontend/src/View/Latte/MarkommerceLatteEngineFactory.php` (new). Annotated `#[Preference(LatteEngineFactory::class)]`. Constructor injects `ViewConfig` (parent's dep) plus `ViteExtension` (from this package). `create()` returns `$engine = parent::create(); $engine->addExtension($this->viteExtension); return $engine;`. NOT `final` (per project standards).
- File: `packages/frontend/module.php` (replace the empty placeholder from task 003). Returns an array — likely just `[]` since the `Preference` attribute is auto-discovered by Marko's `PreferenceDiscovery` scanning the module's `src/` directory.
- File: `packages/frontend/config/vite.php` (new — Marko's config layer auto-discovers `config/*.php` per module; verified by `marko/vite`'s own `config/vite.php`).
- Defaults to ship (mirroring `marko/vite/config/vite.php`):
  - `entry` → `'packages/frontend-demo/resources/js/main.ts'`
  - `useDevServer` → `false`
  - `devServerUrl` → `'http://localhost:5173'`
  - `buildDirectory` → `'build'` (relative to `public/`)
  - `manifestFilename` → `'.vite/manifest.json'`
  - `devServerStylesheets` → `[]`
  - Note: these keys are scoped under the top-level `vite` namespace by Marko's config loader. The file returns the inner array directly (matches `marko/vite/config/vite.php`).
- Feature test: boots a tiny Marko app with `marko/core`, `marko/config`, `marko/view`, `marko/view-latte`, `marko/vite`, and our `markommerce/frontend`; resolves the bound `ViewInterface` and asserts the underlying Latte engine has the `vite` function registered. Use Marko's `Application` class to boot; the `marko/core/tests/Unit/ApplicationTest.php` shows the pattern.

## Requirements (Test Descriptions)

- [ ] `it returns a bindings array with no extra entries — Preference auto-discovery handles the engine factory override`
- [ ] `it provides default config at config/vite.php setting useDevServer to false`
- [ ] `it provides a default entry pointing at the frontend-demo main.ts`
- [ ] `the MarkommerceLatteEngineFactory has the #[Preference(LatteEngineFactory::class)] attribute`
- [ ] `the MarkommerceLatteEngineFactory::create() returns an Engine with both SlotExtension and ViteExtension registered`
- [ ] `it loads cleanly in a Pest feature test that boots a minimal Marko app`
- [ ] `the Latte engine reports the vite function as registered after the module boots`
- [ ] `it does not declare LatteEngineFactory as a binding — the Preference attribute handles the swap`

## Acceptance Criteria

- Pest feature test green.
- PHPStan level 8 clean.
- PHPCS clean.
- module.php conforms to the convention used by other markommerce packages (small, focused, no logic).
- `MarkommerceLatteEngineFactory` is not `final` so downstream packages can extend it to add more extensions.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
