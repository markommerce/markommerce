# Devil's Advocate Review: docs-foundation

## Critical (Must fix before building)

### C1. `doc-updater` will re-trigger infinitely on its own changes (Task 003 / 004)

The orchestrator's Step 4a obtains the changed-file list via `git add -A && git diff --name-only --cached && git reset HEAD`, then passes that list to `doc-updater`. When this very plan runs, the changed files include `docs/DOCS-STANDARDS.md`, `docs/src/content/docs/**`, `.claude/agents/doc-updater.md`, and `.claude/pipeline.md`. Without an explicit "no source code changed" early-exit, the agent might still attempt updates against the new content tree (it just got the tree). Worse, on a future plan, if any task touches `.claude/`, `docs/`, or root tests, the agent has no clear guidance to no-op.

The Marko `doc-updater.md` source only mentions "Skip files that are test-only, internal refactors, or non-package code" in Step 1. That language is too vague: the agent could interpret a docs-only change as a "new package" trigger or get confused.

**Fix:** Task 003 must explicitly add a "Skip non-package paths" rule to the agent body: when **every** changed file is outside `packages/*/src/`, `packages/*/README.md`, or `packages/*/composer.json`, the agent outputs `DOCS_CURRENT` immediately. Also make this the first rule in the Process section, not buried in Rules.

### C2. Test for `doc-updater` agent uses Symfony YAML that is not installed (Task 003)

Task 003 acceptance criteria states tests "parse the YAML frontmatter with `Symfony\Component\Yaml\Yaml` if available, otherwise use line-based regex". `composer.json` does NOT include `symfony/yaml`, and the project has not requested it as a new dependency. The "if available" fallback to regex will be the *only* path taken — making the "if available" clause dead text that confuses implementation.

**Fix:** Drop the Symfony YAML mention. Use line-based string assertions (the file is short, frontmatter format is fixed). Either:
- pin tests to literal substring matches like `expect($content)->toContain("name: doc-updater")`, or
- adopt a tiny PHP regex parser inline.

No new composer dependency is needed for this plan.

### C3. Task 001 substitution table is wrong about `marko/cli` (Task 001)

Task 001 says: "`composer global require marko/cli` → keep as is" and "markommerce uses Marko's CLI". This may be true *eventually*, but there is currently no decision on record that markommerce ships with `marko/cli`. More importantly, the **DOCS-STANDARDS.md** is *prescriptive* — it tells contributors what CLI examples must look like. If markommerce does NOT ship `marko/cli` as a default install dep, the standard becomes a lie.

Until the markommerce CLI story is decided, the safest port is to **remove the `marko` vs `php marko` rule entirely from markommerce's DOCS-STANDARDS.md**, OR rewrite it neutrally: "Use the CLI command that the markommerce installation guide documents". Otherwise contributors will write `marko foo` in commerce docs against a CLI that doesn't exist here.

**Fix:** Update Task 001's adaptation table: instead of "keep as is", say "remove the CLI Commands subsection — restore in a follow-up once markommerce's CLI story is decided". Update DOCS-STANDARDS.md's success criteria so its line count target reflects this small reduction (drop the "at least 200 lines" hard floor — use "at least 180 lines" or remove the floor entirely).

### C4. `index.mdx` imports `@astrojs/starlight/components` but no resolver exists; the test must not enforce that it parses (Task 002)

Without the Astro app, `import { Card, CardGrid } from '@astrojs/starlight/components';` is a dangling import that no tool can resolve. That is acceptable for content-only delivery, but the plan must:
1. Confirm no PHP test attempts to interpret the MDX import (it doesn't — current tests are substring checks, good).
2. Confirm the file is **not** parsed by phpstan / phpcs / php-cs-fixer (those only walk `*.php`, so this is fine).
3. Add a forward-compatibility note: when the Astro app eventually lands, this is the file that has to be valid. If we want the file to be lint-clean immediately, we should NOT include the `import` line and instead write the index page WITHOUT components (plain markdown splash).

Decision: keep the import — that's what Starlight needs once the app lands, and content tests pass either way. But explicitly call out the tradeoff in Task 002 so a worker doesn't "fix" the dangling import.

**Fix:** Task 002 must add an explicit "Implementation note" that the `import { Card, CardGrid }` line is intentional and should NOT be removed even though no resolver exists yet. Also remove `LinkCard` from the import list — Marko's source uses `Card, CardGrid, LinkCard`, but the markommerce splash body in the plan only uses `Card, CardGrid`. Importing an unused identifier will trigger a Starlight warning when the app lands.

### C5. Task 003 description of `doc-updater` invocation contract is missing (Task 003 / 004)

The orchestrator (`SKILL.md` lines 184-189) treats `doc-updater` as **single mode**: "Pass the plan name, changed files list, and any relevant project context." But the agent body in Marko reads "Read the file list provided in your prompt." There is no documented schema for that file list (one path per line? JSON? with diff markers?). When task 003 ports the agent, the agent body must explicitly state the input format the orchestrator delivers, or workers will not be able to test the agent's behavior.

**Fix:** Add to Task 003 requirements that the agent body explicitly states: "The orchestrator passes a newline-separated list of file paths (project-relative). Each line is one file. No diff content is included." This makes the contract explicit and testable.

## Important (Should fix before building)

### I1. Task 003 depends on Task 001 — but really depends on Task 002 too

Task 003's agent uses `@docs/DOCS-STANDARDS.md` (depends on 001) and references `docs/src/content/docs/packages/{name}.md` paths. If the agent tries to GLOB that directory during its first real run and the directory does not exist, behavior is undefined. Task 002 creates the directory. Although these tasks can be implemented in parallel (no shared files), wiring depends on 002 existing before the pipeline activates.

**Fix:** Add Task 002 as a dependency of Task 003 (or at least of Task 004). Without it, `doc-updater` could be activated before its target tree exists. Cheapest fix: Task 004 depends on both 002 and 003.

### I2. The "first run" risk for the agent is not tested

Plan's Risk section acknowledges "First few runs after this plan will need human review to calibrate", but there is no concrete validation step. Specifically: when this very plan completes, `doc-updater` runs as part of Step 4a. There is no requirement in any task verifying that the agent reasonably no-ops on this plan's own changes.

**Fix:** Add a requirement to Task 003 testing: "the agent's Process section explicitly handles the case where no `packages/*/src/` files changed — it MUST output `DOCS_CURRENT` without scanning or editing." Pair this with C1's instructional fix.

### I3. `CLAUDE.md` Documentation section will end up between two "Detailed Configuration" siblings (Task 001)

`CLAUDE.md` ends with the "Detailed Configuration" section, which is itself a bullet list referencing `.claude/*` files. Task 001 says to "append a Documentation section after the existing Detailed Configuration section" — that's fine structurally, but the bullet list under "Detailed Configuration" should also gain a row for `docs/DOCS-STANDARDS.md` so contributors discover the standards from the same index. Without that, the new section is isolated.

**Fix:** Task 001 should also append a bullet under "Detailed Configuration" pointing to `docs/DOCS-STANDARDS.md`. Worded: "- `../docs/DOCS-STANDARDS.md` — Content standards for the docs site and package READMEs" (or similar — note that path is project-relative, not `.claude/`-relative; keep the path correct).

### I4. Tests at `tests/Unit/Docs/*` and `tests/Unit/Agents/*` need bootstrap parity (Task 001 / 002 / 003 / 004)

`phpunit.xml` includes the root `tests/` directory in the "Monorepo" suite. The current `tests/` only contains a `.gitkeep`. Pest 4 needs a `Pest.php` or `TestCase.php` bootstrap to apply project rules. The Marko playground may already have one — but `markommerce` is a fresh project. If there's no `tests/Pest.php`, the new test files will run as plain PHPUnit-on-Pest with no shared helpers. That's actually fine for these existence/content tests (no fixtures needed), but linters/PHPStan might still complain.

**Fix:** Add an explicit acceptance criterion in Task 001 (the first task that creates root-level tests) that if no `tests/Pest.php` bootstrap exists, the worker creates a minimal one. Document the bootstrap requirement so workers don't independently solve it differently in 002, 003, 004. Alternative: confirm Pest 4 works without `Pest.php` and explicitly say so.

### I5. `.gitkeep` files vs Starlight collection schema (Task 002)

When the Astro app lands and a Starlight content collection schema is configured (`content.config.ts` with Zod schemas), Starlight will scan every file in `docs/src/content/docs/` and try to validate frontmatter. `.gitkeep` files are empty and have no frontmatter → Starlight will either:
- skip them if `.md`/`.mdx` extension filter is set (most likely — Starlight is extension-aware), OR
- error out.

This is a tomorrow problem (the Astro app isn't in this plan), but worth noting. Empirically, Starlight ignores non-MD/MDX files in content directories, so `.gitkeep` is safe.

**Fix:** Add a brief note in Task 002's Implementation Notes section: "Starlight ignores non-`.md`/`.mdx` files in content collections, so `.gitkeep` is safe. When the Astro app lands, the placeholder approach can be revisited if seed pages exist."

### I6. Task 004 verifies the bullet exists but not that it's wired correctly (Task 004)

The test asserts `- doc-updater` appears after `## post-implementation` heading. But if a future edit moves the `## post-implementation` heading below the agent line (or splits the file), the test still passes because it's just substring-matching. The orchestrator parses by section heading, so the wiring is structural.

**Fix:** Strengthen the test in Task 004: assert that the index of `- doc-updater` in the file is GREATER than the index of `## post-implementation`. Also assert the index of `## post-implementation` is greater than the index of `## post-plan` (preserves section order).

### I7. `CLAUDE.md` Documentation section heading collision (Task 001)

`CLAUDE.md` does NOT currently contain a "Documentation" heading, so no immediate collision. But the agent description in `.claude/agents/doc-updater.md` (task 003) also has internal headings. None conflict, but task 001's Documentation section is a *very* generic heading. Consider naming it "Documentation Standards" to be specific.

**Fix (minor):** Use `## Documentation` as proposed (the table-of-contents-style brevity in CLAUDE.md is the project's existing pattern — see "Tech Stack", "Commands"). Keep as is.

## Minor (Nice to address)

### M1. Plan's "200 lines" minimum is arbitrary (Task 001)

The 200-line minimum on `DOCS-STANDARDS.md` is a heuristic that may not survive the C3 fix (drop CLI section). Worth removing the hard floor and replacing with structural assertions ("all 5 sections present", "Package README Format section present").

### M2. `index.mdx` GitHub URL is a guess (Task 002)

`https://github.com/markommerce/markommerce` may not be the real repo URL. Verify with the user. If unknown, link to the local repo or leave a TODO.

### M3. The agent's "Skip files that are test-only, internal refactors, or non-package code" rule is fuzzy

What counts as "internal refactor"? Workers will struggle to test this. Consider adding explicit path patterns: anything under `tests/`, anything matching `**/_internal/**`, etc. Tractable, low-value — defer until first real plan exposes the issue.

### M4. `composer.json` could declare `docs/` as part of the project metadata

Not required for this plan, but eventually `composer.json` should declare `extra.markommerce.docs` or similar, so tooling knows where docs live. Defer.

## Questions for the Team

### Q1. Should `marko/cli` actually appear in markommerce DOCS-STANDARDS?
Until markommerce's CLI story is decided, the safer path (per C3) is to drop the CLI Commands rule. Confirm before merging.

### Q2. What is the canonical repo URL? `markommerce/markommerce` is plausible but unverified.

### Q3. Should `doc-updater` also have a `description` field constraint test?
The Marko agent uses the description verbatim from the frontmatter — there's no current requirement that the description match a particular template. If we want consistency between agents, that's a future agent-style guide concern.

### Q4. Should the `Documentation` link in `CLAUDE.md` go to `docs/DOCS-STANDARDS.md` or to `docs/src/content/docs/index.mdx`?
Currently it points to the standards file. That's correct for contributors. End users would expect the index. Keep as standards file for contributor-oriented `CLAUDE.md`.
