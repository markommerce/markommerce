# Task 001: Write `docs/DOCS-STANDARDS.md` and add Documentation section to `CLAUDE.md`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Port Marko's documentation standards to markommerce. The standards file governs how all docs content (READMEs, docs pages) is structured and written. Also add a short "Documentation" section to `CLAUDE.md` so contributors discover the standards file.

## Context

### Source: `/home/michal/www/marko/marko/docs/DOCS-STANDARDS.md`
229 lines, structured as:
- Content Taxonomy (5 sections: Getting Started, Concepts, Packages, Guides, Tutorials — each with Purpose / Audience / Content style / Pages / Rules)
- Formatting Rules (headings, frontmatter, intro paragraph, code blocks, links, PHP code examples, CLI commands, punctuation, tables)
- Sidebar Order
- Migrating Package READMEs to Docs (the slim README workflow)
- Package README Format (slim format spec)
- Content Principles

### Adaptation rules for markommerce

Port verbatim except for these substitutions:

| Marko reference | Markommerce reference |
|---|---|
| `marko/{package}` | `markommerce/{package}` |
| `Marko\` namespaces | `Markommerce\` namespaces |
| `https://marko.build/docs/...` | `https://markommerce.dev/docs/...` (placeholder URL — fine for now) |
| Examples mentioning `marko/cache`, `marko/database` etc. | replace with markommerce equivalents (`markommerce/catalog`, `markommerce/money`, etc.) — only the example bodies, not the rules themselves |

**CLI Commands rule — REMOVED for this port.** Marko's `## Formatting Rules > CLI Commands` subsection prescribes `marko` (not `php marko`) as the standard command in all examples, on the assumption that `marko/cli` is globally installed. Markommerce has **not** decided whether it ships with `marko/cli`, exposes its own CLI binary, or relies on a different entrypoint. Until that decision lands, prescribing `marko foo` in commerce docs would be a lie. Drop the entire `### CLI Commands` subsection from the ported file. A follow-up plan will restore it once the markommerce CLI story is decided.

Section-by-section adaptations:

- **Getting Started section**: keep the Purpose/Audience/Style structure. Page list: Introduction, Installation, Your First Store, Project Structure, Configuration. (markommerce's onboarding flow is store-centric, not generic-app-centric.)
- **Concepts section**: keep verbatim. Add one example pair to make it markommerce-flavored: "`Modularity` explains the module system" → "`Modularity` explains how markommerce modules layer on top of Marko" and "`Interface/Driver Split` explains the payment-stripe-style pattern".
- **Packages section**: keep verbatim. Update the example package name in any inline reference from `marko/database` → `markommerce/catalog`.
- **Guides section**: keep verbatim with one example switched to a commerce-relevant guide: "Cache data with pluggable backends" → "Wire a Stripe payment driver into checkout".
- **Tutorials section**: keep verbatim. Update the example tutorial reference from "blog, REST API, custom module" to "online store, B2B catalog, custom payment driver".
- **Formatting Rules**: keep verbatim **except** drop the `### CLI Commands` subsection (see above). The PHP Code Examples rule about constructor parameter naming aligns with our `.claude/code-standards.md` rule 3 (interface parameter naming) — keep it; the two files agree.
- **Migrating Package READMEs / Package README Format**: keep verbatim with package name examples updated.
- **Content Principles**: keep verbatim.

### `CLAUDE.md` addition

Two edits:

1. **Append a Documentation section** after the existing "Detailed Configuration" section, keeping the existing structure intact. Suggested text:

   ```markdown
   ## Documentation

   Documentation content lives in `docs/src/content/docs/` and follows the rules in `docs/DOCS-STANDARDS.md`. The `doc-updater` agent in `.claude/agents/` runs after every implementation to keep docs in sync.
   ```

2. **Add one bullet to the existing "Detailed Configuration" list** so contributors discover the standards file from the same index:

   ```markdown
   - `../docs/DOCS-STANDARDS.md` — Content standards for the docs site and package READMEs
   ```

   (The relative path `../docs/` is correct: `CLAUDE.md` lives at the repo root, the other Detailed Configuration entries are under `.claude/`, and `docs/` is also at the repo root. Use a project-root-relative path for consistency with the rest of `CLAUDE.md` if that pattern already prevails — otherwise match the existing entries' relative-to-`.claude/` style. Inspect the file before editing.)

### File locations
- `docs/DOCS-STANDARDS.md` — new file (creates `docs/` directory as a side effect).
- `CLAUDE.md` — modify in place to add the Documentation section and the new bullet.

### Test bootstrap

This task is the first to create root-level tests under `tests/Unit/Docs/`. Before writing the tests:

1. Check whether `tests/Pest.php` exists. If not, create a minimal one:
   ```php
   <?php
   declare(strict_types=1);

   // Root-level Pest configuration for the Monorepo testsuite.
   // Package-level tests have their own Pest.php under packages/{name}/tests/.
   ```
2. Pest 4 will autoload `tests/Pest.php` if it exists; without it, tests still run but project-wide helpers cannot be defined.
3. Tasks 002, 003, and 004 reuse this bootstrap — they MUST NOT recreate or replace `tests/Pest.php`.

## Requirements (Test Descriptions)
- [ ] `it creates docs/DOCS-STANDARDS.md at the docs app root`
- [ ] `it documents all five content sections Getting Started Concepts Packages Guides Tutorials with Purpose Audience and Content style for each`
- [ ] `it includes the Formatting Rules section covering headings frontmatter code blocks links PHP code examples and CLI commands`
- [ ] `it includes the Package README Format section with the slim README structure (title installation quick example documentation link)`
- [ ] `it replaces every Marko-specific package reference with the markommerce equivalent (markommerce/catalog appears at least once and marko/cache does not)`
- [ ] `it includes the Content Principles section with the No pseudo-documentation rule`
- [ ] `it appends a Documentation section to CLAUDE.md pointing to docs/DOCS-STANDARDS.md and mentioning the doc-updater agent`

## Acceptance Criteria
- `docs/DOCS-STANDARDS.md` exists and is at least **180 lines** (Marko's source is 229; we drop the CLI Commands subsection, so the floor is slightly lower).
- All five section headings (`### Getting Started`, `### Concepts`, `### Packages`, `### Guides`, `### Tutorials`) are present.
- The `## Formatting Rules` heading is present with `### Headings`, `### Frontmatter`, `### Intro Paragraph`, `### Code Blocks`, `### Links`, `### PHP Code Examples`, `### Punctuation`, and `### Tables` subsections (note: `### CLI Commands` is intentionally absent — see Adaptation Rules above).
- `## Package README Format`, `## Migrating Package READMEs to Docs`, and `## Content Principles` sections are present.
- Tests in `tests/Unit/Docs/DocsStandardsTest.php` use `file_get_contents()` + string/regex assertions for the structural checks above.
- `tests/Unit/Docs/ClaudeMdReferenceTest.php` asserts `CLAUDE.md` contains a Documentation heading and the path `docs/DOCS-STANDARDS.md`.
- No section was dropped relative to Marko's source **except** `### CLI Commands` (intentional, documented above).
- `phpstan` clean at level 8 for the test files (no source code in this task).

## Implementation Notes
(Left blank — filled in by programmer during implementation)
