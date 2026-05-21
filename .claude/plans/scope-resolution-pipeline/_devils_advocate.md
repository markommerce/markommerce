# Devil's Advocate Review: scope-resolution-pipeline

## Critical (Must fix before building)

### C1. Marko `Request` has no cookie support — tasks 007, 014 fundamentally broken
**Affects:** 003, 007, 014, 017

`Marko\Routing\Http\Request` (`/home/michal/www/marko/marko/packages/routing/src/Http/Request.php`) does not expose any cookie accessor. Its constructor accepts only `$server`, `$query`, `$post`, `$body` — no cookies parameter. `$_COOKIE` is never read. `fromGlobals()` does not pass cookies either. Task 007's `$context->request->cookie($cookieName)` call will be a hard fatal: no such method exists.

A `CookieJarInterface` exists, but in `marko/authentication` (`/home/michal/www/marko/marko/packages/authentication/src/Contracts/CookieJarInterface.php`) — and depending on `marko/authentication` from `markommerce/scope` is wrong (auth depends on session, etc., and we just want cookie reads).

**Fix:** Task 007 must define a thin cookie source abstraction (or read `$_COOKIE` superglobal directly with an injectable seam for tests). The plan should not assume Request gives us cookies. See applied fix to 007.

### C2. Marko `Request` has no host accessor — task 009 cannot work as written
**Affects:** 003, 009

There is no `host()` method on `Marko\Routing\Http\Request`. `HTTP_HOST` is only readable via `$request->header('Host')` (which calls `HTTP_HOST` lookup in `$server`). Task 009 says `$context->request->host()` — that method doesn't exist.

**Fix:** Task 009 must use `$request->header('Host')` and handle a null/empty result. See applied fix to 009.

### C3. Marko `Request::path()` returns full URI including query string — task 010 will misbehave
**Affects:** 010

Looking at `Request::path()` (Request.php line 48-54), it strips `?` but `$_SERVER['REQUEST_URI']` can also contain fragments and `;` parameters. The implementation strips `?…` only. The plan's "splits path on `/`" is fine *after* path() returns, but tests should cover `?` in path. More important: `Request::path()` returns a value already without the query string, so the plan is roughly correct here. No fix needed beyond noting that `path()` is the accessor, not `getPath()` or similar.

**Status:** non-blocking; verified accessor name. Already correct in task 010.

### C4. Plugin `#[After]` does NOT run when the wrapped method throws — task 015 leaks ScopeContext on CLI errors
**Affects:** 015, 006 (clear contract)

`PluginInterception::interceptCall()` at `/home/michal/www/marko/marko/packages/core/src/Plugin/PluginInterception.php` line 77: `$result = $this->pluginTarget->$method(...$arguments);` — no try/finally. Exceptions thrown by the wrapped method propagate before `runAfterPlugins()` is called.

Result: any CLI command that throws leaves `ScopeContext` populated. In a long-running process (queue worker calling commands, REPL, test runner reusing app, etc.) this leaks.

**Fix:** Task 015 must NOT rely on `#[After]` for cleanup. Use only a `#[Before]` hook that wraps a `try/finally` is impossible (Before only fires before the call). Alternatives:
- (a) Replace plugin with a wrapper service / explicit lifecycle integration in `CommandRunner`. Not viable without marko-core change.
- (b) Accept the leak with documented limitation: CLI processes are short-lived; per-process leak is acceptable. The plan already notes (line 35 of 015) the uncertainty.
- (c) Have the `Before` plugin register a `register_shutdown_function` that calls clear(). Brittle.
- (d) Add this to marko-core: extend plugin attributes with `#[Around]` or `#[Finally]`. Out of scope for this plan.

The pragmatic fix: document the limitation clearly, scope CLI cleanup to happen in `Before` of the *next* command as a defensive `clearAll()`, AND have markommerce's own `CommandRunner` integration documented as a future enhancement. We will go with (b) but make it explicit. See applied fix to 015.

### C5. `JobInterface::handle()` plugin interception will NOT fire — task 016 is built on a false premise
**Affects:** 016

`Marko\Queue\Worker::work()` at `/home/michal/www/marko/marko/packages/queue/src/Worker.php` line 28-41:
```php
$job = $this->queue->pop($queue);
…
$job->handle();
```
The job is obtained from `$queue->pop()`, NOT from the container. `Job::unserialize()` (line 31-34 of `Job.php`) is `return unserialize($data);` — a raw `unserialize()`. Plugin proxies are only generated at container resolve time. So `$job->handle()` runs on the raw deserialized object and the `#[Plugin(target: JobInterface::class)]` *never executes*.

**Fix:** Task 016 cannot work via plugin interception. Options:
- (a) Drop the queue plugin and document that queue scope resolution requires a separate marko-core change (Worker should resolve jobs via container or invoke an "around" hook).
- (b) Build the integration as a `Worker` decorator/observer pattern instead — requires upstream marko change.
- (c) Replace with an Observer pattern if marko/queue emits events around job execution. Need to verify.

We will drop the plugin approach in task 016 and rewrite it as: scope resolution for queue jobs is deferred until marko/queue exposes a hook point. Add a note documenting the constraint. Optionally provide a small `JobScopeWrapper` helper class users can opt into manually. See applied fix to 016.

### C6. `ManifestParser` does not currently pass through arbitrary keys — task 001 underspecifies the parser changes
**Affects:** 001

`/home/michal/www/marko/marko/packages/core/src/Module/ManifestParser.php` explicitly extracts `bindings`, `singletons`, `boot`, `enabled`, `sequence` — it does NOT pass arbitrary keys through. To support `globalMiddleware`, the parser must be extended (and `ModuleManifest` must gain a `$globalMiddleware` property). Task 001 mentions "find via grep" but doesn't enumerate the touchpoints. Worker needs explicit guidance: edit both `ModuleManifest` and `ManifestParser`.

**Fix:** Expand task 001's context with explicit file list and signatures. See applied fix to 001.

### C7. `ConfigRepositoryInterface::get()` throws when key missing — task 005's `$configRepository->get('…', [])` won't compile
**Affects:** 005, 017

`Marko\Config\ConfigRepositoryInterface::get(string $key, ?string $scope = null): mixed` — second arg is `$scope`, not `$default`. The method throws `ConfigNotFoundException` when the key is missing. Task 005 says `$configRepository->get('scope.axes.{axisName}.resolvers', [])` — this passes `[]` (an array) where a `?string $scope` is expected. Type error.

**Fix:** Use `$configRepository->has(...)` first, or wrap in try/catch and treat `ConfigNotFoundException` as empty chain. See applied fix to 005.

### C8. `Marko\Log\LoggerInterface` FQCN is wrong — actual class is `Marko\Log\Contracts\LoggerInterface`
**Affects:** 006, _plan.md

Plan and task 006 both reference `Marko\Log\LoggerInterface`. The actual interface lives at `/home/michal/www/marko/marko/packages/log/src/Contracts/LoggerInterface.php` — namespace `Marko\Log\Contracts`. A `use Marko\Log\LoggerInterface;` will fail.

**Fix:** Update 006 and _plan.md. See applied fix.

## Important (Should fix before building)

### I1. Tasks 002 and 003 in parallel: 002 type-hints `ScopeResolutionContext` which doesn't exist yet — interface file won't autoload
**Affects:** 002, 003

Task 002 declares an interface that references `ScopeResolutionContext` in its method signature. PHP's autoloader handles `use` statements lazily — the interface file *can* reference a class that doesn't exist yet *as long as that class isn't instantiated*. But PHPStan level 8 (required) will fail on the unknown class. Tests using `Reflection` on the interface won't blow up, but static analysis will.

Static analysis is required per Success Criteria. We need to ensure 003 lands before any PHPStan run that includes 002. Simplest fix: make 002 depend on 003 (sequential), since they're tiny.

**Fix:** Add `Depends on: 003` to 002 (or vice versa — either works; making 002 wait is cheaper because 003 has no external deps). See applied fix.

### I2. SyntheticRequest construction couples to a Request constructor that has no cookie/host fields — synthetic minimal request can't expose them anyway
**Affects:** 003, 015, 016

Since `Request::__construct(server, query, post, body)` doesn't take cookies, `SyntheticRequest` can't pre-populate cookies. That's OK — CLI/queue resolvers shouldn't read cookies anyway. But task 003 says "empty headers, no cookies, no query, no body" — workers may write code that calls a non-existent `cookies()` method on the Request, expecting it to return `[]`. Plan must align with actual Request API.

**Fix:** Update task 003 to list the actual constructor params (server, query, post, body) and not promise cookie support. See applied fix.

### I3. Resolver `ScopeResolutionContext` is created per-axis but resolved map shared — confirm signature
**Affects:** 003, 006

Task 003 says `ScopeResolutionContext` is `readonly class` with a `$resolved` map. Task 006 creates one fresh per axis. That's consistent IF the field is the *current* resolved map (axes resolved up to but not including the current axis). This is fine but explicit in 006, somewhat implicit in 003. Make 003 explicit.

**Fix:** Add explicit doc on `$resolved`: "axes resolved earlier in the same pipeline run; excludes the axis currently being resolved." See applied fix.

### I4. `globalMiddleware` priority ordering vs deduplication is ambiguous
**Affects:** 001

Task 001 says "deduplicates by class name keeping lowest priority". But what if a module sets priority 5 for `ScopeResolutionMiddleware` and an app also declares the same class at priority 20? Keep priority 5 (more aggressive) or priority 20 (app override)? "Lowest priority" means "earliest-running", which usually means app overrides should win. This needs to be defined.

**Fix:** Specify: "When the same class appears multiple times, keep the entry from the highest-priority source (app > modules > vendor); within the same source, keep the lowest priority value (runs earliest)." See applied fix to 001.

### I5. `clear()` in task 006 might clobber axes set by user code in middleware downstream
**Affects:** 006, 014

`ScopeContext::clearAll()` wipes ALL axes. If a downstream middleware calls `$scopeContext->in('preview', 'preview-token-123')`, that's wiped too in the finally. That's actually correct (HTTP request boundary == clear), but worth documenting.

**Fix:** Add a note in task 006 acknowledging full-clear is intentional. See applied fix.

### I6. `AcceptLanguageResolver` may need the registry/axis to validate matches — interface allows it but task 013 doesn't mention how it gets one
**Affects:** 013

Task 013 says "for each candidate, check if `$scopeAxis->hierarchy->exists($candidate)`" — but how does the resolver get `$scopeAxis`? Looking at task 002's interface signature: `resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string`. So `$scopeAxis` is the first parameter. Good — already in the interface. But task 013 says constructor is "zero-arg (no params)" — confirm.

**Fix:** Update task 013 to explicitly call out: uses `$scopeAxis->hierarchy` to validate candidates. See applied fix.

### I7. Task 017 — singletons listed alongside list-style entries; mixing causes errors
**Affects:** 017

The existing `BindingRegistry::registerModule` (line 41-45) treats list-style singleton entries (integer keys) as "mark for singleton, no binding". This works only if the container can autowire the class. `ScopeResolverChainFactory` needs `ConfigRepositoryInterface` and `ContainerInterface` — both auto-resolvable. `ScopeResolutionPipeline` needs `ScopeRegistryInterface` (bound), `ScopeContext` (singleton), `ScopeResolverChainFactory` (singleton), `?LoggerInterface $logger = null`. The optional null default will resolve to null per `BindingException::unresolvableParameter` — actually NO, looking at Container.php line 181-188: builtin types use default, non-builtin without binding will throw. `LoggerInterface` is non-builtin; the container may not have `Marko\Log\Contracts\LoggerInterface` bound if marko/log isn't installed.

Check Container.php line 178-204: a non-builtin parameter without a binding will recurse into `resolve()` which will eventually throw `BindingException::noImplementation()` for an interface. The `?LoggerInterface = null` default is NOT honored by the autowirer for non-builtin types.

**Fix:** Task 017 must use a closure binding for `ScopeResolutionPipeline` that conditionally injects the logger only if available:
```php
ScopeResolutionPipeline::class => function (ContainerInterface $c): ScopeResolutionPipeline {
    $logger = null;
    if (interface_exists(\Marko\Log\Contracts\LoggerInterface::class)) {
        try { $logger = $c->get(\Marko\Log\Contracts\LoggerInterface::class); }
        catch (\Throwable) { $logger = null; }
    }
    return new ScopeResolutionPipeline(…, logger: $logger);
},
```
This is a real wiring issue. See applied fix to 017.

### I8. `ScopeResolutionMiddleware` needs to be retrievable through container, but the pipeline depends on logger which may not be wireable
**Affects:** 014, 017

Same root cause as I7 — Middleware is auto-wired by marko's routing bootstrapper. If `ScopeResolutionPipeline` can't be constructed because of unresolvable logger, middleware construction fails. The closure binding in I7's fix resolves this.

### I9. `Application::discoverGlobalMiddleware()` builds class strings — bootstrapper instantiates them. Confirm the class is autoloadable & implements MiddlewareInterface
**Affects:** 001

Task 001's requirement "throws clear exception with suggestion when a declared class does not implement MiddlewareInterface" is good. Add: also throw if the class doesn't exist (current code silently skips via `class_exists()` — that's fine for hardcoded built-ins but module-declared misspellings should be loud).

**Fix:** Be explicit in task 001 that module-declared globalMiddleware must throw `ModuleException` (or new specific exception) on class-not-found, NOT silently skip. The hardcoded `GLOBAL_MIDDLEWARE` keeps `class_exists()` for backwards-compat (so apps without page-cache/session/layout still boot). Module-declared entries are loud. See applied fix.

### I10. Task 017 — queue plugin guard via `interface_exists()` happens at file load, not runtime
**Affects:** 017

Task 017 says "conditionally include the job plugin only when marko queue interface exists". But the plugin discovery (`PluginDiscovery::discoverInModule` line 38-65 of PluginDiscovery.php) scans every file in `packages/scope/src/`. If `ScopeResolutionJobPlugin.php` exists, the scanner will try to load it. The class declaration references `\Marko\Queue\JobInterface::class` via the `#[Plugin(target: …)]` attribute — `Attribute::newInstance()` will fail if the interface doesn't exist (no, actually class-string is just a string until used; but PHP will try to resolve when the attribute is instantiated).

Actually, looking more carefully: `Plugin::class`'s `target` is a string property; `JobInterface::class` is a compile-time string constant that doesn't require the interface to exist at parse time. So the attribute *itself* parses fine. But later, `PluginRegistry::register` calls `class_implements($class)` and similar — that might silently return false on missing interfaces.

The safer pattern: put the plugin in a subdirectory and skip discovery via not-existing-file. But discovery scans recursively. We can't easily prevent discovery.

The original suggestion in task 016 — "use interface_exists() guard in module.php to skip plugin registration" — doesn't work because plugin registration is driven by class attribute scanning, not by module.php. The plan is broken.

**Fix:** Since task 016 is being neutered anyway (C5), this concern resolves itself. But if the plugin file exists at all, discovery will scan it. If we keep ANY plugin code, it must guard internally: `if (!interface_exists(\Marko\Queue\JobInterface::class)) return;` in its constructor or methods. Better: just don't ship the file unless `marko/queue` is required. We'll require `marko/queue` as a suggest and conditionally include the file via composer's `extra.classmap` — no, too complex. Simplest: have the plugin class exist with a soft guard, and document it as a no-op if queue is unavailable. See applied fix.

### I11. Task 015 says "Class is NOT `readonly`" — but plugins are stateless wrappers; they could be readonly
**Affects:** 015, 016

DatabaseConnectionPlugin has mutable `$started` state. Scope plugins do NOT need mutable state — they just call `$pipeline->run/clear`. They CAN be `readonly class`. Task 015 says "might need to be regular class for plugin instantiation — verify against debugbar example". Verified: regular class works, but readonly works too as long as no mutable fields. Make consistent.

**Fix:** Document that `readonly class` is fine (better, per project standards). See applied fix.

### I12. PHPStan: pipeline catching `Throwable` then logging needs `@throws never` or equivalent on `run()`
**Affects:** 006

Task 006 says: "verify there is no escape path; the docblock should say 'never throws'". PHPStan level 8 with `@throws` rule is loose, but the type system needs assurance. Make it explicit.

**Fix:** Add explicit guidance: `/** @return void; does not throw — all resolver exceptions are caught and logged. */`. See applied fix.

### I13. Resolver order vs `listAxes` order — config-time validation needed
**Affects:** 005, 017

Plan relies on `listAxes()` ordering. Existing `PhpScopeRegistry` returns axes in config-file declaration order. If a downstream consumer overrides the registry with a `Preference` that re-orders axes (alphabetical, etc.), cross-axis resolvers will silently misbehave. Not blocking but worth noting.

**Fix:** Add a small explicit test in 006 asserting axes resolve in `listAxes()` order regardless of alphabetical/etc. Already in 006's requirements (line 27). Good. No change needed.

### I14. The marko-core change has no upgrade path for existing apps that already use closures or non-standard middleware orderings
**Affects:** 001

If an app today swaps in a custom Application subclass that overrides `discoverGlobalMiddleware()`, the new mechanism may quietly do nothing for them. Note this in CHANGELOG.

**Fix:** Add a note in task 018 CHANGELOG entry — "Apps overriding `Application::discoverGlobalMiddleware()` will not pick up module-declared middleware until they update their override." See applied fix to 018.

## Minor (Nice to address — not auto-applied)

### M1. Test naming has spaces — confirm Pest convention vs PHPUnit
Task descriptions like `it accepts globalMiddleware as a flat list of class strings in module.php` are Pest-style. Marko core uses PHPUnit. The PHPUnit equivalent would be `testItAcceptsGlobalMiddlewareAsAFlatListOfClassStringsInModulePhp` or `#[TestDox]` style. Task 001 worker should translate as needed; non-blocking.

### M2. `SubdomainResolver` segment indexing has a magic-default of 0 — explicit defaults are clearer
Task 009: `__construct(private int $segment = 0)`. Consider requiring the segment param explicitly; defaults can mask config bugs.

### M3. AcceptLanguage parsing — should it return the *original* tag or the matched-hierarchy tag?
Header has `en-US`, hierarchy has `en`. Task 013 says "falls back to language code without region" — returning `en`. What if hierarchy has `en-US` AND `en`? Currently the plan returns the regional variant first. Fine, but tests should cover the case where hierarchy has multiple matches.

### M4. `StaticResolver` constructor takes a string value but no validation of "is this a valid scope path for this axis"
Task 012: This is intentional — pipeline validates via `hierarchy->exists()`. Note that `StaticResolver` configured with an invalid value will silently be skipped, falling through to axis default. That's fine but could be a debug headache.

### M5. Tests in task 014 (middleware) should also cover "next throws then finally clears" with a real Throwable subclass — not just `Exception`
Edge: `Error`, `TypeError`. The `try/finally` catches both, but tests should include at least one non-`Exception` throwable.

### M6. The `'globalMiddleware'` config key — kebab-case vs camelCase
Existing module.php keys: `bindings`, `singletons`, `boot`, `sequence`. All single-word lowercase. `globalMiddleware` is camelCase. Consider `'global-middleware'` or `'middleware'`. Naming is a bikeshed but worth a moment of consideration.

### M7. `ScopeResolutionContext`'s `$channel` field could be an enum
A `ChannelKind` enum would be more typesafe than string constants. Marko's `LogLevel` is already an enum (`Marko\Log\LogLevel`). Not blocking — string constants work fine for now.

## Questions for the Team

### Q1. What is the desired behavior for a malformed resolver config — boot-time hard fail, or skip + log?
Currently the plan says "throw `InvalidResolverConfigException` at boot/first-use". But `ScopeResolverChainFactory` is constructed lazily on first axis resolve. A typo in config will surface on the first HTTP request, not at deploy time. Should config be validated at boot in `module.php`'s `boot` callback?

### Q2. Queue scope resolution — accept-and-defer, or block the feature on a marko/queue upstream change?
Given C5 makes queue auto-resolution impossible without an upstream change, do we (a) ship without queue support, (b) block the feature, or (c) ship a manual `JobScopeWrapper` users opt into?

### Q3. Cookie reading — minimal abstraction (read `$_COOKIE` superglobal), or wait for marko/routing to add cookie support upstream?
Reading `$_COOKIE` works but is untestable. Adding cookie support to marko/routing would be cleaner but blocks this plan. Recommend: introduce a small `RequestCookies` reader class in markommerce/scope that reads `$_COOKIE` by default but accepts an array in tests.

### Q4. Should the new marko-core PR also add an `#[Around]` plugin attribute for try/finally-style cleanup?
This would solve C4 (CLI clear-on-throw) and C5 (queue clear-on-throw if the worker resolved jobs via container). It's a bigger surface but the only path to robust auto-cleanup.

### Q5. Should `ScopeResolutionMiddleware` be configurable as opt-in?
Currently auto-registered globally. Apps that don't want auto-resolution have to remove the entire scope module from globalMiddleware — they can't disable just the resolution while keeping ScopeContext. Consider a config flag like `'scope.resolution.enabled' => true` (default true) that the middleware checks.
