# Task 011: Build QueryParamResolver

**Status**: complete
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that reads a named query-string parameter and returns its value. Useful for `?_scope_locale=pl`. Channel-restricted to HTTP.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/QueryParamResolver.php`
- Constructor: `public function __construct(private string $paramName)`
- Returns `null` when `$context->channel !== ScopeResolutionContext::CHANNEL_HTTP`
- Reads param via `$context->request->query($paramName)` — verified accessor at `/home/michal/www/marko/marko/packages/routing/src/Http/Request.php` line 59-68; returns `mixed`. Cast to string (or check `is_string`) before returning. Arrays (e.g. `?_scope[]=foo`) are not valid scope paths — return null.
- Treat empty-string values as null (not a valid scope path); only return the value when it's a non-empty string

## Requirements (Test Descriptions)

- [x] `it returns the param value when the query string contains the configured key`
- [x] `it returns null when the configured key is missing from the query string`
- [x] `it returns null when the configured key has an empty string value`
- [x] `it returns null when the configured key has an array value (e.g. _scope[]=foo)`
- [x] `it returns null when channel is cli`
- [x] `it returns null when channel is queue`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `ScopeAxisResolverInterface`

## Implementation Notes
- `readonly class QueryParamResolver implements ScopeAxisResolverInterface` at `packages/scope/src/Resolver/Resolution/Builtin/QueryParamResolver.php`
- Checks `$scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP` first and returns null for non-HTTP channels
- Uses `is_string()` check to handle array values (e.g. `?_scope[]=foo`) before checking for empty string
- Returns null for empty string values; only returns the value when it's a non-empty string
