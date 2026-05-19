# Task 007: Wire Packages Into Root composer.json + Full Verification

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Add `markommerce/scope` and `markommerce/scope-pgsql` to the root project `composer.json` `require` block at `self.version`, run a scoped `composer update`, then run the full markommerce verification suite inside Docker: `composer test`, `phpcs`, `phpstan analyse`, `php-cs-fixer fix --dry-run`. Every command must exit 0. Any lint/static-analysis violations introduced by the rename are fixed in this task before declaring it complete.

**Self-containment invariant**: at the end of this task, the markommerce/scope and markommerce/scope-pgsql packages must function exactly the same as the originals AND be entirely independent of `marko/scope` and `marko/scope-pgsql`. The originals must not be installed, must not be referenced in any composer.json, must not appear in `composer.lock`, and must not be imported by any PHP file in our packages. This task includes explicit guards that prove that invariant holds.

## Context

- Target file: `/home/michal/www/marko/markommerce/composer.json`. Insert into `"require"` (alphabetically sorted is preferred — there's a `"sort-packages": true` config that will normalize on next composer command).
- Use `self.version` to mirror sibling packages (`markommerce/core: self.version`).
- Run `docker compose -f ~/www/marko/compose.yaml exec -w /workspace/markommerce app composer update markommerce/scope markommerce/scope-pgsql --no-progress` (scoped update) — then `composer install` to refresh autoloaders.
- Verification commands (all inside `marko-playground-app` container):
  - `composer test` (parallel, excludes integration-destructive — covers every test in this fork since nothing is tagged destructive)
  - `composer test:all` (parallel, no exclusion — same set as `composer test` for our packages; run anyway to confirm sibling-package destructive tests still pass and nothing in our packages accidentally got the destructive tag)
  - `./vendor/bin/phpcs` (Markommerce phpcs.xml ruleset against `packages/`)
  - `./vendor/bin/phpstan analyse` (level 8 against new packages)
  - `./vendor/bin/php-cs-fixer fix --dry-run --diff` (read-only check; if fails, run `./vendor/bin/php-cs-fixer fix` to auto-apply, then re-run the dry-run to confirm)
- Grep guards — MUST CHECK BOTH FORMS:
  - Single-backslash (PHP source, namespace/use statements): `grep -rn 'Marko\\Scope\\' packages/scope packages/scope-pgsql --include='*.php'` must return zero matches.
  - Double-backslash (string literals, JSON encoding, PHP-array keys): `grep -rn 'Marko\\\\Scope\\\\' packages/scope packages/scope-pgsql` must return zero matches.
  - Composer JSON keys: `python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print(list(d.get('autoload',{}).get('psr-4',{}).keys()) + list(d.get('autoload-dev',{}).get('psr-4',{}).keys()))" packages/scope/composer.json` (and same for `scope-pgsql`) must show only `Markommerce\\Scope\\` / `Markommerce\\Scope\\Tests\\` / `Markommerce\\Scope\\PgSql\\` / `Markommerce\\Scope\\PgSql\\Tests\\`.
- Reverse grep: confirm that legitimate `Marko\\Config\\`, `Marko\\Core\\`, `Marko\\Database\\` imports are still present (sanity check that we didn't over-rewrite).
- Also assert no string literal `marko/scope-mysql` or `marko/scope-pgsql` remains anywhere in `packages/scope` or `packages/scope-pgsql` (catches the `NoDriverException::DRIVER_PACKAGES` literal from Task 002 if it slips through, and any README/docstring leftovers).
- If `composer test:all` fails, capture the failure mode and surface it — but no test in our packages is tagged destructive, so this should match the `composer test` result exactly.
- **Self-containment guards (must all pass):**
  - `composer show marko/scope` MUST exit non-zero (package not installed). Same for `composer show marko/scope-pgsql`. If either is installed, something — a sibling package, a test fixture, or root require — is pulling it in transitively, which violates the self-containment invariant.
  - `grep -E '"marko/scope(-pgsql)?"' composer.json packages/*/composer.json` MUST return zero matches. (The `../marko/packages/*` path repository is allowed to stay — that's for `marko/core`/`config`/`database` — but no composer.json may *require* `marko/scope*`.)
  - `grep -E '"marko/scope(-pgsql)?"' composer.lock` MUST return zero matches inside the `"packages"` and `"packages-dev"` arrays (use `python3 -c "import json; d=json.load(open('composer.lock')); names=[p['name'] for p in d['packages']+d['packages-dev']]; assert 'marko/scope' not in names and 'marko/scope-pgsql' not in names, names"` for a precise structural check).
  - As a final smoke test: temporarily rename `/home/michal/www/marko/marko/packages/scope` and `/home/michal/www/marko/marko/packages/scope-pgsql` aside (`mv … …-fork-test-aside`), run `composer install` followed by `composer test`, confirm both still exit 0, then restore the directories. This is the strongest possible proof that the new packages don't depend on the originals existing on disk. If you can't safely rename the marko directories in your environment, skip this final smoke test and rely on the composer-level guards above — but flag the skip in the implementation notes.

## Requirements (Test Descriptions)

- [ ] `it adds markommerce/scope to the root composer.json require block`
- [ ] `it adds markommerce/scope-pgsql to the root composer.json require block`
- [ ] `it runs composer test green` (covers all Unit + Feature tests for both packages)
- [ ] `it runs composer test:all green` (same set as composer test for our packages — nothing in scope/scope-pgsql is tagged destructive)
- [ ] `it runs phpcs against the new packages with zero violations`
- [ ] `it runs phpstan analyse at level 8 against the new packages with zero errors`
- [ ] `it runs php-cs-fixer in dry-run mode with no diff against the new packages`
- [ ] `it finds zero single-backslash Marko\Scope\ references in PHP source under packages/scope/ or packages/scope-pgsql/`
- [ ] `it finds zero double-backslash Marko\\Scope\\ references (string literals / JSON keys) under packages/scope/ or packages/scope-pgsql/`
- [ ] `it finds zero references to marko/scope-mysql or marko/scope-pgsql package-name strings anywhere in packages/scope/ or packages/scope-pgsql/`
- [ ] `it preserves Marko\Config\, Marko\Core\, Marko\Database\ imports across the new packages` (the count should match the upstream marko/scope* count after the rename)
- [ ] `composer show marko/scope and composer show marko/scope-pgsql both exit non-zero` (the originals are not installed alongside our fork)
- [ ] `no composer.json under the project root or packages/* requires marko/scope or marko/scope-pgsql`
- [ ] `composer.lock contains neither marko/scope nor marko/scope-pgsql in packages or packages-dev`
- [ ] `composer test still exits 0 after marko/packages/scope and marko/packages/scope-pgsql are temporarily renamed aside` (the strongest self-containment proof; mark as skipped in implementation notes only if the rename can't be performed safely)

## Acceptance Criteria

- Root `composer.json` lists both new packages in `require`.
- `composer.lock` is updated and committed, and contains neither `marko/scope` nor `marko/scope-pgsql`.
- `composer test`, `composer test:all`, `phpcs`, `phpstan analyse`, and `php-cs-fixer fix --dry-run` all exit 0 inside Docker.
- Both backslash-form negative greps and the `marko/scope-*` package-name grep all return zero matches.
- `composer show marko/scope` and `composer show marko/scope-pgsql` both exit non-zero.
- The marko-directories-aside smoke test passes (or the skip is justified in implementation notes).
- Any auto-fixable lint issues introduced by the copy are fixed in this task (re-run `php-cs-fixer fix` if needed, then re-verify).

## Implementation Notes
(Left blank — filled in during implementation)
