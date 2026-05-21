# Task 006: Build ScopeResolutionPipeline

**Status**: pending
**Depends on**: 002, 003, 004, 005
**Retry count**: 0

## Description
The orchestrator. Iterates axes in `ScopeRegistryInterface::listAxes()` order; for each axis runs its resolver chain (via the factory); writes the first non-null hierarchy-valid result to `ScopeContext::in()`; falls back to axis default if all resolvers return null. Catches resolver-thrown `Throwable`s, wraps in `ScopeResolutionException`, logs (if logger available), and continues to the next resolver in the chain — never propagates.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/ScopeResolutionPipeline.php`
- Constructor deps:
  - `ScopeRegistryInterface $scopeRegistry`
  - `ScopeContext $scopeContext`
  - `ScopeResolverChainFactory $scopeResolverChainFactory`
  - `?\Marko\Log\Contracts\LoggerInterface $logger = null` — the FQCN is `Marko\Log\Contracts\LoggerInterface` (verified at `/home/michal/www/marko/marko/packages/log/src/Contracts/LoggerInterface.php`). Marko's container does NOT autowire `?Type = null` for non-builtin types — the pipeline MUST be constructed via a closure binding in module.php (task 017) that conditionally resolves the logger only when marko/log is installed. The constructor accepts `null` and the runtime branch is `if ($this->logger !== null) { $this->logger->error(…) }`.
- Public method: `run(Request $request, string $channel): void`. Builds a `ScopeResolutionContext` per-axis with the in-progress `$resolved` map; writes resolved scope to `ScopeContext`.
- Public method: `clear(): void` — calls `$this->scopeContext->clearAll()`. Lifecycle hooks call this in their finally blocks.
- Resolver failure handling:
  - Resolver returns non-null path → call `$axis->hierarchy->exists($path)`. If true, accept it; if false, log + skip to next resolver. (Wrap in `ScopeResolutionException::invalidPath` for the log message.)
  - Resolver throws → catch `Throwable`, wrap in `ScopeResolutionException::resolverFailed`, log, skip to next.
  - All resolvers exhausted with no accepted path → write `$axis->default`.
- Pipeline must build `ScopeResolutionContext` fresh per-axis (since `$resolved` grows as the loop progresses). The same `Request` and `$channel` are reused.

## Requirements (Test Descriptions)

- [ ] `it iterates axes in registry registration order`
- [ ] `it writes the first non-null hierarchy-valid resolver result to ScopeContext for each axis`
- [ ] `it falls back to axis default when chain is empty`
- [ ] `it falls back to axis default when all resolvers return null`
- [ ] `it exposes resolved axes to later resolvers via context resolved map`
- [ ] `it propagates the request and channel into the ScopeResolutionContext for every resolver`
- [ ] `it skips a resolver that returns a path not in the axis hierarchy and tries the next one`
- [ ] `it skips a resolver that throws an exception and tries the next one`
- [ ] `it never propagates a Throwable thrown by a resolver out of run`
- [ ] `it logs ScopeResolutionException via the injected logger when a resolver fails`
- [ ] `it operates correctly when the logger is null`
- [ ] `clear delegates to ScopeContext clearAll`

## Acceptance Criteria
- All requirements have passing tests
- Pipeline NOT `final`. May be `readonly class` IF all properties are immutable (logger is injected once; factory cache lives on the factory, not the pipeline).
- Constructor uses interface param naming convention: `ScopeRegistryInterface $scopeRegistry`, `ScopeContext $scopeContext`, etc.
- `run()` docblock states "Does not throw — all resolver exceptions are caught, wrapped in `ScopeResolutionException`, logged, and the chain continues." No `@throws` tag because nothing escapes.
- Tests use fake resolvers (anonymous classes implementing the interface) for all behaviors
- Tests cover: ScopeContext is fully cleared between pipeline runs that resolve different axes — pipeline `clear()` calls `$scopeContext->clearAll()` which wipes ALL axes including any set by user code in nested middleware. This is intentional (HTTP request boundary == fresh ScopeContext) — add a regression test asserting it.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
