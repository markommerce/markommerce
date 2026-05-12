# Task 002: Scaffold `docs/src/content/docs/` directory tree

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the Starlight-ready content collection skeleton with the 5 section folders, a splash landing page at `index.mdx`, and a seed `getting-started/introduction.md`. Empty sections get tracked via `.gitkeep` so git preserves them. This task creates **content only** — no Astro app config, no Node dependencies.

## Context

### Directory tree to create

```
docs/
  src/
    content/
      docs/
        index.mdx
        getting-started/
          introduction.md
        concepts/
          .gitkeep
        packages/
          .gitkeep
        guides/
          .gitkeep
        tutorials/
          .gitkeep
```

### `docs/src/content/docs/index.mdx`

Splash landing page. Mirror the SHAPE of Marko's `index.mdx` (Starlight `template: splash` + hero block + a CardGrid teaser), but with markommerce copy. Reference: `/home/michal/www/marko/marko/docs/src/content/docs/index.mdx`.

Required frontmatter and structure:

```mdx
---
title: MARKOMMERCE
description: A modular PHP e-commerce framework — Magento-grade extensibility on top of Marko.
template: splash
hero:
  tagline: Magento-grade modularity for e-commerce, built on Marko PHP. Every domain boundary is an interface; every behavior is swappable.
  actions:
    - text: Get Started
      link: /docs/getting-started/introduction/
      icon: right-arrow
    - text: View on GitHub
      link: https://github.com/markommerce/markommerce
      icon: external
      variant: minimal
      attrs:
        target: _blank
        rel: noopener
---

import { Card, CardGrid } from '@astrojs/starlight/components';

## Why Markommerce?

<CardGrid>
  <Card title="Interface-First" icon="puzzle">
    Every domain boundary is a contract. Swap payments, shipping, or any other driver
    without touching the core.
  </Card>
  <Card title="Built on Marko" icon="seti:php">
    Inherits Marko's module system, preferences, plugins, and event-driven architecture.
  </Card>
  <Card title="Loud Errors" icon="warning">
    Every exception carries a message, context, and suggestion. No silent failures.
  </Card>
  <Card title="No Magic" icon="approve-check">
    Constructor injection only. Explicit types. PHPStan level 8 across the codebase.
  </Card>
</CardGrid>
```

Notes:
- The `import` line references `@astrojs/starlight/components` — fine as a static reference; not resolved until the Astro app is built. The file is markdown-with-MDX and lives untouched until then. **DO NOT** "fix" the dangling import by removing it. It is intentional and required for the Astro app once it lands.
- The import list is **exactly `{ Card, CardGrid }`** — do NOT add `LinkCard` (Marko's source includes `LinkCard` because its splash uses an Explore section; markommerce's splash does not). An unused import will trigger a Starlight warning when the app lands.
- `template: splash` is a Starlight feature; preserved for when the app lands.
- No PHP tooling parses MDX/MD files: `phpstan`, `phpcs`, `php-cs-fixer`, and `rector` only walk `*.php`. The dangling import is invisible to all linters in this plan.

### `docs/src/content/docs/getting-started/introduction.md`

Required frontmatter:

```markdown
---
title: Introduction
description: What markommerce is and why it exists.
---

Markommerce is a modular PHP 8.5+ e-commerce framework that brings Magento-grade extensibility to projects built on the Marko PHP framework. Every domain boundary is an interface contract; every behavior is swappable through Marko's preferences, plugins, and observer system.

## What Markommerce Provides

Markommerce ships a set of focused commerce modules — catalog, cart, checkout, payment, shipping, inventory, pricing — each one a Marko module. Domain packages define interfaces; driver packages implement them. The pattern that Marko uses for `marko/database` → `marko/database-mysql` repeats here: `markommerce/payment` → `markommerce/payment-stripe` / `markommerce/payment-paypal`, and so on.

## What Markommerce Is Not

- Not a turnkey storefront. Markommerce is a framework, not a product.
- Not a Magento clone. The architectural inspiration is Magento's modularity; the developer experience is Marko's.
- Not opinionated about your front-end. Markommerce ships PHP packages; the storefront is yours.

## Next Steps

- [Installation](/docs/getting-started/installation/) — set up a markommerce project.
- [Your First Store](/docs/getting-started/your-first-store/) — wire up a minimal catalog.
- [Modularity](/docs/concepts/modularity/) — understand how markommerce composes on top of Marko.
```

The links to Installation, Your First Store, and Modularity are intentional dangling references — those pages will exist when later plans create them. Starlight is fine with broken root-relative links in development; they surface as warnings, not errors.

### `.gitkeep` files

Plain empty files at:
- `docs/src/content/docs/concepts/.gitkeep`
- `docs/src/content/docs/packages/.gitkeep`
- `docs/src/content/docs/guides/.gitkeep`
- `docs/src/content/docs/tutorials/.gitkeep`

(`getting-started/` does not need a `.gitkeep` because `introduction.md` is already in it.)

**Compatibility note**: Starlight's content collection scans only `*.md` and `*.mdx` files. `.gitkeep` files are invisible to Starlight at build time, so they are safe to leave in place once the Astro app lands. If we later switch from `.gitkeep` to seed pages, that's a content addition — not a breaking change.

### Non-goals
- No `package.json`, no `astro.config.mjs`, no `content.config.ts`. The file tree is what Starlight consumes; tooling lands in a separate plan.

## Requirements (Test Descriptions)
- [x] `it creates the docs/src/content/docs directory tree`
- [x] `it creates an index.mdx with Starlight splash template frontmatter and a hero block`
- [x] `it creates getting-started/introduction.md with title and description frontmatter`
- [x] `it creates a placeholder .gitkeep in concepts packages guides and tutorials sections`
- [x] `it does not create any Astro or Node config files (no package.json no astro.config.mjs no content.config.ts)`

## Acceptance Criteria
- File structure exactly as specified.
- Tests in `tests/Unit/Docs/ContentSkeletonTest.php` use `is_dir()`, `file_exists()`, and `file_get_contents()` + substring assertions for the frontmatter shape.
- A negative-assertion test confirms no `package.json` / `astro.config.mjs` / `content.config.ts` exists anywhere under `docs/`.

## Implementation Notes
- Created `docs/src/content/docs/` directory tree with 5 section subdirectories.
- Created `docs/src/content/docs/index.mdx` as the Starlight splash landing page with hero block and CardGrid — import uses exactly `{ Card, CardGrid }` (no LinkCard).
- Created `docs/src/content/docs/getting-started/introduction.md` with required frontmatter and section headings.
- Created empty `.gitkeep` files in `concepts/`, `packages/`, `guides/`, and `tutorials/` to preserve empty directories in git.
- No `package.json`, `astro.config.mjs`, or `content.config.ts` was created under `docs/`.
- Tests live in `tests/Unit/Docs/ContentSkeletonTest.php` using `is_dir()`, `file_exists()`, `file_get_contents()` with substring assertions.
