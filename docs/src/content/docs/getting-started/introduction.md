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
