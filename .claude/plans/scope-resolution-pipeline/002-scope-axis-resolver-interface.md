# Task 002: Define ScopeAxisResolverInterface

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Define the strategy interface every per-axis resolver implements. Single method `resolve()` returning either a scope path string or `null` ("no opinion, defer to next resolver in chain").

## Context
- Target file: `packages/scope/src/Resolver/Resolution/ScopeAxisResolverInterface.php`
- Existing peer interfaces to model after: `packages/scope/src/Registry/ScopeRegistryInterface.php`, `packages/scope/src/Storage/HasScopesInterface.php`
- The `ScopeResolutionContext` type used in the method signature is defined in task 003. This task depends on 003 so the FQCN it references actually exists at PHPStan-analysis time (PHPStan level 8 will fail on unknown classes even when PHP autoloading is lazy). 003 is small and has no external deps, so the sequencing cost is negligible.

## Requirements (Test Descriptions)

- [ ] `it declares a resolve method taking ScopeAxis and ScopeResolutionContext returning nullable string`
- [ ] `it lives in the Markommerce\Scope\Resolver\Resolution namespace`
- [ ] `it is declared as an interface not a class`
- [ ] `it has no other methods or constants`
- [ ] `it documents that null means defer to next resolver in chain`

## Acceptance Criteria
- File header has `declare(strict_types=1);`
- All requirements have passing tests (a reflection-based test class suffices)
- Interface parameter names follow project convention: `ScopeAxis $scopeAxis`, `ScopeResolutionContext $scopeResolutionContext`
- No `final` modifier (interfaces can't be final but verify class-style declaration matches convention)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
