# Task 008: Adapt docs/src/content/docs/packages/scope.md for Markommerce

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Copy `/home/michal/www/marko/marko/docs/src/content/docs/packages/scope.md` into `/home/michal/www/marko/markommerce/docs/src/content/docs/packages/scope.md`, rewriting it to refer to `markommerce/scope` and the `Markommerce\Scope\…` namespace throughout. Drop every reference to `scope-mysql` (the driver doesn't exist in Markommerce). Keep external cross-links to upstream marko docs (e.g. `marko/database`) pointing at `https://marko.build/docs/packages/database/` rather than relative paths, so the markommerce docs site doesn't render broken internal links.

## Context

- Source: `/home/michal/www/marko/marko/docs/src/content/docs/packages/scope.md`.
- Target: `/home/michal/www/marko/markommerce/docs/src/content/docs/packages/scope.md`.
- Standards: `/home/michal/www/marko/markommerce/docs/DOCS-STANDARDS.md`. Specifically: package pages need Intro paragraph, Installation, Configuration (if applicable), Usage, API Reference, Related Packages.
- Reference example for tone/structure: `/home/michal/www/marko/markommerce/docs/src/content/docs/packages/frontend.md`.
- Rewrites:
  - Frontmatter `title: marko/scope` → `title: markommerce/scope`.
  - Frontmatter `description: …` keep semantically equivalent (just the word `marko/scope` becomes `markommerce/scope`).
  - Intro paragraph: every `marko/scope` → `markommerce/scope`.
  - Installation block: `composer require marko/scope` → `composer require markommerce/scope`. Drop the `marko/scope-mysql` install command and the "or" line, leave only `composer require markommerce/scope-pgsql`.
  - All code blocks: `Marko\Scope\…` → `Markommerce\Scope\…`. Keep `Marko\Database\…` and `Marko\Config\…` unchanged (they reference real upstream marko classes the user is still depending on).
  - API Reference table: every `Marko\Scope\…` FQCN in the left column → `Markommerce\Scope\…`.
  - Caveats section: keep the `marko/config` terminology-overlap note — it's still accurate (the user still depends on marko/config).
  - Related Packages section: rewrite as:
    - `[markommerce/scope-pgsql](/docs/packages/scope-pgsql/) — PostgreSQL driver`
    - `[marko/database](https://marko.build/docs/packages/database/) — Entity system and QuerySpecification interface` (external link, since we don't fork marko/database docs)
    - REMOVE the `marko/scope-mysql` entry entirely.
- Do NOT touch other markommerce docs pages or the sidebar config — adding a new package page picks up automatically if the site's nav is filesystem-driven; if it's explicit, that's a separate change the doc-updater agent handles during post-implementation.

## Requirements (Test Descriptions)

- [ ] `it copies marko docs/scope.md to markommerce docs/packages/scope.md`
- [ ] `it has frontmatter title: markommerce/scope`
- [ ] `it has an Installation section with composer require markommerce/scope`
- [ ] `it has zero references to scope-mysql in the markommerce scope.md`
- [ ] `it uses Markommerce\Scope\ namespaces in every code example, not Marko\Scope\`
- [ ] `it preserves Marko\Database\, Marko\Config\ references in code examples (those are real upstream deps)`
- [ ] `it links to markommerce/scope-pgsql as the single driver`
- [ ] `it links to marko/database externally at marko.build (not as a relative markommerce link)`

## Acceptance Criteria

- File exists at the target path.
- Grep for `marko/scope-mysql` returns zero matches in the new file.
- Grep for `Marko\\Scope\\` returns zero matches in the new file (the namespace must be `Markommerce\\Scope\\`).
- Grep for `Marko\\Database\\` and `Marko\\Config\\` returns at least one match each (the unchanged upstream deps).
- The file conforms to the section order in `DOCS-STANDARDS.md` for package pages (Intro, Installation, Configuration, Usage, API Reference, Related Packages).

## Implementation Notes
(Left blank — filled in during implementation)
