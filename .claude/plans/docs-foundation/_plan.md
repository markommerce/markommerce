# Plan: Docs Foundation

## Created
2026-05-12

## Status
completed

## Objective
Stand up the documentation system's foundation — content standards, a Starlight-ready content directory, and a `doc-updater` subagent in the post-implementation pipeline — so every future plan automatically produces conformant docs under `docs/src/content/docs/`. The actual Astro/Starlight app is **out of scope**; this plan delivers content + tooling that an Astro app will consume whenever we build it.

## Related Issues
none

## Discovery Notes
- Marko's docs setup is the model:
  - `docs/DOCS-STANDARDS.md` — 229-line spec with 5-section content taxonomy (Getting Started, Concepts, Packages, Guides, Tutorials), formatting rules, package README slim-format, content principles.
  - `docs/src/content/docs/{section}/*.md` — Starlight content collection. Index at `index.mdx` (splash hero), section folders with leaf pages.
  - `docs/` is also a full Astro app (`astro.config.mjs`, `package.json` with `@astrojs/starlight`). **We are not porting the app layer in this plan.**
  - `marko/.claude/agents/doc-updater.md` — Sonnet subagent with `Read, Edit, Glob, Grep, Write` tools. Reads `@docs/DOCS-STANDARDS.md` via frontmatter. Identifies changed packages, updates/creates docs pages, slims READMEs.
- Markommerce currently has:
  - `.claude/` with config files but **no `.claude/agents/` directory**.
  - `pipeline.md` has `post-implementation` with `<!-- - standards-enforcer -->` (commented out, never ran).
  - No `docs/` directory anywhere.
  - Branch starting point: `develop`, where only the empty `markommerce/core` package exists.
- `phpunit.xml` already includes a root-level `tests/` testsuite (the "Monorepo" suite), so root-level tests for docs / agents Just Work — no config changes needed.

### Decisions resolved during clarification
- **Docs location**: `docs/DOCS-STANDARDS.md` at the docs app root; content at `docs/src/content/docs/{section}/`.
- **Taxonomy**: mirror Marko's 5 sections (Getting Started, Concepts, Packages, Guides, Tutorials).
- **Initial content**: a splash `index.mdx`, a `getting-started/introduction.md` introducing markommerce, and `.gitkeep` placeholders in the other section folders. **No package docs yet** (no real packages on `develop`).
- **Agent location**: `.claude/agents/doc-updater.md` — first agent in this directory; the directory itself is part of this plan.
- **Pipeline wiring**: replace the commented `standards-enforcer` placeholder in `.claude/pipeline.md` with an active `- doc-updater` entry under `post-implementation`. Future plans will run it after every implementation.
- **Astro app deferred**: no `package.json`, no `astro.config.mjs`, no `content.config.ts`, no Node deps. The content directory is consumable by Starlight whenever the app gets built.
- **README format**: Marko's slim README format (title + one-liner, installation, quick example, docs link) is encoded in `DOCS-STANDARDS.md`. It applies prospectively to new packages. The existing `markommerce/core` README is non-existent today; if/when `feature/catalog-basics` merges, its full READMEs will need to be slimmed in a follow-up. That migration is **not** part of this plan.

### Decisions resolved during devil's-advocate review
- **CLI Commands rule deferred**: Marko's `### CLI Commands` formatting subsection (which prescribes `marko foo` over `php marko foo`) is **not ported** to markommerce's standards. The markommerce CLI story is undecided, and prescribing a non-existent command would mislead contributors. A follow-up plan will restore the rule once the CLI is settled. See task 001.
- **No new composer dependencies**: tests for the agent file use raw line-based string assertions, not `symfony/yaml`. Adding a new framework-wide dep just to parse 4 frontmatter keys is overkill. See task 003.
- **Non-package early-exit for `doc-updater`**: the agent must output `DOCS_CURRENT` immediately when no changed file lives under `packages/*/src/`, `packages/*/README.md`, or `packages/*/composer.json`. This makes the agent safe to run after docs-only / infra-only / tooling-only plans (including this very plan). See task 003.
- **Task 003 also depends on task 002**: so the `docs/src/content/docs/packages/` target directory exists before the agent is wired up.

## Scope

### In Scope
- `docs/DOCS-STANDARDS.md` — adapted port of Marko's standards, with all references rewritten to markommerce (package names, namespaces, URLs).
- `docs/src/content/docs/` directory tree with 5 section folders (`getting-started`, `concepts`, `packages`, `guides`, `tutorials`) — each tracked via `.gitkeep` unless seeded with initial content this plan.
- `docs/src/content/docs/index.mdx` — landing page with a splash hero linking to Getting Started and the GitHub repo (mirror Marko's index.mdx shape minus Marko-specific copy).
- `docs/src/content/docs/getting-started/introduction.md` — one introductory page so the docs aren't empty on day one.
- `.claude/agents/` directory + `doc-updater.md` agent — ported from Marko, adapted to markommerce paths and package conventions.
- `.claude/pipeline.md` updated: replace the commented `standards-enforcer` line under `post-implementation` with an active `- doc-updater` entry.
- Lightweight existence/content tests at `tests/Unit/Docs/` and `tests/Unit/Agents/`.
- A brief "Documentation" section in `CLAUDE.md` pointing to `docs/DOCS-STANDARDS.md`.

### Out of Scope
- The Astro/Starlight app itself (`package.json`, `astro.config.mjs`, themes, components, Node deps, build pipeline, hosting). Deferred to a later plan.
- `content.config.ts` and any Astro/Starlight schema definitions. Comes with the app.
- Package docs pages for any existing/future package — `doc-updater` will produce them in subsequent plans, not this one.
- Migrating the (currently unmerged) `feature/catalog-basics` READMEs to the slim format. That's a follow-up after this plan merges and catalog merges.
- Adding `marko-env`-style configuration for docs (search, theme, etc.) — comes with the app.
- A formal `Tutorials` section page — deferred until we have one.
- Search, sitemap, redirects, link checking, screenshot generation.

## Success Criteria
- [ ] `composer test:all` passes.
- [ ] `phpstan analyse` passes at level 8.
- [ ] `docs/DOCS-STANDARDS.md` exists, contains all 5 section definitions, the package README slim format, formatting rules (sans the deferred CLI Commands subsection), and the content principles.
- [ ] `docs/src/content/docs/` has the 5 section folders, each tracked by git (`.gitkeep` or real content).
- [ ] `docs/src/content/docs/index.mdx` and `docs/src/content/docs/getting-started/introduction.md` exist with proper Starlight frontmatter.
- [ ] `.claude/agents/doc-updater.md` exists, has correct frontmatter (`name`, `description`, `model`, `tools`), and references `@docs/DOCS-STANDARDS.md`.
- [ ] The `doc-updater` agent body documents the orchestrator input format and the non-package early-exit (`DOCS_CURRENT`).
- [ ] `.claude/pipeline.md` `post-implementation` section lists `- doc-updater` as an active entry, and the section ordering matches `## post-plan` → `## post-implementation`.
- [ ] `CLAUDE.md` has a Documentation section linking to `docs/DOCS-STANDARDS.md`, and the existing "Detailed Configuration" list gains a bullet for the standards file.
- [ ] No Astro/Node files exist anywhere in `docs/` (verified by tests).
- [ ] No new composer dependencies were added (no `symfony/yaml`, no Astro/Node packages).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Write `docs/DOCS-STANDARDS.md` adapted from Marko + add Documentation section to `CLAUDE.md` + bootstrap root `tests/Pest.php` if needed | - | completed |
| 002 | Scaffold `docs/src/content/docs/` (5 section folders, `index.mdx`, `getting-started/introduction.md`, `.gitkeep` in empty sections) | - | completed |
| 003 | Create `.claude/agents/doc-updater.md` (Marko port, paths/text adapted to markommerce, drop CLI Commands rule, add non-package early-exit, document orchestrator input format) | 001, 002 | completed |
| 004 | Wire `doc-updater` into `.claude/pipeline.md` `post-implementation` | 003 | completed |

## Architecture Notes

### Why we're not building the Astro app yet
The content collection format (frontmatter, file layout) is what Starlight consumes. Building the Astro app is a separable concern — themes, build pipeline, hosting. By shipping the content layer first, we let `doc-updater` start producing real documentation immediately. The app can be assembled later from the accumulated content with no rework.

### Why standards live in `docs/` and not `.claude/`
`DOCS-STANDARDS.md` governs human and AI contributors equally. Putting it in `docs/` (next to the content it governs) keeps it discoverable from the docs app itself and makes it obvious that it's project-level — not Claude tooling. The `doc-updater` agent imports it via `@docs/DOCS-STANDARDS.md` in its frontmatter.

### Why we don't seed package docs in this plan
The only package on `develop` is empty `markommerce/core`. Writing a stub `packages/core.md` would be pseudo-documentation (banned by the standards we're writing). When `feature/catalog-basics` merges (or when any other plan adds a real package), the `doc-updater` agent will generate the docs page from the README automatically.

### Why `doc-updater` is wired into post-implementation
Two reasons:
1. **Drift-free docs**: every implementation that lands gets a docs pass without anyone having to remember.
2. **Plan templates stay simple**: future plan tasks don't need explicit "update docs" entries — the pipeline handles it. (Marko's plans have explicit doc tasks because their docs system pre-dates this kind of agent.)

## Risks & Mitigations
- **`doc-updater` could fail silently or produce off-target docs after every implementation**. Mitigation: the agent outputs `DOCS_UPDATED` or `DOCS_CURRENT` as the final non-whitespace line for deterministic orchestrator detection. The agent also early-exits with `DOCS_CURRENT` when no changed file lies under `packages/*/src/`, `packages/*/README.md`, or `packages/*/composer.json` — so docs-only, infra-only, or tooling-only plans (including this very plan's own first run) cleanly no-op. First few real runs after this plan will need human review to calibrate.
- **Standards drift between Marko and markommerce**. Mitigation: standards are forked, not synced — markommerce can diverge. If we want to track Marko's changes, that's a future bookkeeping task. Out of scope here.
- **Content folder grows unowned**. Mitigation: every package docs page has a clear upstream — its README. The `doc-updater` agent's job is to keep them in sync.
- **Catalog-basics PR currently has non-slim READMEs**. Mitigation: noted in Out of Scope; addressed in the catalog merge sequence, not this plan.
- **No CI for "docs build doesn't break"** until the Astro app exists. Mitigation: docs content is plain markdown — wrong frontmatter or broken links won't fail this plan's tests but will fail the Astro build when it lands. Accept this trade-off for the time-to-value win.
