# Task 008: Build HeaderResolver

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that reads a named HTTP header and returns its value (or null if missing). Channel-restricted to HTTP.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/HeaderResolver.php`
- Constructor: `public function __construct(private string $headerName)`
- Returns `null` when `$context->channel !== ScopeResolutionContext::CHANNEL_HTTP`
- Reads header via `$context->request->header($headerName)` — verified accessor at `/home/michal/www/marko/marko/packages/routing/src/Http/Request.php` line 89-96. It builds `HTTP_{UPPER}` from the name, so case is automatically normalized by `strtoupper()`.
- Empty-string header values should be treated as null (not a valid scope path).

## Requirements (Test Descriptions)

- [ ] `it returns the header value when the configured header is present`
- [ ] `it returns null when the configured header is missing`
- [ ] `it returns null when the configured header is present but empty string`
- [ ] `it is case-insensitive for the header name`
- [ ] `it returns null when channel is cli`
- [ ] `it returns null when channel is queue`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `ScopeAxisResolverInterface`
- Tests use real `Request` instances where possible

## Implementation Notes
(Left blank — filled in by programmer during implementation)
