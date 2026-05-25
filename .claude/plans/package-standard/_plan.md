# Plan: Package Standard

## Created
2026-05-25

## Status
completed

## Objective
Define the authoritative package-structure standard for markommerce, encode it as automated meta-tests, and bring all 12 existing packages into compliance.

## Related Issues
none

## Discovery Notes

Audited all 12 packages under `packages/`. Findings split into three buckets:

- **Scaffolding drift.** LICENSE missing from 8/12. `.gitattributes` missing from 10/12 (only scope, scope-pgsql have one). `tests/Pest.php` missing from 7/12. `core` is severely under-scaffolded (no README/tests/LICENSE/.gitattributes). `CHANGELOG.md` present only in scope, scope-pgsql but marko upstream uses 0/80 — drop them. `frontend/module.php` and 3 others return `[]`, violating CLAUDE.md/architecture.md guidance that says module.php is *"only created when needed"*.
- **Naming and structure.** `packages/layout/src/Exception/` is singular; the other 11 packages use plural `Exceptions/` — architecture.md spec also says plural. `marko/testing` require-dev and Pest `allow-plugins` config blocks drift.

  **Note on `catalog/Seed/`:** Originally flagged as "drift to be inlined into `src/Seed/`". On verification this is the OPPOSITE of correct — marko's `SeederDiscovery::discoverInVendor` globs `vendor/*/*/Seed` at a fixed depth (see `../marko/packages/database/src/Seed/SeederDiscovery.php:33`). Moving the directory into `src/Seed/` would put it three levels deep and silently break seeder discovery. The `Seed/` top-level directory plus its secondary PSR-4 root in composer.json is the marko-mandated convention for any package that ships seeders. The standard documents this as a recognized exception (task 001), and ComposerJsonShapeTest (task 008) allows it explicitly.
- **resources/ hygiene.** Several stale `.gitkeep` files in non-empty directories. `catalog/package.json` ships CSS without an `exports` map. `frontend-demo/resources/js/.generated/.gitignore` is redundant with the root `.gitignore` pattern.

**Naming-collision decision (from clarification):** keep BOTH meanings of "layout" — Markommerce-layout DSL stays at `<pkg>/layout/` (peer to `config/`), and Latte page-shell templates stay at `<pkg>/resources/views/layout/` because that follows Latte's own `{layout 'foo::layout/base'}` directive convention. The standard doc must explicitly define the two terms.

**Meta-test approach (from clarification):** split per rule under `tests/PackageStandard/` so each failure is self-documenting.

**Affected tests that block cleanup (devil's-advocate re-audit raised the count from 1 to 9):**

- `packages/theme-blank/tests/Unit/ComposerManifestTest.php` — asserts `.gitkeep` files exist in dirs we plan to delete from AND asserts `module.php === []`
- `packages/frontend/tests/Unit/ComposerManifestTest.php` — asserts `module.php` exists and `=== []` (deleted in task 005)
- `packages/frontend-demo/tests/Unit/ComposerManifestTest.php`, `theme-blank-demo/.../ComposerManifestTest.php`, `layout-demo/.../ComposerManifestTest.php` — fully duplicated by new `ComposerJsonShapeTest`
- `packages/layout/tests/Unit/PackageScaffoldingTest.php` — duplicated by `ComposerJsonShapeTest`; contains one unique `.gitignore var/` assertion to preserve
- `packages/catalog/tests/Unit/PackageScaffoldingTest.php` — its `Seed/` PSR-4 assertion is still correct (the `Seed/` root stays per marko convention), but the rest of the file duplicates `ComposerJsonShapeTest`; deleted wholesale in task 009
- `packages/scope/tests/Unit/ReadmeTest.php` — has 3 `it()` blocks that `file_get_contents()` the deleted `CHANGELOG.md`
- `packages/scope-pgsql/tests/Unit/ReadmeTest.php` — has 3 `it()` blocks that `file_get_contents()` the deleted `CHANGELOG.md`

Resolution: tasks 004 and 005 do surgical edits; task 009 deletes the now-fully-redundant files in one pass and is gated on 005 to avoid merge conflicts. (Original task 007 — inlining `catalog/Seed/` into `src/Seed/` — was dropped after verifying marko's seeder discovery contract.)

## Scope

### In Scope
- Author `.claude/package-standard.md` — the authoritative standard with required files per package type, resources/ conventions, naming rules, reference templates (LICENSE, .gitattributes, Pest.php), and the explicit "two meanings of layout" disambiguation.
- Author per-rule meta-tests under `tests/PackageStandard/` (FilePresenceTest, PestPhpTest, NoChangelogTest, EmptyModulePhpTest, ExceptionsNamingTest, ComposerJsonShapeTest, GitkeepHygieneTest, PackageJsonExportsTest, GeneratedDirHygieneTest).
- Bring every existing package into compliance: add missing LICENSE/.gitattributes/README/Pest.php; delete empty module.php files; rename `layout/src/Exception/` → `Exceptions/`; normalize composer.json shape (allowing the `Seed/` second PSR-4 root as a documented exception per marko convention); remove stale .gitkeeps; add catalog/package.json exports; remove redundant .generated/.gitignore; delete per-package CHANGELOGs; delete the obsolete per-package ComposerManifestTest / PackageScaffoldingTest files.

### Out of Scope
- Renaming `resources/views/layout/` (decided: keep, document instead).
- Renaming the markommerce-layout DSL directory.
- Changes to the markommerce-layout discovery logic.
- Per-package CHANGELOG conventions (we're dropping them, not maintaining them).
- Module-level documentation churn — only standard-related doc work in scope.
- Any behavior changes; this is purely structural cleanup.

## Success Criteria
- [ ] `.claude/package-standard.md` exists and is the single source of truth for package layout.
- [ ] All meta-tests under `tests/PackageStandard/` pass on green.
- [ ] All 12 existing packages pass the meta-tests.
- [ ] No stale `.gitkeep` remains in any package.
- [ ] No per-package `CHANGELOG.md` remains.
- [ ] `packages/layout/src/Exceptions/` (plural) exists; `Exception/` (singular) does not; all references updated.
- [ ] `packages/catalog/Seed/` is preserved at the package root (marko's `SeederDiscovery` requires it there); the standard explicitly documents `Seed/` + secondary PSR-4 root as the canonical seeder layout.
- [ ] All previously empty `module.php` files (returning `[]`) are deleted; their packages still load.
- [ ] `composer test`, `phpcs`, and `phpstan analyse` all pass.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Author `.claude/package-standard.md` and reference templates | - | completed |
| 002 | FilePresenceTest + add LICENSE/.gitattributes/README sweep | 001 | completed |
| 003 | PestPhpTest + add missing Pest.php files | 001 | completed |
| 004 | NoChangelogTest + delete scope/scope-pgsql CHANGELOGs | 001 | completed |
| 005 | EmptyModulePhpTest + delete empty module.php files | 001 | completed |
| 006 | ExceptionsNamingTest + rename layout `Exception/` → `Exceptions/` | 001 | completed |
| ~~007~~ | ~~CatalogSeedInlineTest~~ — DROPPED: marko's seeder discovery requires `Seed/` at package root | - | dropped |
| 008 | ComposerJsonShapeTest + normalize composer.json (allows `Seed/` second PSR-4 root) | 001, 003 | completed |
| 009 | GitkeepHygieneTest + remove stale .gitkeeps + delete obsolete per-package scaffold tests | 001, 005 | completed |
| 010 | PackageJsonExportsTest + add catalog CSS exports | 001 | completed |
| 011 | GeneratedDirHygieneTest + delete redundant .generated/.gitignore | 001 | completed |
| 012 | Final verification: run full test suite + phpcs + phpstan | 002-011 | completed |

## Architecture Notes

- Meta-tests live under `tests/PackageStandard/` at the repository root (not per-package). They're Pest tests run by the existing root-level `composer test`.
- Each meta-test follows TDD: written first (RED state, since current packages violate the rule), then the cleanup makes the test pass (GREEN).
- The reference templates (LICENSE text, .gitattributes content, Pest.php skeleton, README placeholder) are embedded in `.claude/package-standard.md` as fenced code blocks. The meta-tests compare each package's file content to those templates where applicable.
- `core` is type `library` (not `marko-module`) — the standard documents which rules apply to library packages vs module packages. Modules require `module.php` only when they have bindings; libraries never have `module.php`.
- The Latte `resources/views/layout/` directory naming is preserved — it follows Latte's `{layout '...'}` directive convention. The standard explicitly documents the "two meanings of layout" so newcomers don't conflate them.
- The `Seed/` top-level directory in `packages/catalog/` (and any future seeder-shipping package) stays where it is. Marko's `SeederDiscovery` globs `vendor/*/*/Seed` at a fixed depth — moving the directory under `src/` would silently break seeder discovery. The standard documents this as the canonical seeder layout, and `ComposerJsonShapeTest` (task 008) allows the secondary PSR-4 root.

## Risks & Mitigations

- **Risk:** Renaming `layout/src/Exception/` → `Exceptions/` touches 21 exception files plus all `use` statements across the layout module and its tests. **Mitigation:** Task 006 is a mechanical rename — implementer does namespace update + import update in one pass. The test asserts no `Markommerce\Layout\Exception\` namespace remains.
- **Risk (averted, retained for posterity):** The original plan called for inlining `catalog/Seed/` into `src/Seed/`. Verification of `marko/database/src/Seed/SeederDiscovery.php:33` showed seeder discovery globs `vendor/*/*/Seed` at fixed depth — moving the directory under `src/` would silently break seeder discovery with no test failure (the seeder simply wouldn't be found). Task 007 was dropped; the standard documents `Seed/` at package root as the canonical layout.
- **Risk:** Deleting empty `module.php` files might break Marko's module discovery if it requires the file's presence. **Mitigation:** Task 005's RED state writes a test that confirms current packages with empty module.php still register correctly; verifies the same after deletion.
- **Risk:** The "stale .gitkeep" rule may false-positive on directories that ARE empty but should exist (e.g., a `config/` dir reserved for future config). **Mitigation:** GitkeepHygieneTest only flags `.gitkeep` siblings of other tracked files; legitimately-empty dirs keep their `.gitkeep`.
- **Risk:** Tasks 002-011 mostly run in parallel; merge conflicts on composer.json or shared files. **Mitigation:** Task 008 is gated on 003 (Pest.php affects allow-plugins reasoning). Other composer.json touches are package-scoped — different packages = no conflict.
- **Risk:** Per-package `composer.json` changes may cascade through self.version dependency resolution. **Mitigation:** Task 012 runs full test suite + composer install to confirm dependency graph still resolves.
- **Risk:** Pest 4 file-scoped `function` declarations (e.g., `function readManifest(...)` in multiple ComposerManifestTest files) are global once loaded. Deleting some but keeping others is fine because each file uses a uniquely named helper (`readManifest`, `readCatalogManifest`, `readLayoutManifest`, etc.). Verified during devil's-advocate review — no two surviving tests redeclare the same helper.
- **Risk:** The Latte page-template renderer might require the `resources/views/layout/.gitkeep` file we are removing if it has been hardcoded into a config. **Mitigation:** Grepped — no such reference exists. The `.gitkeep` files are pure git scaffolding.
- **Risk:** Renaming `packages/layout/tests/Unit/Exception/` to `Exceptions/` could collide with a sibling dir on case-insensitive filesystems if someone re-introduces a `Exception/` test dir later. **Mitigation:** Meta-test in task 006 forbids the singular name under `tests/` too — see updated requirements.
