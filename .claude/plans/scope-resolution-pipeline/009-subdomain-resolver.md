# Task 009: Build SubdomainResolver

**Status**: complete
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that extracts a specific subdomain segment from the request host and returns it. Useful for `eu.example.com` → market `eu`. Channel-restricted to HTTP.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/SubdomainResolver.php`
- Constructor: `public function __construct(private int $segment = 0)` — index into host parts after splitting on `.`. Segment 0 = first label.
- Returns `null` when `$context->channel !== ScopeResolutionContext::CHANNEL_HTTP`
- **No `host()` accessor exists on Marko's Request** — read the host via `$context->request->header('Host')` (verified at `/home/michal/www/marko/marko/packages/routing/src/Http/Request.php`; `Request::header()` looks up `HTTP_HOST` in the server array). Result may be `null` or contain a port (`example.com:8080`); strip any `:port` suffix before splitting on `.`.
- Splits host on `.`, returns the segment at the configured index, or null if the host has too few segments
- Returns null when the host header is null, empty, or numeric (e.g. raw IP `192.0.2.1` should not be split into "1"/"0.2.1"/etc — detect this by checking that the host contains at least one alphabetic character or is not entirely IPv4-shaped). Simplest guard: if `filter_var($host, FILTER_VALIDATE_IP)` returns non-false, return null.

## Requirements (Test Descriptions)

- [x] `it returns the first subdomain label when segment is zero`
- [x] `it returns the second subdomain label when segment is one`
- [x] `it returns null when host has fewer segments than the configured index`
- [x] `it returns null when host is empty`
- [x] `it returns null when host header is missing entirely`
- [x] `it strips port suffix from the host before splitting`
- [x] `it returns null when host is a raw IPv4 address`
- [x] `it returns null when channel is cli`
- [x] `it returns null when channel is queue`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `ScopeAxisResolverInterface`

## Implementation Notes
- Implemented as `readonly class SubdomainResolver implements ScopeAxisResolverInterface`
- Reads host via `$context->request->header('Host')` (maps to `HTTP_HOST` in server array)
- Strips `:port` suffix with `strpos`/`substr` before splitting
- Guards against raw IPv4 addresses using `filter_var($host, FILTER_VALIDATE_IP)`
- Returns segment via `$parts[$this->segment] ?? null` (null-coalesce handles out-of-bounds)
