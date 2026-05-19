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

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Scope;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(length: 255)]
    #[Scoped(axes: ['locale'])]
    public string $name = '';
}

// Set a scoped override
$scopeResolver->setOverride($product, 'name', 'Widget DE', new Scope('locale', 'de'));

// Resolve with hierarchy fallback (de-DE walks up to de)
$scopeContext->in('locale', 'de-DE');
$localizedName = $scopeResolver->resolved($product, 'name');
```

## Documentation

Full usage, API reference, and examples: [markommerce/scope](https://markommerce.dev/docs/packages/scope/)
