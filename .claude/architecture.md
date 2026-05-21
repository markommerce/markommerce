# Architecture

## Philosophy

Markommerce follows the same architectural philosophy as Marko: opinionated, not restrictive. Every bad pattern has a good alternative. The framework redirects toward better architecture rather than blocking functionality.

The commerce layer adds one more dimension: **every domain boundary is an interface contract**. Nothing in `markommerce/catalog` should depend on a concrete class from `markommerce/cart`. All cross-module dependencies go through interfaces.

## Directory Structure

```
packages/                          # All commerce modules (monorepo)
  catalog/                         # Product & category management
    src/
      Contracts/                   # Public interface contracts
      Entity/                      # marko/database entities (#[Table]/#[Column] attributes)
      Repositories/                # Data access implementations
      Services/                    # Business logic
      Exceptions/                  # Domain exceptions (extend MarkoException)
      module.php                   # Marko module registration
    tests/
      Unit/
      Feature/
    composer.json
  cart/
  checkout/
  customer/
  order/
  payment/                         # Interface only — no implementation
  payment-stripe/                  # Stripe driver
  payment-paypal/                  # PayPal driver
  shipping/                        # Interface only
  inventory/
  pricing/
```

## Module System

Markommerce modules are Marko modules. A package becomes a module by setting `"extra": { "marko": { "module": true } }` in `composer.json`. The Marko framework discovers and loads them automatically.

### Module Registration (`module.php`)

Only create `module.php` when needed (bindings, disabling). Minimal example:

```php
<?php

declare(strict_types=1);

return [
    'bindings' => [
        PaymentGatewayInterface::class => StripePaymentGateway::class,
    ],
];
```

## Interface / Driver Split

Domain packages define contracts; driver packages implement them:

| Interface package         | Implementation package(s)               |
|---------------------------|-----------------------------------------|
| `markommerce/payment`     | `markommerce/payment-stripe`, `…-paypal`|
| `markommerce/shipping`    | `markommerce/shipping-fedex`, `…-ups`   |

Interface packages export only interfaces, exceptions, and value objects. They have no concrete implementations and no database dependencies.

## Patterns Used

### Repository Pattern
Data access is always behind a `*RepositoryInterface`. Concrete repositories live in the implementing module. Application code never touches database classes directly.

```
PaymentGatewayInterface  ←  defined in payment/
StripePaymentGateway     ←  implemented in payment-stripe/
```

### Service Layer
Business logic lives in services, not in repositories or controllers. Services depend on repository interfaces, never on concrete implementations.

### Marko Extensibility Primitives
- **Preferences** — override any interface binding in `module.php`
- **Plugins** — decorate public methods without subclassing (Marko `#[Plugin]` attribute)
- **Observers** — react to domain events (Marko `#[Observer]` attribute)

## Naming Conventions

- **Packages**: `markommerce/{domain}` (interface) or `markommerce/{domain}-{driver}` (implementation)
- **Namespaces**: `Markommerce\{Domain}\` (e.g. `Markommerce\Catalog\`)
- **Interfaces**: `*Interface` suffix (e.g. `ProductRepositoryInterface`)
- **Exceptions**: `*Exception` suffix, extend `MarkoException`
- **Driver classes**: `{Driver}{Component}` (e.g. `StripePaymentGateway`)

## Cross-Module Dependencies

- Modules may depend on Marko framework packages and on other markommerce **interface** packages
- Modules must **never** depend on another module's implementation (driver) package
- All cross-domain contracts go through public interfaces in the interface package

## Exception Standards

All exceptions extend `MarkoException` with three named parameters: `message`, `context`, `suggestion`. Use static factory methods:

```php
class ProductNotFoundException extends MarkoException
{
    public static function forId(int $id): self
    {
        return new self(
            message: "Product with ID $id not found",
            context: 'While loading product for display',
            suggestion: 'Verify the product ID exists and is not soft-deleted',
        );
    }
}
```
