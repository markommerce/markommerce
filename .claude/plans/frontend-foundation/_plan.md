# Plan: Frontend Foundation (Phase 1)

## Created
2026-05-15

## Status
completed

## Objective
Stand up the kernel for Markommerce's frontend layer — Vite + TypeScript build pipeline, component registry, hooks registry, DOM event helper, CSS Cascade Layer foundation, Open Props-backed design tokens, and a PHP Latte helper for asset loading — packaged as a new `markommerce/frontend` Composer module. Prove the kernel end-to-end with a separate `markommerce/frontend-demo` package that renders a working `<markommerce-counter>` Web Component at a demo route.

## Related Issues
none

## Discovery Notes

**Reality vs. spec (the spec is the user's design doc, not the codebase):**

- Markommerce uses `packages/` (Composer monorepo), not `modules/`. Each package is a `type: marko-module` with `extra.marko.module: true`. Only `packages/core/` currently exists on disk (and it is `type: library`, not `marko-module`) — references in the spec to `catalog`/`money` are forward-looking. The spec's `modules/markommerce-{name}/` maps directly to `packages/{name}/` here.
- `marko/vite` already exists in the Marko framework and handles PHP-side asset manifest/dev-server resolution. The plan **uses it** rather than reinventing the PHP plumbing the spec assumes we'd build.
- `marko/layout` already exists and exposes the attribute-driven component composition the spec describes: `#[Component(template, handle, slot, slots, sortOrder)]` + `#[Layout(class)]`. Module templates resolve as `module::path/to/template` to `{modulePath}/resources/views/{path}.latte`.
- `marko/view-latte` is the active Latte 3 driver.
- No JS tooling exists yet in the markommerce repo (no `package.json`, `vite.config.ts`, `tsconfig.json`, no `resources/` directories under `packages/`). Greenfield for everything JS/CSS.
- All execution is Docker-only (per `CLAUDE.local.md`); compose.yaml has `app`, `postgres`, `redis` but no Node service.
- Existing `markommerce/core` package is empty — keeping it minimal; the frontend foundation lives in a **new** `markommerce/frontend` package.

**Resolved during clarification (Phase 2):**

- **Scope:** Kernel-only, area-agnostic. No storefront/admin distinction in Phase 1.
- **Package home:** New `markommerce/frontend` Composer + npm package (npm name: `@markommerce/frontend`).
- **Demo:** Separate `markommerce/frontend-demo` package, dev-only. Ships a `<markommerce-counter>` widget visible at a `/markommerce/_demo` route.
- **Module discovery:** Vite plugin scans `packages/*/package.json` for a `markommerce.extension` field. JS-side authoritative (most familiar idiom for frontend devs). PHP-side `composer.json` keeps `extra.marko.module: true` for Marko's existing module loader.
- **First component:** Trivial `markommerce-counter` widget — proves registry + mixin + tokens + CSS cascade layers without depending on cart/money/catalog backends.
- **Node runtime:** Add a `node` service to `compose.yaml` (node:22-alpine).
- **JS test framework:** Vitest + happy-dom (browser-mode opt-in later if needed).
- **Latte asset helper:** Include a `{vite()}` Latte function wrapping `Marko\Vite\Vite` in Phase 1.

**Defaults adopted without explicit user input (overridable in Phase 7 review):**

- Lit `^3`, Open Props pinned via npm.
- TypeScript: `strict`, `noUncheckedIndexedAccess`, `noImplicitOverride`, `useDefineForClassFields: false` (required for Lit decorators), `experimentalDecorators: true`.
- Light DOM as default render root for components; Shadow DOM opt-in.
- CSS Cascade Layers order: `reset, tokens, base, components, modules, theme, utilities`.
- Built assets land at `public/build/` (marko/vite default).
- ESLint + Stylelint + Prettier baseline.
- DOM event map: skeleton `MarkommerceEventMap` interface with no concrete events; domain modules add events via declaration merging when they ship.
- npm workspaces (`packages/*`) with a single root `vite.config.ts` and `tsconfig.json`.
- Theme system primitives (CSS-only token override) work today via cascade layers; the dynamic `ThemeRegistry` PHP class is deferred to Phase 4 — Phase 1 does **not** add a theme service.

## Scope

### In Scope

- New `markommerce/frontend` Composer + npm package (kernel).
- New `markommerce/frontend-demo` Composer + npm package (demo + living docs).
- Component registry (`registerBase`, `addMixin`, `defineAllComponents`, `getRegisteredComponents`, `getMixinChain`).
- Hooks registry (`registerHook`, `runHook`, generic-typed `HookRegistry` interface).
- DOM event helper + `MarkommerceEventMap` skeleton declared globally.
- CSS foundation: `layers.css` (cascade layer declaration), `tokens.css` (Markommerce semantic tokens layered on Open Props).
- Vite scanner plugin: discovers `packages/*/package.json` entries with a `markommerce.extension` field, sorts by priority, generates a side-effect import file.
- Vite + TypeScript + PostCSS + Stylelint + ESLint + Prettier at the repo root.
- Latte function/extension that wraps `Marko\Vite\Vite` so templates load assets idiomatically.
- `Node` service in `compose.yaml`.
- One working Web Component: `<markommerce-counter>` (no external dependencies, demonstrates mixin extension point).
- Demo Latte template + Layout + Controller in `markommerce/frontend-demo` rendering the counter at a gated route.
- READMEs + docs pages for both new packages.
- "Writing a Markommerce frontend module" docs page in the docs site.

### Out of Scope

- Storefront pages (product, category, cart, checkout views) — those land in domain-specific packages later.
- Admin UI integration — orthogonal.
- Cart/customer/checkout HTTP endpoints — no real APIs are wired from `<markommerce-counter>`; it's purely local-state.
- Dynamic theme picker / `ThemeRegistry` PHP service — Phase 4.
- Critical CSS extraction, per-route code splitting — Phase 5.
- Lit SSR / Declarative Shadow DOM — explicit non-goal per the spec.
- Image optimization service, form validation sharing, i18n strategy, semver/deprecation policy — open questions in the spec, deferred.
- A Markommerce reviews module — Phase 3 of the broader roadmap.
- Modifying or wiring frontend assets into the existing `markommerce/catalog` package.

## Success Criteria

- [ ] `docker compose exec node npm run build` produces a hashed bundle in the configured `outDir` (defaults to `public/build/` inside the markommerce repo when `MARKOMMERCE_CONSUMER_PUBLIC` is unset) with a valid `.vite/manifest.json`.
- [ ] `docker compose exec node npm run dev` serves the kernel + counter via the Vite dev server. With the playground (or another consuming app) configured to install `markommerce/frontend-demo` and `frontend_demo.enabled=true`, the demo route renders `<markommerce-counter>` correctly when visited.
- [ ] `docker compose exec node npm test` runs vitest and exits green; the kernel has ≥80% coverage on registry, hooks, events, and the Vite scanner plugin.
- [ ] `docker compose exec node npm run typecheck` exits 0.
- [ ] `composer test` (PHP) stays green and covers the new Latte helper + Vite binding.
- [ ] `./vendor/bin/phpstan analyse` and `./vendor/bin/phpcs` stay green on the new PHP code.
- [ ] The counter increments in the browser, applies a mixin-injected suffix when the demo registers a `LabelSuffixMixin` (proof of mixin composition end-to-end), and dispatches a `markommerce:counter:changed` DOM event.
- [ ] CSS tokens are inheritable: changing `--color-primary` in a global `<style>` overrides the counter's button colour without rebuilding JS.
- [ ] Both new packages have slim READMEs pointing at the docs site, plus dedicated docs pages at `docs/src/content/docs/packages/frontend.md` and `frontend-demo.md`.
- [ ] A "Writing a Markommerce frontend module" guide exists in the docs site explaining `package.json` `markommerce` block, base registration, mixin authoring, hook authoring, and DOM event conventions.

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Bootstrap repo-level Node tooling (root package.json, npm workspaces, .gitignore, .nvmrc) | - | completed |
| 002 | Add `node` service to `compose.yaml` (node:22-alpine, port 5173) | - | completed |
| 003 | Scaffold `markommerce/frontend` Composer package (composer.json, module.php, src/, tests/) | - | completed |
| 004 | Scaffold `@markommerce/frontend` npm workspace package (package.json, tsconfig.json) | 001, 003 | completed |
| 005 | Root TypeScript config + path aliases + per-package tsconfig refs | 001, 004 | completed |
| 006 | Root ESLint + Prettier + Stylelint + PostCSS config files | 001, 005 | completed |
| 007 | Implement component registry (`registerBase`, `addMixin`, `defineAllComponents`, introspection helpers) | 004, 005 | completed |
| 008 | Implement hooks registry (`registerHook`, `runHook`, generic-typed `HookRegistry`) | 004, 005 | completed |
| 009 | Implement DOM events helper + global `MarkommerceEventMap` declaration | 004, 005 | completed |
| 010 | Author CSS foundation: `layers.css` + `tokens.css` (Open Props + semantic tokens) | 004 | completed |
| 011 | Implement Vite scanner plugin (discovers `packages/*/package.json`, generates `extensions.ts` import file) | 004, 005 | completed |
| 012 | Author root `vite.config.ts` wiring the scanner plugin, aliases, build options aligned with marko/vite manifest | 005, 011 | completed |
| 013 | Implement Latte `{vite}` function/extension wrapping `Marko\Vite\Vite` (PHP, Pest tests) | 003 | completed |
| 014 | Wire `markommerce/frontend` module.php + `MarkommerceLatteEngineFactory` Preference (registers ViteExtension via subclassing) + default `config/vite.php` | 003, 013 | completed |
| 015 | Scaffold `markommerce/frontend-demo` Composer package | 003 | completed |
| 016 | Scaffold `@markommerce/frontend-demo` npm workspace package + main entry (`main.ts`) | 004, 010, 015 | completed |
| 017 | Implement `MarkommerceCounterElement` Lit component (Light DOM, tokens, mixin-friendly template methods, event dispatch) | 007, 010, 016 | completed |
| 018 | Implement demo Controller (+ gating middleware) + Latte template rendering the counter + `LabelSuffixMixin` registration | 013, 014, 015, 016, 017 | completed |
| 019 | Write `markommerce/frontend` README (slim) + docs page at `docs/src/content/docs/packages/frontend.md` | 007, 008, 009, 010, 011, 012, 013, 014 | completed |
| 020 | Write `markommerce/frontend-demo` README (slim) + docs page at `docs/src/content/docs/packages/frontend-demo.md` | 015, 016, 017, 018 | completed |
| 021 | Write "Writing a Markommerce frontend module" guide in the docs site | 007, 008, 009, 011, 017 | completed |

## Architecture Notes

**Module structure (every Markommerce frontend module follows this shape):**

```
packages/{name}/
├── composer.json          # type: marko-module; extra.marko.module: true
├── module.php             # PHP bindings + Latte extension registrations
├── package.json           # npm workspace member; "markommerce": { "extension": ..., "priority": ... }
├── src/                   # PHP source
├── resources/
│   ├── js/
│   │   ├── index.ts       # JS entry — calls registerBase/addMixin/registerHook
│   │   ├── components/    # Lit components
│   │   ├── mixins/        # Functional mixins
│   │   └── types.ts       # Public types + declaration merging
│   ├── css/
│   │   ├── tokens.css     # Optional semantic-token contributions
│   │   └── components.css # Optional component styles
│   └── views/             # Latte templates (resolved as `{name}::path/to/template`)
└── tests/
    ├── Unit/              # Pest 4
    ├── Feature/
    └── js/                # Vitest specs (next to component files preferred — kept here too if cross-cutting)
```

**Where build artefacts live:**

- Vite output: `public/build/` (matches `marko/vite` default `vite.buildDirectory`).
- Manifest: `public/build/.vite/manifest.json` (the path `Marko\Vite\Vite` reads).
- `public/build/` is `.gitignore`d.

**Discovery flow (build-time):**

1. `vite build` (or `vite dev`) starts.
2. Scanner plugin reads every `packages/*/package.json` matching a `markommerce` block.
3. Scanner emits `packages/frontend-demo/resources/js/.generated/extensions.ts` (the demo is the bundle's entry point in Phase 1; future apps swap this destination via plugin options).
4. The generated file is `import './path/to/module1'; import './path/to/module2';` ordered by priority (core/kernel first, then ascending priority, alphabetical tiebreak).
5. The demo's `main.ts` imports the generated file then calls `defineAllComponents()`.

**Composition flow (runtime):**

1. Each module's `index.ts` calls `registerBase(tagName, BaseClass)` or `addMixin(tagName, mixin, { source, priority })`.
2. After the generated file finishes executing, `main.ts` calls `defineAllComponents()` which folds mixins onto each base and calls `customElements.define()` per registered tag.
3. Custom Elements hydrate any pre-existing server-rendered light DOM (the Phase 1 counter has no server-rendered fallback content — that pattern lands when domain components ship).

**Light DOM by default:**

The kernel's components extend `LitElement` but override `createRenderRoot()` to return `this`. Confirmed in `markommerce-counter`. Shadow DOM is available by not overriding the root; documented in the guide.

**No `final` JS classes — analogue of the PHP rule.**

`@markommerce/frontend` exports `LitElement` subclasses without `// @ts-expect-error` final patterns. Documented in the guide so module authors keep mixin extension paths open.

**Type extension contract:**

`HookRegistry`, `MarkommerceEventMap`, and per-module data shapes are extended via `declare module '@markommerce/frontend'` declaration merging. The guide documents the pattern.

**PHP-side integration with marko/vite:**

- `markommerce/frontend`'s `module.php` declares `Marko\Vite\Vite` as a singleton (already in `marko/vite`'s own `module.php`, so the binding is reused — we just consume it).
- The Latte `{vite 'entry.ts'}` function calls `$vite->headTags($entry)` and embeds the result.
- `vite.entry` config defaults to `packages/frontend-demo/resources/js/main.ts` in dev; production consumers override.

**Latte extension registration (verified against marko/view-latte source):**

`marko/view-latte` does NOT expose a discovery mechanism for module-provided Latte extensions — `LatteEngineFactory::create()` hard-codes `addExtension(new SlotExtension())` and the class is `readonly class` (so Marko's Plugin attribute can't intercept it via the concrete-subclass strategy and the factory is not bound to an interface). The chosen mechanism is therefore **Marko's `#[Preference]` attribute**: `markommerce/frontend` ships a `MarkommerceLatteEngineFactory extends LatteEngineFactory` annotated `#[Preference(LatteEngineFactory::class)]` whose `create()` calls `parent::create()` and then `$engine->addExtension($this->viteExtension)` for every extension the kernel ships. Module authors who want to add their own Latte extensions follow the same pattern (subclass markommerce's factory). Documented in the "writing a module" guide (task 021).

**Asset consumption (where the built manifest lives):**

`Marko\Vite\Vite` resolves the manifest path as `<ProjectPaths::base>/public/<vite.buildDirectory>/<vite.manifestFilename>`, where `ProjectPaths::base` defaults to `getcwd()`. Since the consuming application (the playground) runs from its own working directory, the Vite build output must end up under the **consuming app's** `public/build/`, not markommerce's. Phase 1 resolves this by configuring Vite to write to `../playground/public/build/` (relative to the markommerce repo root) when `MARKOMMERCE_CONSUMER_PUBLIC` env var is set, falling back to `markommerce/public/build/` for in-repo tests. The README and guide document this explicitly.

**Running the demo end-to-end (Phase 1 scope):**

markommerce itself is a library with no server entry point. To smoke-test the demo route, the consuming app (the playground) must install `markommerce/frontend-demo` as a path dependency and set `frontend_demo.enabled = true`. Task 018 covers the controller/template; an explicit step in the guide documents how to register markommerce as a playground path repository. The plan does **not** add a `bin/marko` / `public/index.php` to markommerce itself.

**Demo route gating and Marko Layout middleware:**

`Marko\Layout\Middleware\LayoutMiddleware` invokes the controller for side effects but discards its return value — when a class has `#[Layout]`, the middleware always renders the layout. This means returning a 404 Response from the controller body to gate the demo does NOT work. The demo therefore implements the gate via a dedicated `EnsureFrontendDemoEnabledMiddleware` registered on the controller via `#[Middleware(...)]`. When the config flag is off, the middleware returns a 404 Response and skips the controller entirely.

## Risks & Mitigations

- **Risk:** The npm workspace + per-package `package.json` pattern interacts awkwardly with Composer path repositories. → **Mitigation:** Each package keeps both manifests; the npm workspace is a separate, parallel concern. Document explicitly in the writing-a-module guide.
- **Risk:** Lit decorators require `useDefineForClassFields: false` + `experimentalDecorators: true`. With future TC39 standard decorators, this is a known migration. → **Mitigation:** Pin to Lit 3.x in Phase 1; revisit in a later phase when Lit 4 drops or TC39 decorators land. Document the pin in the guide.
- **Risk:** Scanner plugin reading filesystem at build time is order-sensitive and racy with HMR. → **Mitigation:** Plugin re-runs on `package.json` change via `handleHotUpdate`; deterministic sort order (priority asc, name asc); the kernel package is hard-coded as `@markommerce/frontend` first.
- **Risk:** `markommerce/frontend-demo` accidentally getting installed in production. → **Mitigation:** The demo Controller registration is gated by a config flag (`frontend_demo.enabled`, default `false`); the demo package is documented as `require-dev` only; the demo route returns 404 when the flag is off.
- **Risk:** Open Props version churn breaking semantic token mappings. → **Mitigation:** Pin Open Props to an exact minor; document the upgrade path in the guide; semantic tokens layer (`tokens.css`) is the only thing that references Open Props directly — components reference semantic tokens.
- **Risk:** Adding a Node service to `compose.yaml` blocks the existing `app` service or changes volume layout. → **Mitigation:** Node service mounts the same workspace volumes read/write but with `working_dir: /workspace/markommerce`; runs `tail -f /dev/null` by default so devs explicitly `docker compose exec node ...`. Vite dev server port 5173 published. Existing services unchanged.
- **Risk:** Lit's `static styles` inheritance across mixins is subtle (the mixin must concatenate `Base.styles`). → **Mitigation:** The mixin authoring guide documents the recommended idiom and the counter's `LabelSuffixMixin` demonstrates it.
- **Risk:** Adding scoping to the cascade layer means consumer-app CSS may need to opt in to the same layer order. → **Mitigation:** `markommerce/frontend` publishes `layers.css` as the single source of truth for the cascade order; the writing-a-module guide instructs consumers to import it first.
- **Risk:** Docker-on-Linux UID mismatch — `node:22-alpine` runs as the `node` user (UID 1000), and `npm install` writes `package-lock.json` to host-mounted volumes. If the developer's host UID differs from 1000, the lockfile gets owned by the wrong user. → **Mitigation:** Task 002 builds a small custom Dockerfile (or uses `user: "${UID:-1000}:${GID:-1000}"` in compose) so the container runs as the host user. Documented in the README.
- **Risk:** `Marko\View\Latte\LatteEngineFactory` is `readonly class` and hard-codes its extensions. → **Mitigation:** ship `MarkommerceLatteEngineFactory extends LatteEngineFactory` with `#[Preference(LatteEngineFactory::class)]`; override `create()` to call `parent::create()` and inject `ViteExtension` (and future extensions) into the returned Engine. Verified that `LatteEngineFactory` is not marked `final`, so subclassing works.
- **Risk:** Vite manifest path mismatch — `Marko\Vite\Vite` reads the manifest relative to the consuming app's `getcwd()`, not the markommerce repo. → **Mitigation:** Task 012 configures Vite's `outDir` to point at the consuming app's `public/build/` via the `MARKOMMERCE_CONSUMER_PUBLIC` env var (default: in-repo `public/build/` for tests). The guide documents the consumer integration step.
- **Risk:** The demo Latte template references the Vite-generated `extensions.ts` file via `main.ts`. `tsc --noEmit` runs before Vite has had a chance to generate the file. → **Mitigation:** Task 016 ships a committed-but-empty placeholder `.generated/extensions.ts` (with a header comment marking it auto-generated) so TypeScript always has the file to import. The Vite plugin overwrites it during build/dev.
