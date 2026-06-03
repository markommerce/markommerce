# markommerce/tax

Tax mode configuration for Markommerce --- globally configurable tax-inclusive/exclusive pricing mode (no rate computation).

## Installation

```bash
composer require markommerce/tax
```

## Quick Example

Configure whether stored/displayed prices include tax via the `tax/prices_include_tax` config key, then resolve the active mode through `TaxModeResolver`:

```php
use Markommerce\Tax\TaxMode;
use Markommerce\Tax\TaxModeResolver;

// $configResolver is a Markommerce\Config\ConfigResolver (or a ScopedConfigResolver via Preference)
$resolver = new TaxModeResolver($configResolver);

$mode = $resolver->mode(); // TaxMode::Exclusive (default) or TaxMode::Inclusive

if ($mode === TaxMode::Inclusive) {
    // Prices already contain tax — display as-is
} else {
    // Prices are exclusive of tax — add tax at display/checkout time
}
```

The default value of `tax/prices_include_tax` is `false` (exclusive). To switch to inclusive pricing, store `true` for that key via `markommerce/config`.

Note: this package provides the *mode* flag only — no rate computation. Tax rates belong in a separate package.

## Documentation

Full usage, API reference, and examples: [markommerce/tax](https://markommerce.dev/docs/packages/tax/)
