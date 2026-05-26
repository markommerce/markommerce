# markommerce/locale

Locale axis declaration for Markommerce --- contributes the `locale` axis to `scope.axes`.

## Installation

```bash
composer require markommerce/locale
```

## Quick Example

The package ships a `config/scope.php` that merges the `locale` axis into the scope configuration when discovered alongside `markommerce/scope`:

```php
// config/scope.php (contributed by this package)
return [
    'axes' => [
        'locale' => [
            'default' => 'default',
            'scopes' => [
                'default' => [],
            ],
        ],
    ],
];
```

After installation the `locale` axis is available for scoped field registration and scope resolution via `markommerce/scope`.

## Documentation

Full usage, API reference, and examples: [markommerce/locale](https://markommerce.dev/docs/packages/locale/)
