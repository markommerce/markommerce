# Plan: Scope Resolution Pipeline

## Created
2026-05-20

## Status
completed

## Objective
Auto-resolve the current scope per axis from HTTP requests, CLI commands, and queue jobs. Introduce a chain-of-resolvers-per-axis pipeline that writes the resolved scope into `ScopeContext` before user code runs, and clears it afterward. Includes a marko-core change so HTTP middleware can be declared by modules.

## Related Issues
none

## Discovery Notes

Existing scope foundation (do not duplicate):
- `Markommerce\Scope\Context\ScopeContext` — mutable per-request store. Has `in($axis, $path)` (write), `get`, `clear`, `clearAll`, `state()`. Docblock already warns about long-running-worker leakage; this plan addresses that warning.
- `Markommerce\Scope\Registry\ScopeRegistryInterface` — exposes `listAxes()` (registration order), `getAxis()`, `getHierarchy()`.
- `Markommerce\Scope\Axis\ScopeAxis` — `name`, `hierarchy`, `default`.
- `Markommerce\Scope\Hierarchy\ScopeHierarchy` — has `exists($path): bool` for validating resolver output.
- Existing `Markommerce\Scope\Resolver\ScopeResolver` is unrelated to lifecycle resolution — it resolves *property values* across scopes given a `ScopeSignature`. The new classes live alongside it under `src/Resolver/Resolution/` to avoid namespace conflict.
- `packages/scope/module.php` registers singletons; pattern to follow.
- `packages/scope/config/scope.php` is currently:
  ```php
  return ['axes' => [
      'locale'  => ['default' => 'default', 'scopes' => ['default' => []]],
      'market'  => ['default' => 'default', 'scopes' => ['default' => []]],
      'channel' => ['default' => 'web',     'scopes' => ['web' => []]],
  ]];
  ```
  Adding `'resolvers' => [...]` per axis must remain backwards-compatible (missing key = empty chain = always-default).

Marko framework findings:
- `Marko\Routing\Middleware\MiddlewareInterface` exists with `handle(Request $request, callable $next): Response`. Pattern matches `Marko\Session\Middleware\SessionMiddleware` (try/finally for cleanup).
- `Marko\Core\Application::GLOBAL_MIDDLEWARE` is a hardcoded `const array` of three classes gated by `class_exists()`. **No module-driven registration exists today** — this plan adds it.
- `Marko\Routing\Router` is `readonly class` with no `RouterInterface`. `PluginInterceptor` cannot subclass readonly classes (PluginInterceptor.php:89). So plugins cannot target `Router::handle()`.
- `Marko\Core\Command\CommandInterface::execute()` is an interface method — `#[Plugin(target: CommandInterface::class)]` with `#[Before]/#[After]` on `execute` is viable for CLI auto-wiring.
- `Marko\Queue\JobInterface::handle()` is an interface method — same pattern for queue.
- Marko's `#[Plugin]` attribute targets a class/interface (`target: ...::class`); `#[Before(method: '...')]` and `#[After(method: '...')]` decorate per-method hooks. See `marko/packages/debugbar/src/Plugins/DatabaseConnectionPlugin.php` for the canonical example.

Decisions resolved during clarification:
- Mechanism: ordered **chain of resolvers per axis**. First non-null wins; empty/all-null falls through to axis default.
- Cross-axis dependencies: resolve in `listAxes()` order; later resolvers read already-resolved axes via `ScopeResolutionContext::$resolved`. No DAG.
- Input contract: `ScopeResolutionContext` value object wraps a `Marko\Routing\Http\Request` (real or synthetic), the registry, `$resolved` map, and a `channel` string.
- For CLI/queue, a `SyntheticRequest` factory produces an empty-but-valid Request so resolvers never null-check.
- Lifecycle: HTTP via `MiddlewareInterface` (auto-registered via the new marko mechanism). CLI via `#[Plugin]` on `CommandInterface::execute()`. Queue is **manual opt-in** via `JobScopeWrapper::withScope()` — auto-wiring is not possible because `Worker::work()` invokes `JobInterface::handle()` on an `unserialize()`-built object that bypasses the container, so plugin interception never fires.
- Resolver throws unexpectedly: pipeline wraps in `ScopeResolutionException`, logs, continues to next resolver in chain. Never crash the request.
- Resolver returns hierarchy-invalid path: log and skip to next resolver.
- Config validation errors (unknown class, missing class key, not implementing interface): throw `InvalidResolverConfigException` at boot, with `suggestion` pointing at the config path. The scope module's boot closure pre-builds every axis's chain via the factory so misconfigured resolvers fail the app's `initialize()` call rather than producing a runtime error on production traffic.

Marko-core change (separate repo at `/home/michal/www/marko/marko/`, new branch `feature/module-global-middleware` off `develop`):
- Add support for `'globalMiddleware' => [Class::class, …]` in `module.php`. Optional per-entry `priority` (lower runs earlier; default 100).
- Existing hardcoded `Application::GLOBAL_MIDDLEWARE` (`PageCacheMiddleware`, `SessionMiddleware`, `LayoutMiddleware`) retained as built-ins with priorities 10/20/30 for backwards compat. Module-declared entries merge into the same priority-sorted list.
- Backwards-compatible: any current consumer continues to work; new module declarations are purely additive.

## Scope

### In Scope

**Markommerce/scope (this repo, branch `feature/scope-resolution-pipeline`):**
- `ScopeAxisResolverInterface` and `ScopeResolutionContext` (with `SyntheticRequest` helper for non-HTTP channels)
- `ScopeResolverChainFactory` building chains from config (class-string OR `['class' => …, ...params]`)
- `ScopeResolutionPipeline` orchestrator
- Seven built-in resolvers: `CookieResolver`, `HeaderResolver`, `SubdomainResolver`, `PathPrefixResolver`, `QueryParamResolver`, `StaticResolver`, `AcceptLanguageResolver`
- `ScopeResolutionMiddleware` (HTTP) implementing `MiddlewareInterface`
- `ScopeResolutionCommandPlugin` (`#[Plugin]` on `CommandInterface::execute()`) — note: `#[After]` does not run on throw, so `beforeExecute` defensively clears state at the start of each command
- `JobScopeWrapper` (manual opt-in helper for queue jobs — see task 016 for why auto-wiring is not viable)
- `InvalidResolverConfigException`, `ScopeResolutionException`
- `module.php` updates to register pipeline + factory + declare `globalMiddleware`
- `config/scope.php` accepts optional `resolvers` key per axis (backwards-compatible)
- CHANGELOG + README/docs updates

**Marko core (sibling repo, branch `feature/module-global-middleware` off `develop`):**
- Module-php `globalMiddleware` key with optional `priority`
- `Application::discoverGlobalMiddleware()` merges module-declared with built-ins, priority-sorted
- Tests + CHANGELOG

### Out of Scope
- Observer/event hook for third-party resolver injection (deferred until a consumer asks)
- Caching resolved scopes across requests
- UI/admin for editing resolver chains
- Persisting "last resolved scope" to a cookie
- A marko `commandHooks` / `jobHooks` symmetric API — CLI/queue use `#[Plugin]` instead
- Refactoring existing `PageCacheMiddleware`/`SessionMiddleware`/`LayoutMiddleware` to declare themselves via module.php (left as-is for now)

## Success Criteria
- [ ] Installing markommerce/scope into a marko app with the new marko version causes `ScopeContext` to be populated before every HTTP request and CLI command with zero application boilerplate
- [ ] Queue jobs can opt in to scope resolution by wrapping their `handle()` logic with `JobScopeWrapper::withScope(...)`; auto-resolution is documented as a future enhancement gated on a marko/queue upstream change
- [ ] `ScopeContext::clearAll()` runs even when the handler/command/job throws
- [ ] Cross-axis dependencies work: a resolver for axis B can read axis A's resolved value if A is registered before B
- [ ] Resolver exceptions never crash the request — they're caught, logged, and the chain continues
- [ ] All tests passing across both repos (Pest 4 in markommerce, PHPUnit in marko)
- [ ] PHPStan level 8 clean in both repos
- [ ] Coverage ≥ 80% in markommerce/scope
- [ ] Code follows project standards (`declare(strict_types=1)`, no `final`, `readonly` where applicable, explicit constant types, `@throws` on every exception path)
- [ ] Both PRs target `develop` (markommerce → markommerce/develop, marko → marko/develop)

## Task Overview

| Task | Description | Depends On | Repo | Status |
|------|-------------|------------|------|--------|
| 001 | Add module-declared globalMiddleware support to marko | - | marko | completed |
| 002 | Define ScopeAxisResolverInterface | 003 | markommerce | completed |
| 003 | Define ScopeResolutionContext + SyntheticRequest helper | - | markommerce | completed |
| 004 | Define exception classes (InvalidResolverConfigException, ScopeResolutionException) | - | markommerce | completed |
| 005 | Build ScopeResolverChainFactory | 002, 004 | markommerce | completed |
| 006 | Build ScopeResolutionPipeline | 002, 003, 004, 005 | markommerce | completed |
| 007 | Build CookieResolver | 002, 003 | markommerce | completed |
| 008 | Build HeaderResolver | 002, 003 | markommerce | completed |
| 009 | Build SubdomainResolver | 002, 003 | markommerce | completed |
| 010 | Build PathPrefixResolver | 002, 003 | markommerce | completed |
| 011 | Build QueryParamResolver | 002, 003 | markommerce | completed |
| 012 | Build StaticResolver | 002, 003 | markommerce | completed |
| 013 | Build AcceptLanguageResolver | 002, 003 | markommerce | completed |
| 014 | Build ScopeResolutionMiddleware (HTTP) | 006 | markommerce | completed |
| 015 | Build ScopeResolutionCommandPlugin (CLI) | 006 | markommerce | completed |
| 016 | Build JobScopeWrapper helper (Queue — manual opt-in; plugin not viable, see task file) | 006 | markommerce | completed |
| 017 | Wire pipeline into module.php + extend config schema | 001, 005, 006, 014, 015, 016 | markommerce | completed |
| 018 | Update CHANGELOG and docs in both repos | 001–017 | both | completed |

## Architecture Notes

**Namespace convention:** all new code lives under `Markommerce\Scope\Resolver\Resolution\` (mapped to `packages/scope/src/Resolver/Resolution/`). The existing `ScopeResolver` (which resolves *attribute values* across scopes) stays in `Markommerce\Scope\Resolver\`. The two have different jobs — one resolves "what is the current scope?", the other resolves "what is the value of this attribute given the current scope?".

**Pipeline behavior on error:**
- Resolver returns a path that doesn't exist in the axis hierarchy → pipeline logs (via `Marko\Log\Contracts\LoggerInterface` if available; if not, swallow silently to avoid hard-coupling) and proceeds to the next resolver in the chain.
- Resolver throws `Throwable` → pipeline catches, wraps in `ScopeResolutionException` (preserving the cause), logs, proceeds to next resolver. Never re-thrown to the caller.
- All resolvers in chain returned null (or chain is empty) → pipeline writes `$axis->default` to `ScopeContext`.

**Config shape (final):**
```php
'axes' => [
    'locale' => [
        'default'   => 'en',
        'scopes'    => ['en' => [], 'pl' => []],
        'resolvers' => [
            ['class' => CookieResolver::class, 'cookieName' => 'site_locale'],
            AcceptLanguageResolver::class,
            ['class' => StaticResolver::class, 'value' => 'en'],
        ],
    ],
],
```
- Bare class-string → factory instantiates via container (zero-arg or container-resolvable deps)
- Array form → `class` key names the class, remaining keys are passed as named constructor arguments alongside container-resolved deps
- Missing/empty `resolvers` key → empty chain → always falls through to axis default

**Channel semantics:**
- `'http'` — set by `ScopeResolutionMiddleware`
- `'cli'` — set by `ScopeResolutionCommandPlugin`
- `'queue'` — set by `ScopeResolutionJobPlugin`
- HTTP-only built-in resolvers (`CookieResolver`, `HeaderResolver`, `SubdomainResolver`, `PathPrefixResolver`, `QueryParamResolver`, `AcceptLanguageResolver`) early-return `null` when `$context->channel !== 'http'`
- `StaticResolver` is channel-agnostic (its job is to provide a constant fallback that works everywhere)

**SyntheticRequest:** a minimal `Marko\Routing\Http\Request` constructed with empty headers/cookies/query/body for CLI/queue. Built once at plugin time (not lazy) so the type contract of `ScopeResolutionContext` is preserved.

**Marko core change (Task 001) — design:**
- `module.php` accepts `'globalMiddleware' => [...]`. Entries are either class-strings or `['class' => …::class, 'priority' => N]`.
- Default priority `100`. Built-in middleware get explicit priorities preserving current order: `PageCacheMiddleware=10`, `SessionMiddleware=20`, `LayoutMiddleware=30`.
- `Application::discoverGlobalMiddleware()` collects from `GLOBAL_MIDDLEWARE` (with defaults) AND from every module's manifest, sorts by priority ascending, deduplicates by class, returns class-string list.
- Markommerce scope declares its middleware at priority `5` so it runs before PageCache/Session/Layout (so those can read resolved scope, e.g. PageCache can vary by scope).

## Risks & Mitigations

- **Risk**: Plugin scanner doesn't discover `#[Plugin]` classes in module subdirectories.
  - **Mitigation**: Pattern follows `marko/packages/debugbar/src/Plugins/DatabaseConnectionPlugin.php` — known-working location. Plugin classes live under `packages/scope/src/Plugins/`. Confirmed `PluginDiscovery` scans module src/ recursively.

- **Risk**: The new marko PR doesn't merge in time / regresses behavior for apps using the hardcoded list.
  - **Mitigation**: Task 001 keeps `GLOBAL_MIDDLEWARE` constant with its three entries as built-in defaults; module-declared entries merge on top. Existing apps see zero behavior change.

- **Risk**: Resolver constructors can't be resolved by the container (unusual dependency shape).
  - **Mitigation**: `ScopeResolverChainFactory` uses Marko's container to build instances, identically to other services. Array-form config entries pass named args directly. If a resolver has a dep the container can't resolve, the error surfaces at boot with a clear `InvalidResolverConfigException`.

- **Risk**: Plugin on `CommandInterface::execute()` fires for `marko/queue`'s own `work` command, causing scope resolution to run twice (once for the worker command, again per job).
  - **Mitigation**: This is acceptable. The CLI plugin runs once when `bin/marko queue:work` starts; the Job plugin re-resolves per job. Both clear after themselves. The worker process gets a "fresh" context per job, which is the right behavior.

- **Risk**: AcceptLanguage q-value parsing has known edge cases (malformed headers from misbehaving clients).
  - **Mitigation**: Test explicit edge cases (empty header, malformed q-values, unknown languages). Fall back to null (defer to next resolver) on any parse failure rather than throwing.

- **Risk**: SyntheticRequest construction couples markommerce/scope to Marko's Request constructor signature, which may change.
  - **Mitigation**: Document in the SyntheticRequest factory. Test that it produces a Request whose accessor methods all behave sanely (return empty/null rather than throw).

- **Risk**: Logging via `Marko\Log\Contracts\LoggerInterface` creates a hard dependency on marko/log.
  - **Mitigation**: Inject logger as optional (`?LoggerInterface`). Because Marko's container does NOT honor `?Type = null` defaults for non-builtin parameters (see `Container::resolve()`), the pipeline must be bound via a closure in `module.php` that conditionally resolves the logger inside `interface_exists()` + try/catch and passes `null` otherwise. Document this in pipeline README section.
