# Task 013: Build AcceptLanguageResolver

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that parses the `Accept-Language` HTTP header (with q-values) and returns the highest-preference language tag that exists in the axis hierarchy. Falls back to null if no language in the header matches an existing path. Channel-restricted to HTTP.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/AcceptLanguageResolver.php`
- Constructor: zero-arg (no params)
- Returns `null` when `$context->channel !== ScopeResolutionContext::CHANNEL_HTTP`
- Reads `Accept-Language` header from the request via `$context->request->header('Accept-Language')` (verified at `/home/michal/www/marko/marko/packages/routing/src/Http/Request.php`).
- Validates candidates against `$scopeAxis->hierarchy->exists($candidate)` — the `$scopeAxis` parameter comes from the `resolve()` method's first arg per `ScopeAxisResolverInterface` (task 002).
- Parse format: `lang[;q=N], lang2[;q=M], ...` (e.g. `en-US,en;q=0.9,pl;q=0.8`).
- Sort candidates by q-value descending. q is a float in [0,1]; default is 1.0 when omitted.
- For each candidate, check `$scopeAxis->hierarchy->exists($candidate)` — return the first match. Also try matching the language code without region (e.g. `en-US` falls back to `en` if `en-US` doesn't exist but `en` does). Region-stripped fallback runs AFTER all regional variants have been tried.
- Normalize case: HTTP language tags are case-insensitive per RFC; lowercase the tag before checking the hierarchy. (Scope paths are case-sensitive — the convention is lowercase; document this.)
- On malformed header (parse failure), return null — never throw. Treat malformed tokens within a header as skippable, not as fatal.
- Q-values out of [0, 1] range: skip that candidate (don't throw).

## Requirements (Test Descriptions)

- [ ] `it returns the highest q-value language that exists in the axis hierarchy`
- [ ] `it returns null when no language in the header matches the hierarchy`
- [ ] `it returns null when the Accept-Language header is missing`
- [ ] `it returns null when the Accept-Language header is empty string`
- [ ] `it returns null when the Accept-Language header is malformed`
- [ ] `it treats omitted q-value as 1.0`
- [ ] `it falls back to language code without region when the regional variant is not in the hierarchy`
- [ ] `it lowercases language tags before checking the hierarchy`
- [ ] `it skips candidates with q-values outside the 0 to 1 range`
- [ ] `it returns null when channel is cli`
- [ ] `it returns null when channel is queue`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `ScopeAxisResolverInterface`
- Parse logic is internal — no public helper methods exposed
- Tests cover at least: simple header, multi-value with q, malformed input, missing header

## Implementation Notes
(Left blank — filled in by programmer during implementation)
