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

## Field metadata

`ScopedFieldRegistry` is the read-time source of truth for which properties of
an entity class are scoped and which axes they belong to. `ScopeMetadataFactory`
reads from the registry to build `ScopeMetadata` instances; consumers call
`ScopeMetadataFactory::for($entityClass)` to retrieve metadata for a class.
The public API of `ScopeMetadataFactory` is unchanged.

### Attribute path (lazy scan)

The simplest way to mark a property as scoped is the `#[Scoped]` attribute.
On the first call to `ScopeMetadataFactory::for($entityClass)`, the factory
performs a one-time reflection scan of the class and its parents, writing any
`#[Scoped]` findings into `ScopedFieldRegistry`. Subsequent calls return the
cached `ScopeMetadata` without re-scanning.

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('catalog_products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(length: 255)]
    #[Scoped(axes: ['locale'])]
    public string $name = '';
}
```

A `#[Scoped(axes: [])]` declaration is a no-op: the property will not appear
in the resulting `ScopeMetadata`.

### Programmatic path (bridge `module.php`)

Third-party packages that cannot annotate an entity directly --- for example, a
bridge package adding scope support to a vendor entity --- register properties
programmatically via a `boot` callback in their `module.php`. The container
auto-injects `ScopedFieldRegistry` by type-hint:

```php title="packages/acme-catalog-scope/module.php"
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        $scopedFieldRegistry->register(
            entityClass: Product::class,
            property: 'price',
            axes: ['channel', 'locale'],
        );
    },
];
```

Passing `axes: []` to `register()` is a no-op: the call returns without
modifying the registry.

If any axis name is not registered in `ScopeRegistryInterface`, `register()`
throws `UnknownAxisException` immediately --- there are no silent failures.

### Cache-staleness contract

Once `ScopeMetadataFactory::for($entityClass)` has been called for a class, the
resulting `ScopeMetadata` is frozen. Later calls to `ScopedFieldRegistry::register()`
for the same class do NOT affect the already-cached metadata. All bridge
contributions MUST happen during the boot phase, before any request handling
begins.

## Documentation

Full usage, API reference, and examples: [markommerce/scope](/docs/packages/scope/)
