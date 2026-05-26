# Task 001: Drop `locale` from scope's default config

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Remove the `locale` axis entry from `packages/scope/config/scope.php` and update every scope test that asserted on the default axis list. The `locale` axis will be contributed by the new `markommerce/locale` package (task 002), so scope itself must stop shipping it. `market` and `channel` axes remain in scope's defaults for now (P4 / future phases own those).

This task also relocates one scope test that improperly cross-references catalog (`CatalogMetadataIntegrationTest.php`) — its concern moves into the Tier 2 integration test in task 011 and the file is deleted.

## Context
- Related files:
  - `packages/scope/config/scope.php` — the default axes config file
  - `packages/scope/tests/Unit/Config/DefaultAxesConfigTest.php` — asserts `locale, market, channel` axes exist
  - `packages/scope/tests/Unit/ModulePhpTest.php` — boots scope module and checks axis registry
  - `packages/scope/tests/Unit/ModulePhpPipelineTest.php` — same
  - `packages/scope/tests/Feature/DefaultScopeResolutionTest.php` — uses default config including `locale`
  - `packages/scope/tests/Feature/CatalogMetadataIntegrationTest.php` — wrongly references `Markommerce\Catalog\Entity\Product`; delete this file (assertions relocate to task 011)
- Patterns to follow: existing scope test setup with `makeConfigStubForDefaultAxes()` helper

## Requirements (Test Descriptions)
- [ ] `it declares only market and channel axes in scope's default config (no locale)`
- [ ] `it gives the market axis a single scope named default set as its default`
- [ ] `it gives the channel axis a single scope named web set as its default`
- [ ] `it is accepted by PhpScopeRegistry and lists only [market, channel] axes from scope's default config`
- [ ] `it boots scope with no locale axis present in PhpScopeRegistry after the scope module's boot closure runs`
- [ ] `it removes the obsolete CatalogMetadataIntegrationTest file that cross-referenced catalog entities from scope tests`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/scope/config/scope.php` no longer contains the `locale` axis key.
- `packages/scope/tests/Feature/CatalogMetadataIntegrationTest.php` is deleted.
- All remaining scope tests pass against the new default config.
- Code follows project standards (`declare(strict_types=1)`, no final, etc).

## Implementation Notes
(Left blank — filled in by programmer during implementation)
