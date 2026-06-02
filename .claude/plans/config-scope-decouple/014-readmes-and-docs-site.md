# Task 014: READMEs for the four new packages plus docs-site updates

**Status**: completed
**Depends on**: 007, 011, 012
**Retry count**: 0

## Description
Write production-quality READMEs for `config-scope`, `config-scope-pgsql`, `config-locale`, and `config-market`, following `docs/DOCS-STANDARDS.md`. Update `packages/config/README.md` to remove the `#[Scoped]` example and point to `config-scope` for per-scope overrides. Update `packages/config-pgsql/README.md` to drop the per-scope-overrides mention and the GIN-index note. Add docs-site pages for the four new packages at `docs/src/content/docs/packages/`. Update `docs/src/content/docs/packages/config.md` to mirror the README's descoping. Add a docs-pages safety-net test (mirror P4's `CatalogMarketExtractPagesTest`) asserting all five docs pages exist and contain key anchor sections.

## Context
- Related files (new READMEs):
  - `packages/config-scope/README.md` (full version replacing the placeholder from task 007)
  - `packages/config-scope-pgsql/README.md`
  - `packages/config-locale/README.md`
  - `packages/config-market/README.md`
- Related files (updated READMEs):
  - `packages/config/README.md`
  - `packages/config-pgsql/README.md`
- Related files (new docs pages):
  - `docs/src/content/docs/packages/config-scope.md`
  - `docs/src/content/docs/packages/config-scope-pgsql.md`
  - `docs/src/content/docs/packages/config-locale.md`
  - `docs/src/content/docs/packages/config-market.md`
- Related files (updated docs):
  - `docs/src/content/docs/packages/config.md`
  - `docs/src/content/docs/packages/config-pgsql.md`
- New test:
  - `tests/Unit/Docs/ConfigScopeDecouplePagesTest.php`
- Patterns to follow: `docs/DOCS-STANDARDS.md` (slim README template — H1, blurb, one Installation block, one Quick Example, link to full docs); `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` (assertion shape).

## Requirements (Test Descriptions)
- [ ] `it ships a README.md for config-scope with the package name as H1 and an Installation section`
- [ ] `it ships a README.md for config-scope with a Quick Example block showing #[Scoped(axes: ['locale'])] usage`
- [ ] `it ships a README.md for config-scope-pgsql with the package name as H1 and a Schema section documenting the config_value_overrides table`
- [ ] `it ships a README.md for config-locale documenting the placeholder status and the merchant-#[Scoped] shortcut`
- [ ] `it ships a README.md for config-market documenting the placeholder status`
- [ ] `it removes the #[Scoped] example from packages/config/README.md`
- [ ] `it removes the per-scope override mention from packages/config-pgsql/README.md`
- [ ] `it ships a docs page at docs/src/content/docs/packages/config-scope.md (asserted by ConfigScopeDecouplePagesTest)`
- [ ] `it ships a docs page at docs/src/content/docs/packages/config-scope-pgsql.md (asserted by ConfigScopeDecouplePagesTest)`
- [ ] `it ships a docs page at docs/src/content/docs/packages/config-locale.md (asserted by ConfigScopeDecouplePagesTest)`
- [ ] `it ships a docs page at docs/src/content/docs/packages/config-market.md (asserted by ConfigScopeDecouplePagesTest)`
- [ ] `it updates docs/src/content/docs/packages/config.md to remove scope-aware examples (asserted by ConfigScopeDecouplePagesTest)`

## Acceptance Criteria
- All requirements have passing tests.
- Each new README passes its package's `ReadmeTest` (which the scaffolding task ships).
- The docs-pages safety-net test mirrors `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` and runs in the main suite.
- PHP-CS-Fixer and PHPCS clean on README-adjacent test files.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
