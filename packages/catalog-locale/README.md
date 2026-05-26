# markommerce/catalog-locale

Locale field bridge for catalog entities --- registers `Product.name`, `Product.description`, `Category.name`, and `Category.description` as locale-scoped fields via `ScopedFieldRegistry` at boot.

## Installation

```bash
composer require markommerce/catalog-locale
```

## Quick Example

This package is a thin auto-wiring bridge. Its entire purpose is a `boot` closure in `module.php` that registers the locale-scoped catalog fields --- no manual wiring required:

```php title="packages/catalog-locale/module.php"
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/locale' => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        foreach ([Product::class, Category::class] as $entityClass) {
            foreach (['name', 'description'] as $property) {
                $scopedFieldRegistry->register(
                    entityClass: $entityClass,
                    property: $property,
                    axes: ['locale'],
                );
            }
        }
    },
];
```

Installing this package is the configuration --- the `boot` closure runs automatically when the module is loaded.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-locale](https://markommerce.dev/docs/packages/catalog-locale/)
