# markommerce

Markommerce is a modular PHP e-commerce framework — Magento 2-grade extensibility built on top of the Marko PHP framework. Every domain boundary is an interface, every behavior is swappable, and third-party code can override anything without forking the core.

## Tech Stack

- **Language**: PHP 8.5+
- **Framework**: Marko (`../marko`)
- **Testing**: Pest 4 (parallel, 80% min coverage)
- **Linting**: PHP-CS-Fixer + PHP_CodeSniffer (Slevomat)
- **Static Analysis**: PHPStan level 8
- **Refactoring**: Rector

## Core Principles

1. **Magento-grade modularity** — everything is swappable via Marko's Preferences, Plugins, and Observers
2. **Loud errors** — no silent failures; every exception has `message`, `context`, and `suggestion`
3. **Explicit over implicit** — constructor injection only, no service locators, no magic methods
4. **Interface/driver split** — contract packages define boundaries, driver packages implement them
5. **No `final` classes** — preserves Preference-based extensibility for downstream consumers

## Project Structure

```
packages/          # All commerce modules (monorepo)
  catalog/         # Products & categories — the foundation (interface + default impl)
  cart/            # Shopping cart and line items
  checkout/        # Checkout flow and order placement
  payment/         # Payment contracts (interface only)
  payment-stripe/  # Stripe driver
  shipping/        # Shipping contracts (interface only)
  …
```

All packages share the same version number (unified versioning).

## Commands

Tests run in the **self-contained Docker stack** (`compose.yaml`): it clones the
marko framework at the pinned `.marko-version` and runs its own Postgres — no
local `../marko` needed. Start a shell once (`docker compose run --rm tests bash`)
and run the commands below inside it. See `.claude/testing.md` for the full
workflow and `CLAUDE.local.md` for the workspace-container alternative.

```bash
# Unit / non-DB suite (parallel; excludes the integration-destructive group)
composer test

# DB-backed integration suite only (parallel, real Postgres)
composer test:integration

# Everything (unit + integration)
composer test:all

# Lint (check / fix)
./vendor/bin/phpcs
./vendor/bin/php-cs-fixer fix

# Static analysis — needs the raised memory limit (default 128M OOMs)
php -d memory_limit=2G ./vendor/bin/phpstan analyse
```

## Key Rules

- Every PHP file: `declare(strict_types=1);`
- No `final` classes — blocks Marko Preferences
- Use `readonly class` when all constructor properties are immutable
- Constructor injection only — no service locators
- Interface parameter names: camelCase of interface name minus `Interface` suffix
- All constants need explicit type declarations (PHP 8.3+)
- Every `throw` or exception-propagating call needs a `@throws` PHPDoc tag
- No traits — use explicit composition via injected dependencies
- No magic methods — be explicit
- Prefer `array_find()`, `array_any()`, `array_all()` over foreach loops (PHP 8.5)

## Feature Development

For any feature beyond a simple fix, use the `hcf:plan-create` skill to trigger the autonomous development workflow. Never use Claude Code's built-in plan mode.

## Detailed Configuration

Project configuration files are in `.claude/`:
- `project-overview.md` — Project identity, goals, and planned domain modules
- `architecture.md` — Technical patterns, directory structure, module system
- `testing.md` — Test configuration, TDD workflow, fakes vs mocks
- `code-standards.md` — Coding conventions and style rules
- `pipeline.md` — Post-plan and post-implementation agent pipeline
- `../docs/DOCS-STANDARDS.md` — Content standards for the docs site and package READMEs

## Documentation

Documentation content lives in `docs/src/content/docs/` and follows the rules in `docs/DOCS-STANDARDS.md`. The `doc-updater` agent in `.claude/agents/` runs after every implementation to keep docs in sync.
