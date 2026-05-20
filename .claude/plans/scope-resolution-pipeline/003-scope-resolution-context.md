# Task 003: Define ScopeResolutionContext and SyntheticRequest Factory

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Build the read-only value object resolvers receive. Wraps a `Marko\Routing\Http\Request` (real or synthetic), the `ScopeRegistryInterface`, the `$resolved` axis-name → path map (axes resolved earlier in the same pipeline run), and a `$channel` string. Also ship a `SyntheticRequest` factory that produces a valid empty Request for CLI/queue lifecycles so resolvers never null-check.

## Context
- Target files:
  - `packages/scope/src/Resolver/Resolution/ScopeResolutionContext.php` — the value object
  - `packages/scope/src/Resolver/Resolution/SyntheticRequest.php` — static factory that returns a `Marko\Routing\Http\Request`
- **Verified constructor** at `/home/michal/www/marko/marko/packages/routing/src/Http/Request.php`:
  ```php
  public function __construct(
      private array $server = [],
      private array $query = [],
      private array $post = [],
      private string $body = '',
  )
  ```
  The Request takes NO cookies arg and exposes no cookie/host accessors. SyntheticRequest cannot meaningfully populate cookies; that's fine because CLI/queue resolvers don't need them.
- Synthetic request shape: empty `$server`, empty `$query`, empty `$post`, empty `$body`. This gives `method() === 'GET'` (per `Request::method()`'s default) and `path() === '/'` (per `Request::path()`'s default when `REQUEST_URI` is absent).
- Add `marko/routing` to `packages/scope/composer.json` `require` (at `self.version`).
- The `$resolved` map on `ScopeResolutionContext` contains axes resolved EARLIER in the same pipeline run, EXCLUDING the axis currently being resolved. Each axis sees the cumulative resolved-so-far map.
- Channel values are string literals — declare as class constants on `ScopeResolutionContext`: `const string CHANNEL_HTTP = 'http';` etc.

## Requirements (Test Descriptions)

- [ ] `it stores request registry resolved map and channel as readonly properties`
- [ ] `it exposes CHANNEL_HTTP CHANNEL_CLI and CHANNEL_QUEUE constants with explicit string types`
- [ ] `it accepts an empty resolved map`
- [ ] `it accepts a populated resolved map mapping axis names to paths`
- [ ] `it is declared as readonly class`
- [ ] `SyntheticRequest factory returns a Marko Request with empty server query post and body`
- [ ] `SyntheticRequest factory returns a request whose method is GET and path is /`
- [ ] `SyntheticRequest factory returns a request whose header lookup returns null for any name`
- [ ] `SyntheticRequest factory does not throw when constructed`

## Acceptance Criteria
- All requirements have passing tests
- `ScopeResolutionContext` is `readonly class` with constructor-injected properties
- All class constants have explicit type declarations (PHP 8.3+ requirement per project standards)
- `packages/scope/composer.json` updated to require `marko/routing` (`self.version`)
- File headers have `declare(strict_types=1);`

## Implementation Notes
(Left blank — filled in by programmer during implementation)
