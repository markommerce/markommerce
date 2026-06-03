# Task 004: Strip scope from config tests; delete FakeScopeRegistry; add ScopeDecouplingTest and ComposerManifestTest

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Sweep through `packages/config/tests/` and remove every `Markommerce\Scope\…` import, scope-aware test case, and fixture artefact. Delete `tests/Fakes/FakeScopeRegistry.php`. Update `tests/Feature/ModulePhpTest.php`'s `bootModuleContainer()` helper to no longer bind `ScopeRegistryInterface`. Update `tests/PackageScaffoldingTest.php` to assert config no longer requires `markommerce/scope`. Add a new `tests/Unit/ScopeDecouplingTest.php` safety-net that walks `packages/config/src/` and asserts no scope coupling remains. Add a new `tests/Unit/ComposerManifestTest.php` that asserts the future Tier-2 packages (`config-scope`, `config-scope-pgsql`, `config-locale`, `config-market`) are NOT in config's require block.

## Context
- Related files:
  - `packages/config/tests/Fakes/FakeScopeRegistry.php` (to be deleted)
  - `packages/config/tests/Feature/ModulePhpTest.php`
  - `packages/config/tests/PackageScaffoldingTest.php`
  - `packages/config/tests/Unit/SecretCipherIntegrationTest.php`
  - `packages/config/tests/Unit/Resolver/ConfigResolverGetTest.php`
  - `packages/config/tests/Unit/Cache/CachingConfigResolverTest.php` (already touched in 002 — final mop-up here)
  - All other files that still carry `use Markommerce\Scope\…` after tasks 001–003 — enumerate via `grep`.
- New files:
  - `packages/config/tests/Unit/ScopeDecouplingTest.php` (mirror of `packages/catalog/tests/Unit/ScopeDecouplingTest.php`).
  - `packages/config/tests/Unit/ComposerManifestTest.php` (mirror of `packages/catalog/tests/Unit/ComposerManifestTest.php`).
- Patterns to follow: P2's `ScopeDecouplingTest` shape. The decoupling test walks every `*.php` under `packages/config/src/` and asserts the FQN and unqualified scope-only symbols are absent from each file's contents.

## Requirements (Test Descriptions)
- [ ] `it does not include packages/config/tests/Fakes/FakeScopeRegistry.php on the filesystem after task completes`
- [ ] `it does not bind ScopeRegistryInterface inside bootModuleContainer in tests/Feature/ModulePhpTest.php`
- [ ] `it asserts packages/config/composer.json does NOT require markommerce/scope (PackageScaffoldingTest case)`
- [ ] `it walks every PHP file under packages/config/src and reports zero occurrences of Markommerce\Scope\ FQN prefix`
- [ ] `it walks every PHP file under packages/config/src and reports zero occurrences of ScopeContext, ScopeSignature, ScopeRegistryInterface, ScopedFieldRegistry, SignatureCandidateEnumerator, OverrideMatcher, or AxisNotDeclaredException identifiers`
- [ ] `it walks every PHP file under packages/config/src and reports zero #[Scoped(] attribute usages`
- [ ] `it asserts packages/config/composer.json does NOT require markommerce/config-scope`
- [ ] `it asserts packages/config/composer.json does NOT require markommerce/config-scope-pgsql`
- [ ] `it asserts packages/config/composer.json does NOT require markommerce/config-locale`
- [ ] `it asserts packages/config/composer.json does NOT require markommerce/config-market`

## Acceptance Criteria
- All requirements have passing tests.
- `grep -rln "Markommerce\\Scope\|ScopeContext\|ScopeSignature\|OverrideMatcher\|AxisNotDeclaredException" packages/config/tests/` returns zero matches.
- `composer test:all` for `packages/config/` passes (subject to other packages still having scope; this task validates config-internal coupling).
- PHPStan level 8 clean for all touched files.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
