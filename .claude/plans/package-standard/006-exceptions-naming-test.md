# Task 006: ExceptionsNamingTest + Rename layout `Exception/` → `Exceptions/`

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/ExceptionsNamingTest.php` asserting that no `packages/*/src/Exception/` (singular) directory exists — only `Exceptions/` (plural). Rename `packages/layout/src/Exception/` to `packages/layout/src/Exceptions/`, update every namespace declaration in the 21 exception files, update every `use Markommerce\Layout\Exception\…` reference across the codebase (~50 files), and update `@throws` PHPDoc references. Also rename the matching test directory `packages/layout/tests/Unit/Exception/` to `tests/Unit/Exceptions/` for consistency (Pest 4 picks tests up by path glob, not namespace — verify after rename that both test files still run).

## Context

Initial RED state — `packages/layout/src/Exception/` exists with 21 files using the singular namespace `Markommerce\Layout\Exception\`. Architecture.md line 19 documents the plural form as canonical.

A full grep run before drafting this task showed references in 50 PHP files (production + tests). The most important non-obvious hits:

```bash
grep -rln 'Markommerce\\Layout\\Exception' packages/
```

- `packages/layout/src/Exception/*.php` — 21 namespace declarations + the dir itself
- `packages/layout/src/Discovery/LayoutDiscovery.php` (1 use)
- `packages/layout/src/Compiler/{Compiler,CompilerInterface,DecoratorTemplateValidator,ResolutionPhase,ValidationPhase}.php` (many uses)
- `packages/layout/src/Cache/PreparedTreeBuilder.php` (2 uses)
- `packages/layout/src/Command/CompileCommand.php` (1 use)
- `packages/layout/src/Middleware/{CompileIfStaleMiddleware,MarkommerceLayoutMiddleware}.php`
- `packages/layout/src/Runtime/{SourceResolver,TreeMerger}.php`
- `packages/layout/src/{ExtensibleData,ExtensionBag}.php` — `ExtensibleData.php` has a `@throws \Markommerce\Layout\Exception\DuplicateExtensionException` PHPDoc
- 16 test files including `packages/layout/tests/Unit/Exception/{ExceptionCatalogTest,HandleExceptionCatalogTest}.php` and several `ResolutionPhaseTest.php` body-string toThrow calls (`->toThrow(\Markommerce\Layout\Exception\...::class)`)

Watch outs:
- Body-text `toThrow(\Markommerce\Layout\Exception\...::class)` calls (not just `use` imports) — these are in `ResolutionPhaseTest.php`, `ExtensibleDataTest.php`, `IterationDecoratorTest.php`. A pure `use`-statement rewrite misses these.
- PHPDoc `@throws \Markommerce\Layout\Exception\...` lines — these need the same rename even though PHPStan may not flag them.
- The cached layout artifact at `var/cache/markommerce/layouts.php` does NOT reference exception classes (verified by grep). No cache invalidation needed for this task — but if you regenerate the cache during testing, it must still resolve.
- `packages/layout/composer.json` PSR-4 already maps `Markommerce\Layout\\` to `src/` so the new path resolves automatically after `composer dump-autoload`.

- Related files: all 21 files in `packages/layout/src/Exception/`, every consumer, and the test directory `packages/layout/tests/Unit/Exception/`
- Patterns to follow: 11 other packages already use plural `Exceptions/`

## Requirements (Test Descriptions)

- [ ] `it asserts no packages/*/src/Exception directory exists (only Exceptions plural)`
- [ ] `it asserts the layout package has packages/layout/src/Exceptions directory`
- [ ] `it asserts no PHP file under packages/ contains the string Markommerce\Layout\Exception\ (singular namespace) — covers both use-statements and inline FQCN strings`
- [ ] `it asserts the layout package's tests still pass after the rename`

## Acceptance Criteria
- `tests/PackageStandard/ExceptionsNamingTest.php` exists and follows Pest 4 syntax
- `packages/layout/src/Exception/` directory no longer exists
- `packages/layout/src/Exceptions/` directory contains all 21 exception files with updated namespaces
- All `use Markommerce\Layout\Exception\…` statements throughout the codebase are updated to `…\Exceptions\…`
- All `@throws \Markommerce\Layout\Exception\…` docblocks are updated
- All inline FQCN strings like `->toThrow(\Markommerce\Layout\Exception\…::class)` are updated
- `packages/layout/tests/Unit/Exception/` directory is renamed to `tests/Unit/Exceptions/` and both test files (`ExceptionCatalogTest.php`, `HandleExceptionCatalogTest.php`) still execute
- `composer dump-autoload` runs cleanly
- `composer test` passes (layout module tests in particular)
- `phpstan analyse` passes
- No regressions in other tests

## Implementation Notes
(Left blank — filled in by programmer during implementation)
