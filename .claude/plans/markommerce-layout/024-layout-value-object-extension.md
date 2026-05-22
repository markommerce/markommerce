# Task 024: Extend `Layout` value object with `operations` and `inherits`

**Status**: complete
**Depends on**: 023
**Retry count**: 0

## Description
Extend the `Layout` value object so layout files can declare both placements **and** operations targeting their own (or inherited) placements, and can declare a single parent handle to inherit from. This unlocks default-handle behavior, handle inheritance, and the foundation for dynamic handles — without changing how extension files in `layout/extensions/` work.

## Context
- Current shape: `Layout(handle, extends, context, slots, template)`. `LayoutExtension(handle, operations, priority)` is separate.
- New shape: `Layout(handle, extends, inherits, context, slots, operations, template)`. Both new fields are optional, default empty/null. Existing layouts compile unchanged.
- `inherits: ?string` names another handle whose tree gets flattened into this one at compile time (task 026 implements the flattening). Multi-parent inheritance is explicitly out of scope.
- `operations: list<Operation>` lets a layout file remove/replace/wrap placements declared in `extends` or `inherits` ancestors — solves the catalog-product-listing-removes-default-component use case.
- `Layout::$handle` continues to accept `array|string|null`. Add no new validation rules here — those go in task 027/030.
- `LayoutExtension` is unchanged. Files in `layout/extensions/` still target a handle from outside.
- Adjust constructors in `packages/layout-demo/layout/layout_demo.php` and any other call sites that pass positional args, so they keep compiling.
- Before adding fields, grep the monorepo for any positional `new Layout(` calls in `packages/*/layout/`, `packages/*/src/Layout/`, and `tests/`. Verified at planning time: `category_show.php`, `layout_demo.php`, and all `theme-blank` `LayoutDefinition::define()` implementations use named arguments. Tests likewise use named args. Add a CI grep assertion if practical.
- Field-ordering: the constructor adds `inherits: ?string = null` after `extends:` and `operations: list<Operation> = []` after `slots:`. Place new params with defaults at the end so existing named-arg calls are unaffected.

## Requirements (Test Descriptions)
- [x] `it accepts an inherits parameter as nullable string defaulting to null`
- [x] `it accepts an operations parameter as list of Operation defaulting to empty array`
- [x] `it stores both fields as readonly public properties`
- [x] `it preserves backwards compatibility with layouts that omit the new fields`
- [x] `it rejects an inherits value that equals the layout's own handle key`
- [x] `it passes PHPStan level 8 with the new fields typed`

## Acceptance Criteria
- All requirements have passing tests
- Existing `Layout` consumers still pass without modification (or are updated in this task)
- Compiler changes for the new fields are NOT in scope here — only the value object

## Implementation Notes
- Added `inherits: ?string = null` after `extends` and `operations: list<Operation> = []` after `slots` in `Layout` constructor.
- To satisfy PHP's "required params must precede optional" rule, `context` and `slots` were given default values of `[]`. All existing call sites use named args, so this is backwards compatible.
- Self-inheritance guard: constructor computes the handle key from `handle` (string as-is, array as `Class::method`) and throws `InvalidArgumentException` when `inherits` equals the key.
- `readonly class` ensures both new properties are automatically readonly and public.
- PHPStan level 8 passes cleanly; the `@throws InvalidArgumentException` PHPDoc tag is added per project standards.
- Test file created at `packages/layout/tests/Unit/LayoutTest.php`.
