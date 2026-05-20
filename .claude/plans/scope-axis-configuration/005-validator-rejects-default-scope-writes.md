# Task 005: Reject Overrides Written at a Default Scope in ScopeSignatureValidator

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Adds `InvalidSignatureForAttributeException::forDefaultScope(string $axis, string $defaultScope)` and teaches `ScopeSignatureValidator::validate()` to throw it for any signature that names an axis at the axis's declared default scope. The base column value *is* the default — writing a JSON override at a default-scope signature would be silently ignored by the enumerator (task 004), so it must be a loud error at write time. `ScopeResolver::setOverride()` and `clearOverride()` already call the validator, so they pick up the new rule automatically.

## Context

After task 004, the enumerator filters axis defaults out of candidate signatures, meaning a stored override at e.g. `locale:default` is unreachable by `ScopeWalker`. To prevent this footgun the validator must reject default-scope signatures up front, naming the axis and giving the merchant the actionable fix ("the base column already holds the default value — edit the property directly").

Partial signatures that simply *omit* an axis must continue to validate (omission is how composites scope on a subset of axes). The rule applies only to a signature that explicitly names an axis at its default.

Current code (`packages/scope/src/Signature/ScopeSignatureValidator.php:24-47`):
```php
foreach ($scopeSignature->axes() as $axis) {
    if (!in_array($axis, $attributeAxes, true)) {
        throw InvalidSignatureForAttributeException::forUnknownAxis($axis, $attributeAxes);
    }
    $value = $scopeSignature->get($axis);
    if ($value !== null && !$this->scopeRegistry->getHierarchy($axis)->exists($value)) {
        throw InvalidSignatureForAttributeException::forUnknownValue($value, $axis);
    }
}
```

Add a third check inside the loop comparing `$value` against `$this->scopeRegistry->getAxis($axis)->default` and throw `forDefaultScope($axis, $value)` on equality. Order the checks so "unknown axis" → "unknown value" → "default scope" reports the most-specific error.

- Files to modify:
  - `packages/scope/src/Signature/ScopeSignatureValidator.php`
  - `packages/scope/src/Exceptions/InvalidSignatureForAttributeException.php` (add the factory method)
  - `packages/scope/tests/Unit/Signature/ScopeSignatureValidatorTest.php` — the existing fake registry in this file has `getAxis()` throwing `RuntimeException('Not implemented')`. After this task's change, the validator calls `getAxis($axis)->default`, so the fake **must** be rewritten to construct and return real `ScopeAxis` instances (carrying the `default` field introduced in task 001). Adopt task 001's sentinel-default convention: extend `makeSignatureValidatorRegistry()` with an optional `array $axisDefaults = []` parameter; for each axis whose default is unset, fall back to the sentinel `'__test_default'` (prepended to the hierarchy if not already present). Have `getAxis($name)` return `new ScopeAxis(name: $name, hierarchy: $hierarchies[$name], default: $axisDefaults[$name] ?? '__test_default')`. This keeps the existing "accepts a signature naming a valid value" assertions intact while letting new tests pass explicit defaults (e.g. `'b2c'`) to assert the rejection path.
  - `packages/scope/tests/Unit/Exceptions/ScopeExceptionsTest.php` (cover the new factory)
- Patterns to follow:
  - Static factory methods on `MarkoException` subclasses with `message`/`context`/`suggestion`.
  - Re-use the existing `@throws InvalidSignatureForAttributeException` PHPDoc on `validate()` — no signature change.
  - The validator's internal cache (`$cache` keyed on `signature . '||' . axes`) still works untouched.

## Requirements (Test Descriptions)
- [ ] `it rejects a single-axis signature naming the axis at its default scope`
- [ ] `it throws InvalidSignatureForAttributeException for a default-scope signature`
- [ ] `it rejects a default-scope axis inside a multi-axis composite signature`
- [ ] `it accepts a signature naming an axis at a non-default scope`
- [ ] `it accepts a partial signature that omits an axis entirely`

## Acceptance Criteria
- All requirements have passing tests.
- The exception's `message`, `context`, and `suggestion` name the axis and the default scope, and explain that the base column is the default.
- `composer test` for the `scope` package is green. **Do not gate on `composer test:all`** — `scope-pgsql`'s integration test stays red until task 007 lands.
- `phpstan analyse` clean.
- Code follows code standards.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
