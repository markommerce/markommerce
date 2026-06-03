# markommerce/money-intl

Locale-aware money formatting for Markommerce — renders a `Money` value as a localized currency string using PHP's `ext-intl` `NumberFormatter`, pulling the active locale from `ScopeContext`.

## Installation

```bash
composer require markommerce/money-intl
```

> **Requires `ext-intl`** — the PHP `intl` extension must be enabled. If it is not loaded, `MoneyFormatter` will throw a `MoneyFormattingException` with a suggestion to enable the extension.

## Quick Example

```php
use Markommerce\MoneyIntl\MoneyFormatter;

// format() reads the active locale from ScopeContext (falls back to 'en')
$formatted = $formatter->format($money);
// e.g. "$1,234.56"

// formatFor() uses an explicit locale
$formatted = $formatter->formatFor($money, 'de');
// e.g. "1.234,56 €"
```

## Documentation

Full usage, API reference, and examples: [markommerce/money-intl](https://markommerce.dev/docs/packages/money-intl/)
