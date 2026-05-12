# Task 003: Create `.claude/agents/doc-updater.md`

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Add the first agent under `.claude/agents/` — `doc-updater`. Port from Marko, adapt paths and references to markommerce. The agent reads `docs/DOCS-STANDARDS.md` via its frontmatter, identifies changed packages from the file list passed to it, and updates or creates docs pages and READMEs accordingly.

## Context

### Source agent
`/home/michal/www/marko/marko/.claude/agents/doc-updater.md` — 79 lines. Frontmatter:

```yaml
---
name: doc-updater
description: "Documentation updater. Reviews changed package code and updates corresponding docs pages and package READMEs when public API, configuration, or usage patterns have changed."
model: sonnet
tools: Read, Edit, Glob, Grep, Write
---
```

Then a body with sections: Documentation Standards (imports `@docs/DOCS-STANDARDS.md`), Process (Step 1-5), Rules.

### Adaptations from Marko's version

| Marko text | Markommerce text |
|---|---|
| "Marko framework" | "markommerce framework" |
| `marko/{package-name}` in path examples | `markommerce/{package-name}` |
| `Marko\` namespace references | `Markommerce\` |
| All other content | keep verbatim — the agent's logic is framework-agnostic |

### Required additions to the agent body (not in the Marko source)

The Marko `doc-updater` was written before the markommerce orchestrator contract existed. Port adds three explicit clauses:

1. **Input format (in `## Process` preamble):**
   > The orchestrator passes a newline-separated list of project-relative file paths as your prompt. One file per line. No diff content is included — use the `Read` tool to inspect changed files if needed.

2. **Non-package early-exit (NEW Step 1.5, inserted between current Step 1 and Step 2):**
   > **Step 1.5: Check for non-package-only change set.**
   > If EVERY changed file path lies outside `packages/*/src/`, `packages/*/README.md`, and `packages/*/composer.json`, the change set has no user-facing API impact. Skip Steps 2–4 entirely and output `DOCS_CURRENT`. This prevents the agent from running on infra-only, tooling-only, or docs-only plans (including this very plan's own initial run).

3. **Output sentinel placement (clarify Step 5):**
   > Output `DOCS_UPDATED` or `DOCS_CURRENT` as the **final non-whitespace line** of your response so the orchestrator can detect it deterministically.

Path references in the agent that need updating:
- `docs/src/content/docs/packages/{package-name}.md` — keep, paths are project-relative
- `packages/{package-name}/README.md` — keep, paths are project-relative

The `@docs/DOCS-STANDARDS.md` import in the body works as long as `docs/DOCS-STANDARDS.md` exists (task 001 creates it). The `@` syntax is Claude Code's auto-load.

### Final file location
`.claude/agents/doc-updater.md` — this task also creates the `.claude/agents/` directory if it does not exist.

### What the agent does (one-paragraph summary for the description body)
> "Documentation updater for markommerce. Reviews a list of changed package files, identifies which packages had public-API / configuration / usage changes, and updates or creates the corresponding docs pages (`docs/src/content/docs/packages/{name}.md`) and slim package READMEs. Skips internal-only changes. Outputs `DOCS_UPDATED` or `DOCS_CURRENT`."

## Requirements (Test Descriptions)
- [ ] `it creates the .claude/agents directory`
- [ ] `it creates .claude/agents/doc-updater.md with frontmatter name doc-updater`
- [ ] `it declares the model as sonnet and tools as Read Edit Glob Grep Write in the frontmatter`
- [ ] `it imports docs/DOCS-STANDARDS.md via the @docs/DOCS-STANDARDS.md auto-load reference in the body`
- [ ] `it describes the agent's process steps Identify Affected Packages Determine What Changed Find Relevant Docs Update or Create Docs and Output`
- [ ] `it declares the DOCS_UPDATED and DOCS_CURRENT output sentinels`
- [ ] `it instructs the agent to slim package READMEs to the format defined in DOCS-STANDARDS.md`
- [ ] `it adapts references from Marko to markommerce throughout the body (no occurrences of marko/cache or marko/database remain)`
- [ ] `it documents that the orchestrator passes a newline-separated list of project-relative file paths`
- [ ] `it instructs the agent to output DOCS_CURRENT immediately when no changed file lies under packages/{name}/src or packages/{name}/README.md or packages/{name}/composer.json`

## Acceptance Criteria
- File exists at `.claude/agents/doc-updater.md`.
- Frontmatter parses as valid YAML — `name`, `description`, `model`, `tools` keys present.
- Body length is comparable to Marko's source (75+ lines, with the additions in this task slightly larger).
- Tests in `tests/Unit/Agents/DocUpdaterAgentTest.php` use **line-based string assertions** on the raw file content. Do NOT add `symfony/yaml` (or any new composer dependency) just to parse 4 frontmatter keys. Assert each required key with substring/regex matches against the raw `file_get_contents()` output. Example:
  ```php
  $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');
  expect($content)
      ->toContain("name: doc-updater")
      ->toContain("model: sonnet")
      ->toMatch('/tools:\s*Read,\s*Edit,\s*Glob,\s*Grep,\s*Write/');
  ```

## Implementation Notes
(Left blank — filled in by programmer during implementation)
