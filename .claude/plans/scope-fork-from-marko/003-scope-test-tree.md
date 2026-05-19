# Task 003: Copy & Rename markommerce/scope Test Tree

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Copy every test file from `marko/packages/scope/tests/` (except the scaffolding/sentinel tests already created in Task 001 — `PackageScaffoldingTest.php`, `Unit/ReadmeTest.php`) into `markommerce/packages/scope/tests/`, rewriting all `namespace Marko\Scope\Tests` declarations to `Markommerce\Scope\Tests` and all `use Marko\Scope\…;` imports to `Markommerce\Scope\…`. Other `use Marko\Config\…`, `use Marko\Core\…`, `use Marko\Database\…` imports stay. Re-author `Unit/ModulePhpTest.php` from upstream in this task (NOT in Task 001) because one of its assertions has to be rewritten to account for the Marko Container's hardcoded `Marko\\` prefix check (see Implementation note below). The full test suite for `markommerce/scope` should be green at the end of this task.

## Context

- Source test files to copy (relative to `/home/michal/www/marko/marko/packages/scope/tests/`):
  - `Unit/ScopeTest.php`
  - `Unit/ScopeAxisTest.php`
  - `Unit/Attributes/ScopedTest.php`
  - `Unit/Validation/ScopedEntityValidatorTest.php`
  - `Unit/Resolver/ScopeResolverTest.php`
  - `Unit/Registry/PhpScopeRegistryTest.php`
  - `Unit/Registry/ScopeRegistryInterfaceTest.php`
  - `Unit/Storage/HasScopesTraitTest.php`
  - `Unit/Storage/ScopedDataSerializerTest.php`
  - `Unit/Hierarchy/ScopeHierarchyTest.php`
  - `Unit/Context/ScopeContextTest.php`
  - `Unit/Resolution/ScopeWalkerTest.php`
  - `Unit/Query/ScopedOrderByTest.php`
  - `Unit/Query/ScopeSortRendererInterfaceTest.php`
  - `Unit/Query/ScopeSortExpressionTest.php`
  - `Unit/Query/ScopedOrderByFactoryTest.php`
  - `Unit/Metadata/ScopeMetadataFactoryTest.php`
  - `Unit/Exceptions/ScopeExceptionsTest.php`
  - `Unit/ModulePhpTest.php` (re-author with the BindingException rewrite — see below)
  - `Feature/ScopedOverridesPersistenceTest.php`
  - `Feature/ScopedOverridesEntityDirtyTrackingTest.php`
- Do NOT re-copy: `Pest.php`, `PackageScaffoldingTest.php`, `Unit/ReadmeTest.php` — those were authored in Task 001 with the correct rewrites.
- Rename rules (same as 002): `Marko\Scope\` → `Markommerce\Scope\` in `namespace`, `use`, FQCN strings, and any `Marko\\Scope\\` escaped string literals. Leave `Marko\Config\`, `Marko\Core\`, `Marko\Database\`, `Marko\Database\PgSql\` alone.
- **Verified (do not re-verify): neither `Feature/ScopedOverridesPersistenceTest.php` nor `Feature/ScopedOverridesEntityDirtyTrackingTest.php` references `Marko\Scope\PgSql\…`.** Both Feature tests use only `Marko\Scope\Storage\…` (renamed by us) plus `Marko\Database\…` (kept) plus an in-memory `ConnectionInterface` anonymous class implementation. They MUST remain in the default `composer test` run — do NOT tag them `integration-destructive`. Doing so would silently exclude legitimate coverage.
- **`Unit/ModulePhpTest.php` rewrite required** (this is why this task re-authors it instead of Task 001):
  - Upstream test "throws a loud error if a ScopedOrderBy is used while no ScopeSortRendererInterface is bound" calls `(new Container())->get(ScopeSortRendererInterface::class)` and expects `NoDriverException`. Marko core's `Container::get()` only produces `NoDriverException` for ids starting with the literal prefix `Marko\\` (see `marko/packages/core/src/Container/Container.php:152`). Our renamed interface starts with `Markommerce\\`, so the Container falls through to `Marko\Core\Exceptions\BindingException::noImplementation()` instead.
  - Rewrite the assertion to `->toThrow(\Marko\Core\Exceptions\BindingException::class)` AND add a second test "NoDriverException::noDriverInstalled() emits a suggestion mentioning markommerce/scope-pgsql" that verifies the renamed `DRIVER_PACKAGES` constant flows into the suggestion text. This preserves the loud-error semantics in our public API while accommodating the upstream Container's prefix check.
  - All other `ModulePhpTest` assertions (bindings/singletons mapping, `PhpScopeRegistry` factory wiring) translate verbatim with the FQCN rename.

## Requirements (Test Descriptions)

- [x] `it copies every Unit test file from marko/scope/tests/Unit/ into packages/scope/tests/Unit/`
- [x] `it copies every Feature test file from marko/scope/tests/Feature/ into packages/scope/tests/Feature/`
- [x] `it has no remaining Marko\\Scope\\ references in packages/scope/tests/` (grep both single-backslash and double-backslash forms; assert zero matches)
- [x] `it passes the full Unit and Feature test suites under composer test` (no scope test depends on a live database or on the pgsql driver classes)
- [x] `it does not regress the scaffolding tests from Task 001`
- [x] `ModulePhpTest expects BindingException (not NoDriverException) when ScopeSortRendererInterface is unbound` (accommodates marko/core Container's hardcoded Marko\\ prefix check)
- [x] `ModulePhpTest verifies NoDriverException::noDriverInstalled() suggestion mentions markommerce/scope-pgsql` (preserves loud-error UX for the driver-install hint)

## Acceptance Criteria

- `./vendor/bin/pest packages/scope/tests` exits 0 under `composer test` (parallel, excluding integration-destructive — no scope test is in that group).
- Every Unit/Feature test file has the `Markommerce\Scope\Tests` namespace (where applicable) and imports map to `Markommerce\Scope\…`.
- No file contains a `Marko\Scope\` reference except inside `Markommerce\Scope\…` (i.e. as a substring of the renamed namespace).
- Total test count under `packages/scope/tests` matches the upstream `marko/scope/tests` count plus the one added "NoDriverException suggestion" test.
- The rewritten `ModulePhpTest` expects `BindingException` for unbound `ScopeSortRendererInterface` access; the suggestion-text test asserts the message contains `markommerce/scope-pgsql`.

## Implementation Notes

- Created `packages/scope/tests/CopyTest.php` as the meta-test file that verifies all 21 test files are present and that no `Marko\Scope\` references remain.
- All 21 upstream test files were copied with `Marko\Scope\` → `Markommerce\Scope\` namespace rewrites; `Marko\Config\`, `Marko\Core\`, and `Marko\Database\` imports left unchanged.
- `ModulePhpTest.php` re-authored with two changes: (1) the NoDriverException test replaced with a BindingException expectation; (2) a new "NoDriverException suggestion" test added that directly calls `NoDriverException::noDriverInstalled()`.
- Feature tests are pure in-memory (use anonymous `ConnectionInterface` implementations) — no database tags needed.
- Total: 154 tests in `packages/scope/tests/`, 381 total passing across all packages.
