# Task 010: ScopedFieldExpression + ScopedFieldRendererInterface

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Introduce `ScopedFieldExpression` and `ScopedFieldRendererInterface` — the generic, use-case-agnostic SQL expression types that replace the old `ScopeSortExpression` and `ScopeSortRendererInterface`. The new types carry a list of candidate `ScopeSignature` objects (descending-score order from the enumerator) and the fallback column; the renderer emits a bare SQL expression that the caller wraps with `orderByRaw`, `selectRaw`, or `whereRaw` as appropriate.

## Context
- Files to create:
  - `packages/scope/src/Query/ScopedFieldExpression.php` (replaces `ScopeSortExpression`).
  - `packages/scope/src/Query/ScopedFieldRendererInterface.php` (replaces `ScopeSortRendererInterface`).
- Files to delete (in this same task — no transition period):
  - `packages/scope/src/Query/ScopeSortExpression.php`
  - `packages/scope/src/Query/ScopeSortRendererInterface.php`
  - `packages/scope/tests/Unit/Query/ScopeSortExpressionTest.php`
  - `packages/scope/tests/Unit/Query/ScopeSortRendererInterfaceTest.php`
- `ScopedFieldExpression` shape:
  ```
  readonly class ScopedFieldExpression
  {
      /**
       * @param list<ScopeSignature> $candidateSignatures in descending-score order
       */
      public function __construct(
          public string $property,        // JSONB inner key, e.g. 'price'
          public string $column,          // fallback column name, e.g. 'price'
          public array $candidateSignatures,
          public string $jsonColumn = 'scopes',
      ) {}
  }
  ```
  Note: NO `direction` field — direction belongs to the calling query spec, not the expression.
- `ScopedFieldRendererInterface`:
  ```
  interface ScopedFieldRendererInterface
  {
      /** @throws InvalidColumnException */
      public function render(ScopedFieldExpression $expression): string;
  }
  ```
- Implementation lives in `scope-pgsql` (task 011); this task only introduces the contracts and removes the old contracts.
- `NoDriverException::DRIVER_PACKAGES` (currently lists `markommerce/scope-pgsql`) — keep the list as-is; UPDATE the static factory `noDriverInstalled()` so its `message` and `context` reference the new `ScopedFieldRendererInterface` (the old strings reference "scope sort renderer").
- Update `packages/scope/module.php`: remove the binding hint comment referring to `ScopeSortRendererInterface` if any; the loose binding stays as-is.
- Update `packages/scope/tests/Unit/ModulePhpTest.php`: replace every `ScopeSortRendererInterface` reference with `ScopedFieldRendererInterface`. The test names should match (e.g. `'does not bind ScopedFieldRendererInterface'`).

## Requirements (Test Descriptions)
- [ ] `ScopedFieldExpression can be constructed with property, column, and a list of signatures`
- [ ] `ScopedFieldExpression accepts an empty signatures list (fallback-column-only case)`
- [ ] `ScopedFieldExpression defaults jsonColumn to "scopes"`
- [ ] `ScopedFieldExpression does NOT have a direction field`
- [ ] `ScopedFieldRendererInterface declares a single render method returning a string`
- [ ] `the old ScopeSortExpression class file does not exist`
- [ ] `the old ScopeSortRendererInterface file does not exist`
- [ ] `no PHP file under packages/scope or packages/scope-pgsql imports ScopeSortRendererInterface or ScopeSortExpression`
- [ ] `ScopedFieldExpression preserves the candidateSignatures order as given (the constructor does not sort or normalize them; the order is the renderer's contract)`
- [ ] `NoDriverException::noDriverInstalled() message and context reference ScopedFieldRendererInterface (not the old ScopeSortRendererInterface vocabulary)`

## Acceptance Criteria
- All requirements have passing tests.
- The new files declare `strict_types=1` and have correct `@throws` PHPDoc tags.
- `ScopedFieldExpression` is `readonly class`, not `final`.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
