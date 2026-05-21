# Task 015: Build ScopeResolutionCommandPlugin (CLI)

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Marko plugin (`#[Plugin(target: CommandInterface::class)]`) that resolves scope before any CLI command executes and clears it afterward. Auto-discovered by Marko's plugin scanner — no manual registration needed.

## Context
- Target file: `packages/scope/src/Plugins/ScopeResolutionCommandPlugin.php`
- Pattern: `marko/packages/debugbar/src/Plugins/DatabaseConnectionPlugin.php`
- Attributes:
  - Class-level: `#[Plugin(target: \Marko\Core\Command\CommandInterface::class)]`
  - Method `#[Before(method: 'execute')] public function beforeExecute(Input $input, Output $output): void` — first calls `$pipeline->clear()` (defensive — clears any leaked state from a previous failed command in the same process), then `$pipeline->run(SyntheticRequest::create(), CHANNEL_CLI)`
  - Method `#[After(method: 'execute')] public function afterExecute(int $result, Input $input, Output $output): int` — calls `$pipeline->clear()`, returns `$result` unchanged. Note: this does NOT execute when `execute()` throws.
- **VERIFIED CONSTRAINT:** `#[After]` does NOT run when the wrapped method throws. See `/home/michal/www/marko/marko/packages/core/src/Plugin/PluginInterception.php` line 77 (`interceptCall`) and line 99 (`interceptParentCall`) — the target call is not wrapped in try/finally, so a thrown exception propagates before `runAfterPlugins()` is reached. This means a CLI command that throws will leave `ScopeContext` populated until the process exits.
- **Mitigation accepted by plan:** CLI processes are short-lived (one command per process invocation), so a leaked ScopeContext on uncaught command failure is process-local and lost when the process dies. Document this in the file's class-level docblock as a known limitation. A defensive `clearAll()` is also performed at the START of `beforeExecute` so a long-running test runner / REPL that reuses the container between commands doesn't carry state forward from a previous failed command.
- Constructor dep: `ScopeResolutionPipeline $scopeResolutionPipeline`

## Requirements (Test Descriptions)

- [ ] `it has Plugin attribute targeting CommandInterface`
- [ ] `beforeExecute defensively clears pipeline before resolving`
- [ ] `beforeExecute runs the pipeline with cli channel`
- [ ] `beforeExecute uses a SyntheticRequest as the request`
- [ ] `afterExecute calls pipeline clear`
- [ ] `afterExecute returns the original result unchanged`
- [ ] `it has Before attribute on beforeExecute targeting execute method`
- [ ] `it has After attribute on afterExecute targeting execute method`

## Acceptance Criteria
- All requirements have passing tests
- Class is NOT `final`. May be `readonly class` because it has no mutable state (only the injected pipeline).
- Attributes are exact as documented
- Tests use reflection to verify attributes are correctly applied (cannot easily integration-test plugin interception without booting the full app)
- Class-level docblock explicitly states: "`#[After]` does NOT run when `execute()` throws (Marko plugin chain limitation). The `beforeExecute` hook defensively clears state to compensate."

## Implementation Notes
(Left blank — filled in by programmer during implementation)
