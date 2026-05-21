# Task 007: Build CookieResolver

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Built-in resolver that reads a named cookie and returns its value (or null if the cookie is missing). Channel-restricted to HTTP.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/Builtin/CookieResolver.php`
- **Important constraint:** `Marko\Routing\Http\Request` has NO cookie accessor and its constructor accepts no cookies array. We cannot read cookies through the Request object. Verified at `/home/michal/www/marko/marko/packages/routing/src/Http/Request.php` — no `cookie()` / `cookies()` method and constructor takes only `$server, $query, $post, $body`.
- **Cookie source strategy:** Read `$_COOKIE` superglobal directly, but provide a constructor-injected seam so tests can supply a cookie map without manipulating the superglobal.
- Constructor signature:
  ```php
  /** @param array<string, string>|null $cookies When null, reads from $_COOKIE at resolve time. */
  public function __construct(
      private string $cookieName,
      private ?array $cookies = null,
  ) {}
  ```
  In tests, pass an explicit `$cookies` map. In production wiring, omit the argument so the superglobal is used.
- Returns `null` when `$context->channel !== ScopeResolutionContext::CHANNEL_HTTP`
- Returns the cookie value as a string, or null if missing/empty
- Do NOT depend on `marko/authentication`'s `CookieJarInterface` — that pulls in session/auth machinery unrelated to scope resolution.
- Cookie reading should happen inside `resolve()`, not in the constructor — `$_COOKIE` may be repopulated between resolver construction and resolution in unusual test scenarios.

## Requirements (Test Descriptions)

- [ ] `it returns the cookie value when the configured cookie is present in the injected cookie map`
- [ ] `it returns null when the configured cookie is not set`
- [ ] `it returns null when the cookie value is empty string`
- [ ] `it returns null when channel is cli`
- [ ] `it returns null when channel is queue`
- [ ] `it ignores cookies with a different name`
- [ ] `it reads from $_COOKIE superglobal when no explicit map is injected`

## Acceptance Criteria
- All requirements have passing tests
- Class is `readonly class` (all properties immutable)
- Class is NOT `final`
- Implements `ScopeAxisResolverInterface`
- Tests pass an explicit `$cookies` array — they MUST NOT mutate `$_COOKIE` (would cross-pollute parallel pest workers). One single test for the superglobal-fallback path may set/unset `$_COOKIE` with a `finally` block to restore.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
