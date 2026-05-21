# Task 016: Build JobScopeWrapper Helper (Queue) — Manual Opt-In

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Provide a manual opt-in helper class users can wrap their job logic with to auto-resolve scope. **A plugin-based auto-wiring is NOT possible** because `Marko\Queue\Worker::work()` obtains jobs via `$queue->pop()` (which uses `unserialize()`), NOT through the container — see `/home/michal/www/marko/marko/packages/queue/src/Worker.php` line 28-41 and `Job::unserialize()` at `/home/michal/www/marko/marko/packages/queue/src/Job.php` line 31-34. Plugin interception proxies are generated at container resolve time only, so a `#[Plugin(target: JobInterface::class)]` would never fire.

## Context
- Target file: `packages/scope/src/Queue/JobScopeWrapper.php`
- The wrapper is intended for users who want scope-aware jobs to call `JobScopeWrapper::withScope(fn() => $this->doActualWork())` from inside their `handle()` method.
- Constructor deps:
  - `ScopeResolutionPipeline $scopeResolutionPipeline`
  - (no others)
- Public method signature:
  ```php
  /**
   * @template T
   * @param callable(): T $work
   * @return T
   * @throws \Throwable rethrows whatever $work throws after clearing scope
   */
  public function withScope(callable $work): mixed
  ```
- Implementation:
  1. Defensively `$pipeline->clear()` (in case a prior job in the same worker process leaked).
  2. `$pipeline->run(SyntheticRequest::create(), ScopeResolutionContext::CHANNEL_QUEUE)`.
  3. `try { return $work(); } finally { $pipeline->clear(); }`.
- The class file MUST NOT carry `#[Plugin]` attributes — it's a regular service, container-resolvable, used by application code.
- `composer.json`: optionally add `"suggest": { "marko/queue": "Enables queue-side scope resolution via JobScopeWrapper" }`. The class itself does not reference `JobInterface` (it only operates on callables), so it can be loaded even without marko/queue installed.
- Document the limitation in the class docblock: "Marko's `Worker` deserializes jobs and invokes `handle()` directly, bypassing the container — plugin-based auto-resolution is not possible. Users must explicitly call `JobScopeWrapper::withScope()` inside `handle()`."

## Requirements (Test Descriptions)

- [ ] `withScope defensively clears the pipeline before resolving`
- [ ] `withScope runs the pipeline with queue channel`
- [ ] `withScope uses a SyntheticRequest as the request`
- [ ] `withScope returns the value produced by the wrapped callable`
- [ ] `withScope calls pipeline clear after the callable returns normally`
- [ ] `withScope calls pipeline clear after the callable throws`
- [ ] `withScope rethrows the original throwable when the callable throws`
- [ ] `the class carries no Plugin attribute (verified via reflection)`

## Acceptance Criteria
- All requirements have passing tests
- Class is `readonly class`, NOT `final`
- Does NOT depend on `marko/queue` interfaces (deliberately decoupled)
- File class-level docblock explains why this is manual opt-in (Worker uses unserialize, not container)
- Tests do not require `marko/queue` to be installed

## Implementation Notes
(Left blank — filled in by programmer during implementation)
