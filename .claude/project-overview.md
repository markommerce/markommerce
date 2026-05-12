# Project Overview

## Identity

- **Name**: Markommerce
- **Tagline**: Magento-grade e-commerce modularity on top of Marko PHP
- **Repository**: github.com/markommerce/markommerce
- **License**: MIT

## Goal

Build a modular e-commerce framework in the spirit of Magento 2 — where every behavior is swappable, every boundary is an interface, and third-party code can override anything without forking the core. Built entirely on top of the Marko PHP framework.

## Tech Stack

- **Language**: PHP 8.5+
- **Framework**: Marko (github.com/marko-php/marko)
- **Testing**: Pest 4 (parallel, 80% min coverage)
- **Linting**: PHP-CS-Fixer + PHP_CodeSniffer (Slevomat)
- **Static Analysis**: PHPStan level 8
- **Refactoring**: Rector
- **Databases**: MySQL/MariaDB, PostgreSQL (via Marko's database packages)

## Repository Structure

- **Type**: Monorepo (mirrors Marko's structure)
- **Packages**: `packages/` directory — one Composer package per domain
- **Namespace root**: `Markommerce\`
- **Versioning**: Unified — all packages share the same version number (mirrors Marko's `self.version` pattern)

## Domain Modules (planned)

Starting with `catalog`. Future modules will follow as the platform grows:

- `catalog` — Products, categories, attributes (the foundation)
- `cart` — Shopping cart and line items
- `checkout` — Checkout flow and order placement
- `customer` — Customer accounts and addresses
- `order` — Order management and history
- `payment` — Payment contracts (interface) + driver packages per gateway
- `shipping` — Shipping contracts (interface) + driver packages per carrier
- `inventory` — Stock management
- `pricing` — Price rules, discounts, taxes
- `search` — Commerce-specific search layer on top of `marko/search`

## Extensibility Model

Follows Marko's extensibility primitives:

- **Preferences** — replace any bound interface with a custom implementation
- **Plugins** — decorate any public method without subclassing
- **Observers** — react to domain events without coupling
- **Interface/driver split** — contracts in base packages, implementations in driver packages (e.g. `markommerce/payment` + `markommerce/payment-stripe`)
