# markommerce/market

Market axis declaration for Markommerce --- contributes the `market` axis to `scope.axes`.

## Installation

```bash
composer require markommerce/market
```

## Quick Example

The package ships a `config/scope.php` that merges the `market` axis into the scope configuration when discovered alongside `markommerce/scope`:

```php
// config/scope.php (contributed by this package)
return [
    'axes' => [
        'market' => [
            'default' => 'default',
            'scopes' => [
                'default' => [],
            ],
        ],
    ],
];
```

After installation the `market` axis is available for scoped field registration and scope resolution via `markommerce/scope`.

## Documentation

Full usage, API reference, and examples: [markommerce/market](https://markommerce.dev/docs/packages/market/)
