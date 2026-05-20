# markommerce/scope

Scoped attributes for entities with multi-axis hierarchical fallback.

## Installation

```bash
composer require markommerce/scope
```

A driver package is also required:

```bash
composer require markommerce/scope-pgsql
```

## Quick Example

Two-axis composite override — B2B channel + Spanish locale:

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    #[Scoped(axes: ['channel', 'locale'])]
    public float $price = 100.00;
}

// Single-axis override: B2B channel price
$scopeResolver->setOverride($product, 'price', 85.00, ScopeSignature::fromArray(['channel' => 'b2b']));

// Single-axis override: Spanish locale price
$scopeResolver->setOverride($product, 'price', 90.00, ScopeSignature::fromArray(['locale' => 'es']));

// Composite override: B2B + Spanish — highest priority
$scopeResolver->setOverride($product, 'price', 75.00, ScopeSignature::fromArray(['channel' => 'b2b', 'locale' => 'es']));

// Resolution priority: composite > channel:b2b > locale:es
$scopeContext->in('channel', 'b2b');
$scopeContext->in('locale', 'es');
$price = $scopeResolver->resolved($product, 'price'); // 75.00
```

## Documentation

Full usage, API reference, and examples: [markommerce/scope](/docs/packages/scope/)
