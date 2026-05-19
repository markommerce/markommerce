# Task 001: Bootstrap markommerce/scope Package Skeleton

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the `markommerce/scope` package directory with composer.json, LICENSE, .gitattributes, README.md, module.php, and `tests/Pest.php` plus two scaffolding/sentinel tests (`PackageScaffoldingTest`, `Unit/ReadmeTest`). All composer/PSR-4/README references use the new `markommerce/scope` package name and `Markommerce\Scope\` namespace. The package's `extra.marko.module` flag stays true so marko/core's module discovery picks it up. (`Unit/ModulePhpTest` is intentionally NOT authored here — it's authored in Task 003 alongside the rest of the test tree because one assertion has to be rewritten to accommodate the marko/core Container's `Marko\\` prefix check. See Task 003 context.)

## Context

- Source for diffing: `/home/michal/www/marko/marko/packages/scope/composer.json`, `module.php`, `README.md`, `LICENSE`, `.gitattributes`, `tests/Pest.php`, `tests/PackageScaffoldingTest.php`, `tests/Unit/ReadmeTest.php`.
- Target paths: `packages/scope/composer.json`, `packages/scope/LICENSE`, `packages/scope/.gitattributes`, `packages/scope/README.md`, `packages/scope/module.php`, `packages/scope/tests/Pest.php`, `packages/scope/tests/PackageScaffoldingTest.php`, `packages/scope/tests/Unit/ReadmeTest.php`.
- The module.php at this point references FQCNs (`Markommerce\Scope\Context\ScopeContext`, etc.) that don't yet exist — that's fine, the scaffolding test only requires `module.php` and checks the returned array shape, not class autoloading.
- The `marko/scope` README mentions BOTH `scope-mysql` and `scope-pgsql` install commands and the ReadmeTest enforces both — we drop scope-mysql entirely. Specifically the upstream sentinel `it('references driver packages in the installation instructions', …)` asserts `toContain('scope-mysql')` AND `toContain('scope-pgsql')`. In our fork it asserts ONLY `toContain('markommerce/scope-pgsql')` and explicitly forbids `scope-mysql` (use `->not->toContain('scope-mysql')`).
- composer.json `require`: `php: ^8.5`, `marko/core: self.version`, `marko/config: self.version`, `marko/database: self.version`. `require-dev`: `pestphp/pest: ^4.0`. autoload PSR-4: `Markommerce\\Scope\\` → `src/`. autoload-dev PSR-4: `Markommerce\\Scope\\Tests\\` → `tests/`. extra.marko.module: true.
- module.php (initial form): mirror the marko/scope module.php structure but with `Markommerce\Scope\…` FQCNs. Bindings: `ScopeRegistryInterface` → closure returning `PhpScopeRegistry`. Singletons: `ScopeContext`, `ScopeMetadataFactory`, `ScopeResolver`, `ScopedOrderByFactory`, `ScopeWalker`.
- `PackageScaffoldingTest` upstream asserts the literal autoload keys `'Marko\\Scope\\'` and `'Marko\\Scope\\Tests\\'`. Our copy asserts `'Markommerce\\Scope\\'` and `'Markommerce\\Scope\\Tests\\'` — note the DOUBLE backslashes match the JSON encoding of single backslashes in `composer.json`.
- `Pest.php` in upstream `marko/scope/tests/Pest.php` is an empty skeleton (only banner comments). Copy it verbatim — Pest needs the file to exist for autoload-dev discovery; the empty body is intentional.
- `LICENSE` is MIT (copy verbatim from `/home/michal/www/marko/marko/packages/scope/LICENSE`).

## Requirements (Test Descriptions)

- [x] `it has a valid composer.json with name markommerce/scope and extra.marko.module true`
- [x] `it requires PHP ^8.5, marko/core, marko/config, and marko/database in composer.json`
- [x] `it has no version field in composer.json`
- [x] `it autoloads PSR-4 namespace Markommerce\Scope\ from packages/scope/src/` (asserts the JSON-encoded key `Markommerce\\Scope\\`)
- [x] `it autoloads PSR-4 test namespace Markommerce\Scope\Tests\ from packages/scope/tests/` (asserts the JSON-encoded key `Markommerce\\Scope\\Tests\\`)
- [x] `it has a module.php returning an array with bindings and singletons keys`
- [x] `it has a README.md with title # markommerce/scope and a single driver install line for markommerce/scope-pgsql`
- [x] `it has no reference to scope-mysql in the README` (drops the upstream both-driver list — explicit `->not->toContain('scope-mysql')`)
- [x] `it has a Documentation section in the README linking to the markommerce docs site`

## Acceptance Criteria

- All scaffolding/sentinel tests pass (`./vendor/bin/pest packages/scope/tests/PackageScaffoldingTest.php` and `tests/Unit/ReadmeTest.php`).
- `packages/scope/composer.json` contains no leftover `marko/scope` reference in any field.
- `packages/scope/README.md` does not mention `scope-mysql` anywhere.
- `packages/scope/module.php` references only `Markommerce\Scope\…` FQCNs in its bindings and singletons.
- `.gitattributes` mirrors marko/scope's export-ignore list (tests, .github, .gitattributes, .gitignore, phpunit.xml.dist).
- File follows project standards (`declare(strict_types=1);`, no `final`, etc.).

## Implementation Notes
- Created `packages/scope/` directory with full skeleton: `composer.json`, `module.php`, `README.md`, `LICENSE`, `.gitattributes`, `tests/Pest.php`, `tests/PackageScaffoldingTest.php`, `tests/Unit/ReadmeTest.php`, and empty `src/` directory.
- `composer.json` uses `markommerce/scope` name and `Markommerce\\Scope\\` PSR-4 namespace; no `version` field; `extra.marko.module: true`.
- `module.php` mirrors marko/scope structure with all FQCNs replaced from `Marko\Scope\…` to `Markommerce\Scope\…`; returns `bindings` and `singletons` keys.
- `README.md` uses `# markommerce/scope` title, references only `markommerce/scope-pgsql` (no `scope-mysql`), and includes `## Documentation` section.
- All 9 tests pass; full suite of 223 tests remains green.
