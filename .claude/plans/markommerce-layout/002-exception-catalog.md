# Task 002: Exception Catalog

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the full layout exception catalog. A `LayoutException` base extends `MarkoException`; ten named subclasses each provide static factory methods that populate `message`, `context`, and `suggestion`. These are the "loud errors" — every compiler failure mode has a dedicated, located, suggestion-bearing exception.

## Context
- All exceptions extend `MarkoException` (markommerce convention — see `code-standards.md` Exception Standards).
- Each exception carries `message`, `context`, `suggestion` named constructor params, set via static factory methods.
- `context` should carry the placement chain (e.g. `storefront → category_show → content > product_grid`) or the offending file path + line where determinable.
- Exceptions live in `src/Exception/`.
- The ten named exceptions: `UnknownContextException`, `UnknownIterationException`, `TypeMismatchException`, `RepeatTypeMismatchException`, `DanglingAnchorException`, `DuplicateNameException`, `MissingDataKeyException`, `ExtensionConflictException`, `MissingPropException`, `InvalidSourceTypeException`.
- Patterns to follow: `marko/layout/src/Exceptions/` (e.g. `SlotNotFoundException::forSlot()`), `markommerce/catalog` exception classes.

## Requirements (Test Descriptions)
- [ ] `it provides a LayoutException base extending MarkoException`
- [ ] `it builds UnknownContextException naming the missing context token and layout`
- [ ] `it builds UnknownIterationException naming the iteration token and the placement`
- [ ] `it builds TypeMismatchException naming expected type, actual type and the prop`
- [ ] `it builds RepeatTypeMismatchException naming the yields type and the actual item type`
- [ ] `it builds DanglingAnchorException naming the missing anchor and the extension file`
- [ ] `it builds DuplicateNameException naming the duplicated placement name`
- [ ] `it builds MissingDataKeyException naming the data key and the component`
- [ ] `it builds ExtensionConflictException naming the conflicting operations and priority`
- [ ] `it builds MissingPropException naming the required prop and the component`
- [ ] `it builds InvalidSourceTypeException naming the source, the value and the target type`
- [ ] `it includes a non-empty suggestion on every exception factory`

## Acceptance Criteria
- All requirements have passing tests
- Every exception extends `MarkoException`
- Every factory sets all three of message/context/suggestion
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
