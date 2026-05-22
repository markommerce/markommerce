# Task 023: Handle-system exception catalog

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add the named exceptions used by the multi-handle system: inheritance cycles, unknown parent handles, default-handle conflicts, dynamic-handle conflicts, missing dynamic handles, duplicate context tokens during inheritance merge, and chained handle providers. Each follows the existing `LayoutException` static-factory pattern (`message`, `context`, `suggestion`).

## Context
- Existing exception catalog lives at `packages/layout/src/Exception/`. New exceptions follow the same shape — extend `LayoutException`, ship a single static factory, include a `suggestion` that names the file and line to fix.
- Pattern reference: `packages/layout/src/Exception/DanglingAnchorException.php`.
- These exceptions are thrown by the compiler/runtime in tasks 026, 027, 028, 029, and 030. Defining them up front keeps the loud-error contract intact when those tasks land.
- The seven new exceptions:
  - `CircularInheritanceException` — task 026 (cycle in `inherits:` chain). Factory `forChain(list<string> $chain)`.
  - `UnknownParentHandleException` — task 026 (`inherits:` points to a handle that does not exist). Factory `forParent(string $parent, string $child)`.
  - `DefaultHandleConflictException` — task 027 (`'default'` handle declares `extends:`, `inherits:`, or `handleProviders`). Factory `forField(string $field)` or three named factories.
  - `DynamicHandleConflictException` — tasks 029/030 (merged trees declare the same placement name). Factory `forCollidingPlacement(string $placementName, string $baseHandle, string $dynamicHandle)`.
  - `UnknownDynamicHandleException` — task 029 (a `HandleProvider` returns a handle key that is not in the compiled artifact). Factory `forHandle(string $handle, string $providerClass)`.
  - `DuplicateContextTokenException` — task 026/027 (inheritance or default merge introduces a context token already defined on the child). Factory `forToken(string $token, string $sourceHandle, string $targetHandle)`.
  - `ChainedHandleProviderException` — tasks 028/030 (a dynamic handle's tree itself declares `handleProviders`). Factory `forChain(string $providerClass, string $dynamicHandle)`.

## Requirements (Test Descriptions)
- [x] `it throws CircularInheritanceException with the inheritance chain in context`
- [x] `it throws UnknownParentHandleException with the missing parent name and suggestion to define it`
- [x] `it throws DefaultHandleConflictException when a default handle declares an extends or inherits chain`
- [x] `it throws DefaultHandleConflictException when a default handle declares handleProviders`
- [x] `it throws DynamicHandleConflictException when two merged trees declare the same placement name`
- [x] `it throws UnknownDynamicHandleException when a HandleProvider returns a handle key not in the artifact`
- [x] `it throws DuplicateContextTokenException when an inherits or default merge introduces a duplicate token`
- [x] `it throws ChainedHandleProviderException when a dynamic handle's tree itself declares handleProviders`
- [x] `each new exception extends LayoutException`
- [x] `each new exception is documented in the package README exception table`

## Acceptance Criteria
- All requirements have passing tests
- All seven new exceptions appear in `packages/layout/README.md` exception list
- PHPStan level 8 clean

## Implementation Notes
- Created seven exception classes in `packages/layout/src/Exception/`: `CircularInheritanceException`, `UnknownParentHandleException`, `DefaultHandleConflictException`, `DynamicHandleConflictException`, `UnknownDynamicHandleException`, `DuplicateContextTokenException`, `ChainedHandleProviderException`
- Each extends `LayoutException` with a single static factory method following the existing pattern
- All have non-empty `suggestion` fields pointing to actionable fixes
- Added an Exceptions table to `packages/layout/README.md` listing all 17 exceptions (10 existing + 7 new)
- Tests in `packages/layout/tests/Unit/Exception/HandleExceptionCatalogTest.php`
- PHPStan level 8 clean, all 1031 tests pass
