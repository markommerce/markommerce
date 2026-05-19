# Task 002: Copy & Rename markommerce/scope Source Tree

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Copy every `src/**/*.php` from `marko/packages/scope/src/` into `markommerce/packages/scope/src/`, rewriting the `namespace Marko\Scope\…;` declaration and every `use Marko\Scope\…;` import to `Markommerce\Scope\…`. Every other namespace (`Marko\Config\`, `Marko\Core\`, `Marko\Database\`, `Marko\Database\PgSql\`) is left untouched. Class bodies, method signatures, attributes, and PHPDoc strings are otherwise preserved verbatim. Each file keeps its `declare(strict_types=1);`. No class is made `final`. The rename is mechanical — no behavior changes, no refactors.

## Context

- Source files to copy (all under `/home/michal/www/marko/marko/packages/scope/src/`):
  - `Scope.php`
  - `Attributes/Scoped.php`
  - `Axis/ScopeAxis.php`
  - `Context/ScopeContext.php`
  - `Exceptions/NoDriverException.php`, `ScopeConfigurationException.php`, `ScopeContextException.php`, `ScopeStorageException.php`, `UnknownAxisException.php`, `UnknownScopeException.php`
  - `Hierarchy/ScopeHierarchy.php`
  - `Metadata/ScopeMetadata.php`, `ScopeMetadataFactory.php`
  - `Query/ScopedOrderBy.php`, `ScopedOrderByFactory.php`, `ScopeSortExpression.php`, `ScopeSortRendererInterface.php`
  - `Registry/PhpScopeRegistry.php`, `ScopeRegistryInterface.php`
  - `Resolution/ScopeWalker.php`, `ScopeWalkResult.php`
  - `Resolver/ScopeResolver.php`
  - `Storage/HasScopes.php`, `HasScopesInterface.php`, `ScopedDataSerializer.php`
  - `Validation/ScopedEntityValidator.php`
- Target structure mirrors source: same subdirectories under `packages/scope/src/`.
- Rename rules:
  - `namespace Marko\Scope` → `namespace Markommerce\Scope`
  - `use Marko\Scope\` → `use Markommerce\Scope\`
  - FQCN strings in `@param`, `@return`, `@throws` docblocks that reference `Marko\Scope\…` → `Markommerce\Scope\…`
  - Any string literals that contain `Marko\\Scope\\` (escaped FQCNs, e.g. in exception messages) → `Markommerce\\Scope\\`
  - Leave alone: `use Marko\Config\…`, `use Marko\Core\…`, `use Marko\Database\…`, `use Marko\Database\PgSql\…` and their corresponding docblock/string references.
- **`src/Exceptions/NoDriverException.php` carries a `private const array DRIVER_PACKAGES = ['marko/scope-mysql'];` literal that is NOT a namespace rewrite.** The mechanical-rename script will miss it. Replace it with `private const array DRIVER_PACKAGES = ['markommerce/scope-pgsql'];` so the loud-error `suggestion` advises users to install our actual driver. (The upstream array contains only mysql because mysql was the older driver listed first; after fork, list only the pgsql driver Markommerce actually ships.) Note: the upstream class is `\Marko\Scope\Exceptions\NoDriverException` extending `Marko\Core\Exceptions\MarkoException` — the parent stays untouched.
- The `HasScopes` "trait" file at `src/Storage/HasScopes.php` IS a PHP trait (verified — `trait HasScopes { … }` at line 20). CLAUDE.md's project rule "No traits" is a coding standard for new Markommerce code; phpcs.xml does not enforce it. Keep the trait as-is in this task — converting it would break the public API (consumers do `use HasScopes;` per the README quick example). The trait's `$scopes` jsonb column declaration and method set are part of the contract the fork must preserve verbatim.
- All copies preserve `declare(strict_types=1);` and never add `final`.

## Requirements (Test Descriptions)

- [x] `it autoloads every Markommerce\Scope\ class without a fatal error` (a single test that calls `class_exists()` on each FQCN listed in this task's Context)
- [x] `it has no remaining Marko\\Scope\\ references in packages/scope/src/` (greps the directory and asserts zero matches — checks BOTH single-backslash and double-backslash forms)
- [x] `it preserves all Marko\Config\, Marko\Core\, Marko\Database\ imports unchanged` (compares the count of these imports before and after — should match the upstream marko/scope count)
- [x] `it keeps declare(strict_types=1) at the top of every src file`
- [x] `it does not introduce a final class in any src file`
- [x] `NoDriverException::DRIVER_PACKAGES contains markommerce/scope-pgsql and does not contain marko/scope-mysql or marko/scope-pgsql` (assert against the const value, since this string drives the loud-error suggestion text)
- [x] `NoDriverException::noDriverInstalled()->getSuggestion() mentions markommerce/scope-pgsql` (verify the loud-error guidance reaches the user)

## Acceptance Criteria

- All target files exist at the expected paths.
- `composer dump-autoload` from the project root succeeds (autoloader is consistent).
- `./vendor/bin/pest packages/scope/tests` is not regressed by this task (scaffolding tests from 001 keep passing — most other tests get added in 003).
- The autoload test above passes.
- `NoDriverException` lists `markommerce/scope-pgsql` (and only that) in `DRIVER_PACKAGES`; no `marko/scope-mysql` or `marko/scope-pgsql` strings remain anywhere in `packages/scope/src/`.
- No `final` class anywhere.

## Implementation Notes

- Created all 25 source files under `packages/scope/src/` with namespace `Marko\Scope` → `Markommerce\Scope` applied to namespace declarations and `use` imports.
- All upstream non-Scope namespaces (`Marko\Config\`, `Marko\Core\`, `Marko\Database\`) are preserved untouched.
- `NoDriverException::DRIVER_PACKAGES` updated to `['markommerce/scope-pgsql']`.
- `HasScopes` trait kept as-is (not converted) per task instructions.
- Added `markommerce/scope` to root `composer.json` `require-dev` so its PSR-4 autoload entries are picked up by the monorepo autoloader.
- Test file: `packages/scope/tests/Unit/SourceTree/SourceTreeTest.php` covers all 7 requirements.
- The upstream path for the import-count comparison uses `/workspace/marko/packages/scope/src` (the Docker container path).
