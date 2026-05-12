# Task 002: Create `MoneyException` in `markommerce/money`

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add the `MoneyException` class to `markommerce/money`. This is the exception part of the Money contract — any implementation of `MoneyInterface` (including the default in `markommerce/money-moneyphp`, task 003) throws it. It extends `Marko\Core\Exceptions\MarkoException` and exposes named static factory methods that populate `message`, `context`, and `suggestion`.

## Context
- File location: `packages/money/src/MoneyException.php`
- Pattern reference: project code-standards.md exception example.
- Extends `Marko\Core\Exceptions\MarkoException`. Named factory methods only (no public `__construct` override).
- Factory methods to create:
  - `currencyMismatch(string $expected, string $actual, string $operation)` — used by implementations from `add` / `subtract` / `greaterThan` / `lessThan` / arithmetic operations when operand currencies differ.
  - `invalidAllocationRatios(string $reason)` — used by `allocate` when ratios array is empty, contains a non-positive value, or sums to zero.
  - `invalidMultiplyFactor(string $factor, string $reason)` — used by `multiply` when the string is not a valid numeric representation.
- Every factory's message includes the relevant values to make the error self-diagnosing (currencies, factor string, reason).
- Carry `@throws MoneyException` PHPDoc on every `MoneyInterface` method that the contract says can throw — but the actual throwing happens in the driver; on the interface, the `@throws` annotation describes the contract.

## Requirements (Test Descriptions)
- [ ] `it constructs a currency mismatch exception carrying expected currency actual currency and the operation name in the message`
- [ ] `it constructs an invalid allocation ratios exception carrying the failure reason in the message`
- [ ] `it constructs an invalid multiply factor exception carrying both the factor and the reason`
- [ ] `it provides a non-empty context and suggestion on every factory method`
- [ ] `it extends MarkoException so handlers catching MarkoException also catch MoneyException`

## Acceptance Criteria
- `MoneyException` lives in the `Markommerce\Money` namespace, file `packages/money/src/MoneyException.php`.
- No public `__construct` is defined — only the parent's, invoked via static factories.
- Tests in `packages/money/tests/Unit/MoneyExceptionTest.php`.
- Not `final`.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
