# Task 002: Catalog Domain Exceptions

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the catalog domain exceptions used by the services and controller: `ProductNotFoundException`, `CategoryNotFoundException`, and `DuplicateSkuException`. All extend `MarkoException` and expose static factory methods.

## Context
- Create in `packages/catalog/src/Exceptions/`.
- All catalog exceptions extend the framework base exception `MarkoException`. Find the exact base class and namespace by inspecting an existing exception in `packages/scope/src/Exceptions/` (e.g. `ScopeStorageException.php`) — reuse that same base class and constructor shape (`message`, `context`, `suggestion` named parameters).
- Use static factory methods, not raw `new`:
  - `ProductNotFoundException::forId(int $id)`
  - `CategoryNotFoundException::forId(int $id)`
  - `DuplicateSkuException::forSku(string $sku)`
- Each factory supplies a `message`, a `context` describing the operation, and a `suggestion` (per `architecture.md` Exception Standards).
- No `final` classes.

## Requirements (Test Descriptions)
- [x] `it builds a ProductNotFoundException for an id with a message containing that id`
- [x] `it builds a ProductNotFoundException with non-empty context and suggestion`
- [x] `it builds a CategoryNotFoundException for an id with a message containing that id`
- [x] `it builds a DuplicateSkuException for a sku with a message containing that sku`
- [x] `it builds a DuplicateSkuException with non-empty context and suggestion`
- [x] `it makes every catalog exception an instance of the MarkoException base class`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Created three exception classes in `packages/catalog/src/Exceptions/`: `ProductNotFoundException`, `CategoryNotFoundException`, `DuplicateSkuException`
- All extend `Marko\Core\Exceptions\MarkoException` (same base as scope exceptions)
- Each uses a static factory method (`forId(int $id)` / `forSku(string $sku)`) with named parameters `message`, `context`, `suggestion`
- Added `markommerce/catalog` to root `composer.json` require and `marko/log` to `require-dev` (needed by scope resolution pipeline test)
- Test file: `packages/catalog/tests/Unit/Exceptions/CatalogExceptionsTest.php`
