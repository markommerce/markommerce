# Task 011: `ConfigCacheInterface` + `RequestConfigCache` (value-level)

**Status**: completed
**Depends on**: 010
**Retry count**: 0

## Description
Add per-request memoization of *resolved values* (not raw rows). Define `ConfigCacheInterface` (so a future Redis/APCu impl can drop in) and implement `RequestConfigCache` (in-process associative-array cache, cleared per-request via `ConfigCacheResetMiddleware`). Cache key is the tuple `(config-key, projected-scope-signature)` where the projection includes only the axes declared on the config's `#[Scoped]` attribute — so two configs sharing a key but reading under different scope-context projections cache independently.

## Context
- **Granularity: VALUE-level**, not row-level. The cache stores the final, post-cast, post-decrypt resolved value — the value the caller would have observed. Reads bypass storage, matcher, caster, and cipher entirely on a hit.
- **Cache key shape**: `<config-key>|<axis1>:<val1>|<axis2>:<val2>` — exactly the `ScopeSignature::toString()` format projected over the config's declared axes (sorted). Unscoped configs cache under just the config key (no `|` separator). When an axis is declared on the property but unset in the current `ScopeContext`, the projection omits it (so partial-scope reads still cache deterministically).
- **Where it lives**: the cache wraps `ConfigResolver`, not `ConfigStorageInterface`. Implement as `CachingConfigResolver` decorator that holds a `ConfigResolver` + a `ConfigCacheInterface`. The decorator implements the same public surface (`resolved`, `resolvedAt`, `get`). On cache miss it delegates and memoizes the cast value.
- **Invalidation**: when a write happens via `ConfigWriter`, invalidate every cache entry whose key starts with `<config-key>|` (prefix match) plus the unscoped key itself. Implementation can scan keys (per-request, small N) — no need for fancy index structures.
- **Reset between requests**: Marko has no request-scoped DI. `RequestConfigCache` is bound as a singleton; `ConfigCacheResetMiddleware` calls `$cache->clear()` at request start. Wiring lives in task 020.
- **Null values are cacheable**: a resolved `null` (from a nullable config with no row + null default) must be cached so we don't re-resolve. Use a sentinel internally OR distinguish "key present in array vs not" via `array_key_exists`. Prefer `array_key_exists` — simpler.
- **Interface**:
  - `get(string $cacheKey, Closure(): mixed $loader): mixed` — returns the cached value if present (including cached nulls), else invokes loader and memoizes
  - `invalidatePrefix(string $configKey): void` — drops every entry whose stored key starts with `<configKey>|` or equals `<configKey>` exactly
  - `clear(): void` — drops everything (called by middleware)
- This task also delivers `ConfigCacheResetMiddleware`: implements Marko's middleware interface, calls `$cache->clear()` before forwarding. Registration in `globalMiddleware` happens in task 020.
- This task also delivers `CachingConfigResolver`: same constructor signature as `ConfigResolver` minus one dep (it takes a `ConfigResolver` instance + `ConfigCacheInterface`). NOT registered as the default `ConfigResolver` binding in this task — that wiring decision lives in task 020.

## Requirements (Test Descriptions)
- [x] `it caches the resolved value on first call and returns the cached value on second call for the same config-key and scope projection`
- [x] `it caches resolved null values and does not re-invoke the loader on subsequent reads of the same key`
- [x] `it caches independently for the same config under different ScopeContext projections`
- [x] `it projects the cache key over only the config's declared axes ignoring unrelated active axes`
- [x] `it invalidates all cache entries for a given config key when invalidatePrefix is called`
- [x] `it does not invalidate entries for unrelated config keys when invalidatePrefix is called`
- [x] `it clears every entry when clear() is called`
- [x] `CachingConfigResolver delegates to the wrapped ConfigResolver on cache miss and stores the result`
- [x] `CachingConfigResolver returns the cached value without invoking the wrapped resolver on cache hit`
- [x] `ConfigCacheResetMiddleware calls cache.clear() before forwarding to the next handler`

## Acceptance Criteria
- `ConfigCacheInterface::get(string $cacheKey, Closure $loader): mixed` (returns mixed because resolved values are typed by config; loader returns whatever the resolver returns)
- `ConfigCacheInterface::invalidatePrefix(string $configKey): void` + `ConfigCacheInterface::clear(): void`
- `RequestConfigCache` implements the interface fully; non-`final`; uses `array_key_exists` to distinguish cached-null from miss
- `CachingConfigResolver` wraps a `ConfigResolver` + `ConfigCacheInterface` and implements the same public methods (`resolved`, `resolvedAt`, `get`)
- `ConfigCacheResetMiddleware` exists in `src/Middleware/` and implements Marko's middleware interface
- All cache-key construction goes through a single private helper to avoid drift between `resolved` and `resolvedAt` paths
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
