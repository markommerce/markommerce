---
title: markommerce/money-intl
description: Locale-aware money formatting for Markommerce — renders a Money value as a localized currency string using PHP's ext-intl NumberFormatter.
---

Locale-aware money formatting for Markommerce. `markommerce/money-intl` provides a single `MoneyFormatter` class that renders a `Money` value object as a localized currency string using PHP's `ext-intl` `NumberFormatter`. The active locale is read from `ScopeContext` automatically, with a fallback to `'en'` when no locale is active. An explicit locale can also be passed directly.

## Installation

```bash
composer require markommerce/money-intl
```

> **Requires `ext-intl`** --- the PHP `intl` extension must be enabled. If it is not loaded, `MoneyFormatter` throws `MoneyFormattingException` on instantiation with a suggestion to enable the extension.

## Usage

### Formatting with the active scope locale

`format()` reads the active locale from `ScopeContext` (the `locale` axis) and falls back to `'en'` when no locale is set:

```php
use Markommerce\MoneyIntl\MoneyFormatter;

// $moneyFormatter is injected by the container
// Active locale from ScopeContext, e.g. 'en_US'
$formatted = $moneyFormatter->format($money);
// => "$1,234.56"
```

### Formatting with an explicit locale

`formatFor()` bypasses `ScopeContext` and formats using the locale you provide:

```php
use Markommerce\MoneyIntl\MoneyFormatter;

$formatted = $moneyFormatter->formatFor($money, 'de');
// => "1.234,56 €"

$formatted = $moneyFormatter->formatFor($money, 'fr_FR');
// => "1 234,56 €"
```

Both methods delegate to PHP's `NumberFormatter::formatCurrency()`. Amounts are cast to `float` only at this presentation boundary --- the `string` from `Money::amount()` remains the source of truth for arithmetic and storage.

## API Reference

### `MoneyFormatter`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `format(Money $money)` | `string` | `MoneyFormattingException` | Format `$money` using the active locale from `ScopeContext`, falling back to `'en'`. |
| `formatFor(Money $money, string $locale)` | `string` | `MoneyFormattingException` | Format `$money` using the given BCP-47/ICU locale string. |

**`DEFAULT_LOCALE`** --- class constant `string` with value `'en'`. Used as the fallback when `ScopeContext` has no active `locale` value.

### Exceptions

| Exception | Named constructor(s) | When thrown |
|---|---|---|
| `MoneyFormattingException` | `forMissingIntlExtension()` | `ext-intl` is not loaded when `MoneyFormatter` is constructed. |
| `MoneyFormattingException` | `forInvalidLocale(string $locale)` | `NumberFormatter` construction failed for the given locale (malformed or unsupported by the installed ICU library). |

## Related Packages

- [markommerce/money](/docs/packages/money/) --- `Money` and `Currency` value objects
- [markommerce/scope](/docs/packages/scope/) --- `ScopeContext` that `MoneyFormatter::format()` reads the active locale from
- [markommerce/locale](/docs/packages/locale/) --- Sets the active locale in `ScopeContext`
