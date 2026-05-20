# Task 017: Wire Pipeline into module.php and Extend Config Schema

**Status**: pending
**Depends on**: 001, 005, 006, 014, 015, 016
**Retry count**: 0

## Description
Register the new pipeline, factory, and middleware in `packages/scope/module.php`. Declare the middleware via the new marko-supported `'globalMiddleware'` key (task 001) with priority `5` so it runs before existing built-ins. Bind `ScopeResolutionPipeline` via a closure so the optional logger is wired safely. Extend the scope config schema to document the new optional `'resolvers'` key per axis.

## Context
- Target files:
  - `packages/scope/module.php` — add singletons, closure binding for pipeline, globalMiddleware declaration
  - `packages/scope/config/scope.php` — add a header comment showing the optional `resolvers` shape; do not change axis entries (they remain backwards-compatible without resolvers)

### Current module.php structure
```php
return [
    'bindings' => [
        ScopeRegistryInterface::class => function (ContainerInterface $container): PhpScopeRegistry { ... },
    ],
    'singletons' => [
        ScopeContext::class, ScopeMetadataFactory::class, ...
    ],
    'boot' => function (ContainerInterface $container): void { ... },
];
```

### Required additions

**1. `ScopeResolverChainFactory` and `ScopeResolutionMiddleware` as list-style singletons** — both are auto-wireable (only inject `ContainerInterface` and `ConfigRepositoryInterface` / `ScopeResolutionPipeline` respectively, both of which are already resolvable):
```php
'singletons' => [
    // …existing…
    \Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory::class,
    \Markommerce\Scope\Middleware\ScopeResolutionMiddleware::class,
],
```

**2. `ScopeResolutionPipeline` as a closure binding** because of the optional logger. Marko's container does NOT honor `?Type = null` defaults for non-builtin parameters (confirmed in `Container::resolve()` — non-builtin params without bindings recurse into `resolve()` and throw `BindingException::noImplementation`). The closure must conditionally fetch the logger:
```php
'bindings' => [
    // …existing…
    \Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline::class =>
        function (ContainerInterface $container): \Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline {
            $logger = null;
            if (interface_exists(\Marko\Log\Contracts\LoggerInterface::class)) {
                try {
                    $logger = $container->get(\Marko\Log\Contracts\LoggerInterface::class);
                } catch (\Throwable) {
                    $logger = null;
                }
            }
            return new \Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline(
                scopeRegistry: $container->get(\Markommerce\Scope\Registry\ScopeRegistryInterface::class),
                scopeContext: $container->get(\Markommerce\Scope\Context\ScopeContext::class),
                scopeResolverChainFactory: $container->get(\Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory::class),
                logger: $logger,
            );
        },
],
'singletons' => [
    // also mark the pipeline as shared so the closure result is cached
    \Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline::class,
],
```
Note: list-style singletons on a closure-bound class register it as shared, see `BindingRegistry::registerModule` line 41-48.

**3. `globalMiddleware`** — declare ScopeResolutionMiddleware at priority `5`:
```php
'globalMiddleware' => [
    ['class' => \Markommerce\Scope\Middleware\ScopeResolutionMiddleware::class, 'priority' => 5],
],
```

**4. Plugin discovery — no manual registration needed.** `ScopeResolutionCommandPlugin` is auto-discovered by Marko's `PluginDiscovery` scanner.

### Conditional JobScopeWrapper handling
`JobScopeWrapper` (task 016) is now a manual opt-in helper, NOT a plugin. It does not need conditional registration. Users instantiate it through the container when they need it. No special handling in module.php. The class does NOT reference `Marko\Queue\JobInterface` (deliberately decoupled) so it's safe to ship regardless of whether `marko/queue` is installed.

### Boot-time chain validation
The existing `boot` closure in `module.php` already configures `DefaultScopeGuard`. Extend it to also pre-build every axis's resolver chain via the factory, so `InvalidResolverConfigException` surfaces at application startup rather than on the first request that hits a misconfigured axis:
```php
'boot' => function (ContainerInterface $container): void {
    $registry = $container->get(ScopeRegistryInterface::class);
    $factory  = $container->get(\Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory::class);

    $defaults = [];
    foreach ($registry->listAxes() as $axisName) {
        $defaults[$axisName] = $registry->getAxis($axisName)->default;
        $factory->for($axisName); // throws InvalidResolverConfigException on misconfig — fails fast at boot
    }
    DefaultScopeGuard::configure($defaults);
},
```
This guarantees a misconfigured `resolvers` block fails the app's `initialize()` call rather than producing a runtime error on production traffic.

### Config comment for resolvers shape
Add a header comment to `packages/scope/config/scope.php`:
```php
/**
 * Optional 'resolvers' key per axis:
 *
 *   'locale' => [
 *       'default'   => 'en',
 *       'scopes'    => ['en' => [], 'pl' => []],
 *       'resolvers' => [
 *           ['class' => \Markommerce\Scope\Resolver\Resolution\Builtin\CookieResolver::class, 'cookieName' => 'site_locale'],
 *           \Markommerce\Scope\Resolver\Resolution\Builtin\AcceptLanguageResolver::class,
 *           ['class' => \Markommerce\Scope\Resolver\Resolution\Builtin\StaticResolver::class, 'value' => 'en'],
 *       ],
 *   ],
 *
 * Resolvers run in declared order; first non-null hierarchy-valid result wins.
 * Missing or empty 'resolvers' key → axis always resolves to its default.
 */
```

## Requirements (Test Descriptions)

- [ ] `module.php registers ScopeResolverChainFactory as a singleton`
- [ ] `module.php registers ScopeResolutionPipeline via a closure binding that handles a missing logger gracefully`
- [ ] `module.php registers ScopeResolutionPipeline as shared so the same instance is returned`
- [ ] `module.php registers ScopeResolutionMiddleware as a singleton`
- [ ] `module.php declares ScopeResolutionMiddleware as a globalMiddleware entry with priority 5`
- [ ] `the pipeline closure returns a working ScopeResolutionPipeline when marko log is not installed`
- [ ] `existing config without resolvers key continues to load without error`
- [ ] `axis config with resolvers key successfully builds a chain via the factory after boot`
- [ ] `boot closure pre-builds every axis resolver chain so misconfig throws at boot not on first request`
- [ ] `boot closure surfaces InvalidResolverConfigException from a misconfigured axis during Application initialize`

## Acceptance Criteria
- All requirements have passing tests (integration-style tests booting a minimal container with the scope module loaded)
- Existing scope tests still pass — no regression
- `composer test` clean
- PHPStan clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
