# Task 004: Wire `doc-updater` into `.claude/pipeline.md` `post-implementation`

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Activate the `doc-updater` agent so it runs after every implementation plan completes. Replace the commented-out `standards-enforcer` placeholder line with an active `- doc-updater` entry under the `post-implementation` section of `.claude/pipeline.md`.

## Context

### Current state of `.claude/pipeline.md`

```markdown
# Pipeline

Configure which agents run at each phase of the development workflow. Each entry is an agent name resolved from the project's `.claude/agents/` directory (local override) or the plugin's `agents/` directory (default).

## post-plan
- devils-advocate

## post-implementation
<!-- - standards-enforcer -->
```

### Target state

```markdown
# Pipeline

Configure which agents run at each phase of the development workflow. Each entry is an agent name resolved from the project's `.claude/agents/` directory (local override) or the plugin's `agents/` directory (default).

## post-plan
- devils-advocate

## post-implementation
- doc-updater
```

The commented `standards-enforcer` line is **removed entirely** — it was a placeholder that never ran. If we want to add it later, we can. Leaving stale comments in config files invites confusion.

### Why this is its own task
- The change is a single one-line replacement, but it activates real automation. Tests verify the active entry is present so a future accidental commenting-out would fail CI.
- Splitting from task 003 keeps each task's diff narrow: 003 creates the agent file; 004 activates it. A reviewer sees the activation step as a discrete decision.

### Behavior change after this lands
- Every `hcf:plan-orchestrate` run reads `pipeline.md`, finds `doc-updater` under `post-implementation`, and invokes it after the last task in a plan finishes but BEFORE the final commit. The agent updates docs based on changed files; its changes are folded into the same commit as the implementation.

## Requirements (Test Descriptions)
- [ ] `it lists doc-updater as an active bullet under the post-implementation section of pipeline.md`
- [ ] `it removes the commented-out standards-enforcer placeholder line`
- [ ] `it keeps devils-advocate as the only entry under the post-plan section`
- [ ] `it preserves the file's existing header and explanation paragraph`
- [ ] `it places the doc-updater bullet at a file offset greater than the post-implementation heading`
- [ ] `it places the post-implementation heading at a file offset greater than the post-plan heading`

## Acceptance Criteria
- `.claude/pipeline.md` parses correctly when read by the orchestrator (the orchestrator reads bullets after `## post-implementation`).
- Tests in `tests/Unit/Agents/PipelineConfigTest.php` use line-based string matching and `strpos()` ordering assertions:
  - Assert the literal line `- doc-updater` appears in the file.
  - Assert `strpos($content, '- doc-updater') > strpos($content, '## post-implementation')` — bullet is structurally inside the right section.
  - Assert `strpos($content, '## post-implementation') > strpos($content, '## post-plan')` — section order preserved.
  - Assert no `<!-- - standards-enforcer -->` line exists.
  - Assert `- devils-advocate` appears after the `## post-plan` heading (`strpos` check).

## Implementation Notes
(Left blank — filled in by programmer during implementation)
