# Task 014: Build ScopeResolutionMiddleware (HTTP)

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Marko HTTP middleware that runs the resolution pipeline before the controller and clears the scope context in a `finally` block — even when the handler throws. Channel is `ScopeResolutionContext::CHANNEL_HTTP`.

## Context
- Target file: `packages/scope/src/Middleware/ScopeResolutionMiddleware.php`
- Implements `Marko\Routing\Middleware\MiddlewareInterface` — signature: `handle(Request $request, callable $next): Response`
- Pattern to copy: `marko/packages/session/src/Middleware/SessionMiddleware.php` (the try/finally cleanup pattern)
- Constructor deps: `ScopeResolutionPipeline $scopeResolutionPipeline`
- Inside `handle()`:
  1. Call `$this->scopeResolutionPipeline->run($request, ScopeResolutionContext::CHANNEL_HTTP)`
  2. `try { $response = $next($request); } finally { $this->scopeResolutionPipeline->clear(); }`
  3. Return `$response`

## Requirements (Test Descriptions)

- [ ] `it runs the pipeline with http channel before calling next`
- [ ] `it passes the same request through to next handler`
- [ ] `it returns the response from the next handler`
- [ ] `it clears the scope context after the handler returns successfully`
- [ ] `it clears the scope context when the handler throws an Exception`
- [ ] `it clears the scope context when the handler throws an Error (not just Exception)`
- [ ] `it re-throws exceptions thrown by the handler`

## Acceptance Criteria
- All requirements have passing tests
- `readonly class`, not `final`
- Implements `MiddlewareInterface`
- Tests use a real `ScopeResolutionPipeline` with fake registry + context (NOT mocked pipeline) — the finally-clears-even-on-throw behavior is what we're verifying

## Implementation Notes
(Left blank — filled in by programmer during implementation)
