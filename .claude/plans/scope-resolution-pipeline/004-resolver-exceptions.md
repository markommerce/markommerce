# Task 004: Define Resolver Exception Classes

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add two new exception classes following project convention (extend `MarkoException` with `message`, `context`, `suggestion`; provide static factory methods). `InvalidResolverConfigException` for config-time validation failures; `ScopeResolutionException` wraps unexpected resolver runtime failures so they can be logged and skipped rather than crashing the request.

## Context
- Target files:
  - `packages/scope/src/Exceptions/InvalidResolverConfigException.php`
  - `packages/scope/src/Exceptions/ScopeResolutionException.php`
- Pattern: copy structure from `packages/scope/src/Exceptions/UnknownAxisException.php` (or any existing scope exception). All extend `Marko\Core\Exceptions\MarkoException`.
- Static factories required:
  - `InvalidResolverConfigException::unknownClass(string $className, string $axisName)` — class doesn't exist
  - `InvalidResolverConfigException::missingClassKey(int $index, string $axisName)` — array form missing `class` key
  - `InvalidResolverConfigException::notImplementingInterface(string $className, string $axisName)` — class doesn't implement `ScopeAxisResolverInterface`
  - `ScopeResolutionException::resolverFailed(string $resolverClass, string $axisName, Throwable $previous)` — wraps an unexpected exception thrown by a resolver
  - `ScopeResolutionException::invalidPath(string $resolverClass, string $axisName, string $path)` — resolver returned a path not in the axis hierarchy

## Requirements (Test Descriptions)

- [x] `InvalidResolverConfigException unknownClass produces message naming the class and axis`
- [x] `InvalidResolverConfigException unknownClass produces suggestion pointing at the config path`
- [x] `InvalidResolverConfigException missingClassKey identifies the array index and axis`
- [x] `InvalidResolverConfigException notImplementingInterface names ScopeAxisResolverInterface in suggestion`
- [x] `ScopeResolutionException resolverFailed preserves the original throwable as previous`
- [x] `ScopeResolutionException resolverFailed message names the resolver class and axis`
- [x] `ScopeResolutionException invalidPath message names the offending path the resolver and the axis`
- [x] `both exception classes extend MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Each static factory returns `self`
- Every static factory has a `@throws` PHPDoc tag on any call site that won't appear until later tasks — for now just write the factories
- Files start with `declare(strict_types=1);`

## Implementation Notes
- `InvalidResolverConfigException` and `ScopeResolutionException` both extend `MarkoException` via `new self(message:, context:, suggestion:, previous:)` named args
- `ScopeResolutionException::resolverFailed` passes the `Throwable $previous` as the named `previous:` arg
- Tests live in separate files: `InvalidResolverConfigExceptionTest.php` and `ScopeResolutionExceptionTest.php`
- The "both extend MarkoException" requirement appears in both test files as a redundant guard; all 9 tests pass
