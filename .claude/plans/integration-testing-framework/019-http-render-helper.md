# Task 019: `BootedStore::handle()` request→HTML helper

**Status**: completed
**Depends on**: 018, 009, 010
**Retry count**: 0

## Description
Add the full-stack HTTP helper: `BootedStore::handle(Request): Response` dispatches a path through the REAL router + global middleware pipeline + layout renderer (real Latte view) and returns rendered HTML — so an integration test can "request a path, get HTML, assert on it". Builds on the `storefront` profile + injectable `ProjectPaths` from task 018. Generalizes the ~600-line hand-built wiring duplicated in the storefront feature tests into one reusable capability.

## Context
- **Blueprint, with a CRITICAL caveat**: `packages/catalog-storefront/tests/Feature/Tier1EndToEndTest.php` hand-builds the wiring — read it for shape — BUT it (and `CategoryLayoutTest`/`CategorySeoTest`/`CategorySortRedirectTest`) bind a FAKE `ViewInterface` (`Tier1FakeView` etc.) emitting `<div data-template="…">` placeholders, specifically to AVOID the real Latte engine's `{vite()}` call. So the existing tests do NOT render real HTML and do NOT solve Vite — they sidestep it. `handle()` MUST use the REAL `ViewInterface` from the booted container and solve Vite for real (below). Do not "replicate how the fake tests render".
- **Use marko's own bootstrappers (don't hand-roll per-controller wiring):**
  - `Marko\Routing\RoutingBootstrapper($manifests, $container, $preferenceRegistry, new ClassFileParser())` → `->boot(array $globalMiddleware): Router`. Discovers controllers from every module's `src/`, resolves Preference-replaced controllers, registers `RouteCollection` + `RouteMatcherInterface` + `Router` as container instances, returns the `Router`. Resolve `PreferenceRegistry` from the booted container (it's bound as an instance).
  - `Marko\Core\Module\GlobalMiddlewareResolver::resolve(array $manifests): array` → ordered global-middleware class list. For the storefront profile this yields `[CompileIfStaleMiddleware::class, MarkommerceLayoutMiddleware::class]` (declared in `packages/layout/module.php`). `ManifestParser` captures `globalMiddleware` and the harness `ModuleResolver` builds manifests via it, so the booted manifests carry it. Pass this into `RoutingBootstrapper::boot()`. Do NOT hardcode the middleware list. (`catalog-storefront`/`frontend` have no `module.php`; the real Latte `ViewInterface` + `ViteExtension` come from `marko/view-latte`'s binding + `markommerce/frontend`'s `MarkommerceLatteEngineFactory` `#[Preference]`, already activated by the bootstrapper's preference discovery.)
- **`BootedStore::handle(Request $request): Response`** (core deliverable):
  - Routing via `RoutingBootstrapper`; controllers resolve from the booted container.
  - Global middleware from `GlobalMiddlewareResolver`; the storefront renders HTML in `MarkommerceLayoutMiddleware`, NOT the controller (which returns an empty body + `Link` header) — so `handle()` MUST run the full pipeline, not just call the controller. Both `show` (`layout/category_show.php`) and `pageFragment` (`layout/category_page_fragment.php`) have layouts and render.
  - **Short-circuits**: 302 sort-redirect, 410 page-depth, 404 missing-category are controller-produced and passed through by the layout middleware unchanged — `handle()` must return them with status + `Location`/headers intact.
  - **Layout compilation**: include `CompileIfStaleMiddleware` so layouts compile on demand into the task-018 per-worker `ProjectPaths` base (`var/cache/markommerce/layouts.php`). `CompileIfStaleMiddleware` only compiles when `APP_ENV !== 'production'` — set `APP_ENV` to a non-prod value (or document) so on-demand compilation runs. Use task 018's injectable `ProjectPaths` base (unique per worker) — do NOT reintroduce a hardcoded path.
  - Dispatch through the pipeline → return the `Response` (status, headers, rendered HTML body).
- **Vite — DECIDED: Approach A (dev-server tags, no filesystem writes).** Set `vite.useDevServer => true` in the storefront profile config so `Vite::headTags()` takes the `devServerTags()` branch and emits `<script type="module" src="…">` dev-server tags with NO manifest lookup — never throws `ViteManifestException`. Ensure `vite.entry` + `vite.devServerUrl` are non-empty (frontend's `config/vite.php` provides defaults via `ConfigDiscovery`; verify they survive the merge, else inject them in the storefront profile config). Asset markup becomes dev-server `<script>` tags — fine, since 020/021 assert on real component markup (`<mk-grid>`, product names/prices), NEVER on asset tags. (Fallback only if A proves insufficient: a per-worker stub manifest under the task-018 `ProjectPaths` base — must NOT touch the repo's real `public/`. Do not implement unless A fails.)
- Live in `packages/testing/src/Http/` (a helper class for the routing/middleware/vite wiring — it IS sizable; keep `BootedStore` thin) + the `BootedStore::handle()` method delegating to it. Keep `handle()` usable from any profile that includes routing + layout.
- Add the canonical render test here (a real category page) so the helper is proven before the migrations (020/021) depend on it.

## Requirements (Test Descriptions)
- [x] `it sources global middleware from the booted manifests via GlobalMiddlewareResolver (CompileIfStale then MarkommerceLayout)`
- [x] `it builds a router that matches the category page route via RoutingBootstrapper`
- [x] `it renders real Latte HTML (not fake-view placeholder markup) without throwing ViteManifestException` (group integration-destructive)
- [x] `it returns a 200 response whose HTML body contains the seeded product markup` (group integration-destructive)
- [x] `it exposes the SEO canonical Link header on the response` (group integration-destructive)
- [x] `it passes through controller short-circuit responses (302/410/404) with status and Location/headers intact` (group integration-destructive)
- [x] `it emits vite dev-server script tags rather than failing on a missing manifest` (Approach A; group integration-destructive)
- [x] `it compiles layouts under the per-worker ProjectPaths base without cross-test collision` (group integration-destructive)

## Acceptance Criteria
- `BootedStore::handle(Request): Response` dispatches through `RoutingBootstrapper`-built routing + `GlobalMiddlewareResolver`-sourced middleware + the REAL Latte view, returning rendered HTML backed by the real DB; short-circuits pass through.
- Vite Approach A wired (dev-server tags, no FS writes, parallel-safe), documented in code.
- PHPStan level 8 clean (`php -d memory_limit=2G`).

## Implementation Notes

### Key files
- `packages/testing/src/Http/RequestDispatcher.php` — builds the `Router` via `RoutingBootstrapper` + `GlobalMiddlewareResolver`; sets `APP_ENV=dev` if not already a dev/local value so `CompileIfStaleMiddleware` triggers; caches the router instance.
- `packages/testing/src/Profile/BootedStore.php` — added `handle(Request): Response` delegating to `RequestDispatcher`, `manifests(): array`, and `private array $manifests = []` constructor parameter.
- `packages/testing/src/Profile/StoreProfile.php` — `storefront()` preset wires `configOverrides` with `vite.useDevServer=true`, `vite.devServerUrl`, and `vite.entry`; passes `manifests:` to `BootedStore` constructor in `boot()`.
- `packages/config-pgsql/src/Entity/ConfigValueRecord.php` — fixed `$updatedAt` PHP default from `''` to `null` so `EntityMetadataFactory` does not emit `DEFAULT ''` for a `timestamptz` column (PostgreSQL rejects it).
- `packages/testing/tests/Feature/Http/RequestDispatcherTest.php` — 8 test requirements; parallel-safe `beforeEach` pins `MARKOMMERCE_CONFIG_SECRET_KEY`; `@var ConnectionInterface&TransactionInterface` casts before `TestIsolation::begin()`.

### Decisions
- **Vite Approach A**: `vite.useDevServer=true` in the storefront profile config overrides so `Vite::headTags()` emits dev-server `<script type="module">` tags; no manifest file needed, parallel-safe.
- `marko/vite` config (loaded after `markommerce/frontend`) overwrites `devServerUrl` with an empty env-var-based default. Fixed by explicit overrides in `storefront()` preset rather than relying on load order.
- `ConfigValueRecord::$updatedAt = null` (not `= ''`) — `EntityMetadataFactory` picks up PHP property defaults as DB column defaults; empty string caused `DEFAULT ''` which PostgreSQL rejects for `timestamptz`.
- `MARKOMMERCE_CONFIG_SECRET_KEY` parallel safety: `StoreProfileTest` unsets the key in its `finally` block; a `beforeEach` hook pins a stable test key before each test in the `RequestDispatcherTest` file.
