# markommerce/catalog-market

Market field bridge for catalog entities --- placeholder bridge that reserves the `ScopedFieldRegistry` boot hook for future market-scoped fields (e.g. price, visibility).

## Installation

```bash
composer require markommerce/catalog-market
```

## Quick Example

This package is a thin auto-wiring bridge. Its `boot` closure in `module.php` is typed on `ScopedFieldRegistry` and ready for future market-scoped catalog fields --- no manual wiring required once fields are added:

```php title="packages/catalog-market/module.php"
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market' => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // No fields registered yet — Product does not have price or visibility columns.
        // This closure is type-hinted on ScopedFieldRegistry for future expansion.
    },
];
```

Installing this package is the configuration --- the `boot` closure will run automatically when the module is loaded.

## Placeholder Status

This package ships with an empty boot closure today. `Product` does not yet have `price` or `visibility` columns, so no market-scoped fields are registered. The boot hook is in place and typed on `ScopedFieldRegistry` so that adding those fields in a future release requires no structural changes --- only the registration calls inside the closure.

See the [Tier 3 row in FEATURES.md](https://github.com/markommerce/markommerce/blob/main/FEATURES.md) for the planned end state.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-market](https://markommerce.dev/docs/packages/catalog-market/)
