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

```bash
# Run tests (parallel — default)
composer test

# Run all tests including destructive integration tests
composer test:all

# Lint (check)
./vendor/bin/phpcs

# Lint (fix)
./vendor/bin/php-cs-fixer fix

# Static analysis
./vendor/bin/phpstan analyse
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
