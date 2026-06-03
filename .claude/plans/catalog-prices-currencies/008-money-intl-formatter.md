# Task 008: money-intl package — locale-aware MoneyFormatter

**Status**: complete
**Depends on**: 002
**Retry count**: 0

## Description
Create the `markommerce/money-intl` bridge providing a `MoneyFormatter` that renders a `Money` as a localized string using PHP's `intl` `NumberFormatter::CURRENCY`, pulling the active locale from `ScopeContext`. Formatting is a locale concern, kept entirely separate from the price's own currency.

## Context
- New package `packages/money-intl/` (`src/`, `tests/`, `composer.json`, `module.php`). PSR-4 `Markommerce\MoneyIntl\`. Register in root `composer.json` (require + `autoload-dev`).
- `require`: `php ^8.5`, `ext-intl: *`, `markommerce/core`, `markommerce/money`, `markommerce/locale`, `markommerce/scope` (self.version).
- `MoneyFormatter` (`src/MoneyFormatter.php`): constructor-injects `ScopeContext`. Method `format(Money $money): string` and `formatFor(Money $money, string $locale): string`.
  - `format()` reads the active locale via `ScopeContext->get('locale')`; if null/absent, uses a sensible fallback locale (document it; e.g. `'en'`).
  - Uses `NumberFormatter($locale, NumberFormatter::CURRENCY)->formatCurrency(amount, currencyCode)`. The amount passed is derived from `Money->amount()` (decimal string) and the code from `Money->currency()->code`.
  - **Float-precision gotcha:** `NumberFormatter::formatCurrency()` accepts a `float`, so casting the decimal string via `(float)` is unavoidable at the presentation boundary. This is acceptable for display ONLY because the formatter is presentation-only and the stored amount is clamped to `decimal(20,4)` (≤ 4 dp, well within float's safe integer range for realistic prices). Cast explicitly with a comment documenting that the canonical value remains the string `Money->amount()` and that this float is never persisted or used for arithmetic. Do NOT round the Money or feed the float back into any `Money` construction.
  - `NumberFormatter` construction can fail (returns `false`/`null` for an invalid locale on some ICU builds); guard and throw a loud `MoneyFormattingException` rather than letting a `TypeError` surface.
  - Constructor (or a guard) throws a loud `MoneyFormattingException extends MarkoException` if `ext-intl` is not loaded, with a `suggestion` to enable the extension.
- The formatter must NOT round or mutate the `Money`; presentation only. Raw `Money` is what gets stored/resolved elsewhere.
- No `final`; constructor injection only.
- **Author `packages/money-intl/README.md`** per `.claude/package-standard.md` (mirror `packages/market/README.md`): purpose (locale-aware money formatting), install (note `ext-intl`), a Quick Example showing `MoneyFormatter->format($money)` / `formatFor($money, 'de')`, and the docs link.

## Requirements (Test Descriptions)
- [x] `it formats money for an explicitly provided locale`
- [x] `it formats money using the active locale from scope context`
- [x] `it falls back to the default locale when no active locale is set`
- [x] `it renders the currency symbol and grouping according to the locale`
- [x] `it formats a zero decimal currency like JPY without fraction digits`
- [x] `it throws MoneyFormattingException when the intl extension is unavailable`

## Acceptance Criteria
- Package registered in root `composer.json` with `ext-intl` in `require`.
- `packages/money-intl/README.md` exists and follows the package README standard.
- All requirements have passing tests; coverage ≥ 80%. (The ext-intl-missing case may be tested by guarding/injecting the check so it is exercisable.)
- Follows standards; `@throws` documented.

## Implementation Notes
- `MoneyFormatter` constructor-injects `ScopeContext` and an optional `?Closure` for the ext-intl availability check (enables testing without the actual extension).
- `formatFor()` uses `NumberFormatter($locale, NumberFormatter::CURRENCY)->formatCurrency((float) $money->amount(), $code)`. The float cast is documented inline as a presentation-only boundary.
- `format()` reads `$scopeContext->get('locale')` and falls back to `DEFAULT_LOCALE = 'en'`.
- `MoneyFormattingException` has two factories: `forMissingIntlExtension()` and `forInvalidLocale(string $locale)`.
- The container's ICU 76.1 on Alpine does not include full locale data (all locales format identically), so tests assert currency symbol and structural format (grouping separators) rather than locale-specific decimal separators.
- PHPStan level 8 requires `Closure` (not `callable`) for property types; the `intlChecker` parameter uses `?Closure`.
- All 6 requirements tested and passing; PHPStan level 8 and PHPCS clean.
