# Task 010: Build PathPrefixResolver

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that extracts a specific path segment from the request URL and returns it. Useful for `/eu/products` → market `eu`. Channel-restricted to HTTP.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/PathPrefixResolver.php`
- Constructor: `public function __construct(private int $segment = 0)` — index into path parts after splitting on `/` and filtering empty entries.
- Returns `null` when `$context->channel !== ScopeResolutionContext::CHANNEL_HTTP`
- Reads path via `$context->request->path()` (verify accessor in `marko/packages/routing/src/Http/Request.php`)
- Splits path on `/`, filters empty strings (leading slash, trailing slash, double slashes), returns the segment at the configured index

## Requirements (Test Descriptions)

- [x] `it returns the first non-empty path segment when segment is zero`
- [x] `it returns the second non-empty path segment when segment is one`
- [x] `it returns null when path is /`
- [x] `it returns null when path has fewer segments than the configured index`
- [x] `it ignores leading and trailing slashes`
- [x] `it returns null when channel is cli`
- [x] `it returns null when channel is queue`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `ScopeAxisResolverInterface`

## Implementation Notes
- Implemented as a `readonly class` implementing `ScopeAxisResolverInterface`
- Constructor takes `int $segment = 0` defaulting to the first segment
- Channel guard returns `null` immediately for non-HTTP channels (CLI, queue)
- Path is read via `$context->request->path()` which strips query string from `REQUEST_URI`
- Path is split on `/` and empty strings filtered using `array_filter` + `array_values` to re-index
- Returns `$parts[$this->segment] ?? null` — naturally handles root `/`, short paths, and trailing slashes
