# Task 001: money package scaffold + Currency value object

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the new `markommerce/money` package and its `Currency` value object. This is the dependency-free foundation reused by currency, pricing, cart, checkout, and payment. `Currency` captures an ISO 4217 code plus the metadata needed to format and scale amounts.

## Context
- New package dir: `packages/money/` with `src/` + `tests/`, `composer.json` (`type: marko-module`, `extra.marko.module: true`), PSR-4 `Markommerce\Money\` → `src/`.
- Add `brick/math` to the package `require`. **Pin `^0.12 || ^0.13`** — both lines support PHP 8.5 (`0.12` requires `php ^8.1`, `0.13` requires `php ^8.2`); `0.13` is the current stable. Do NOT leave the constraint open-ended ("latest compatible") — brick/math `0.x` minors carry breaking API changes, so the constraint must be explicit. The repo root is `minimum-stability: dev` / `prefer-stable: true`, so a stable `0.13` release will be selected. Also add `php: ^8.5`, `markommerce/core: self.version` (for `MarkoException`).
- Register the package in the **root** `composer.json`: add `markommerce/money` to `require`, and `Markommerce\Money\Tests\` → `packages/money/tests/` to `autoload-dev.psr-4`.
- Mirror `packages/market/composer.json` exactly for structure/keys.
- `Currency` is a `readonly class` (immutable; all constructor props). No `final`. `declare(strict_types=1)`.
- Properties: `string $code` (ISO 4217, uppercase 3 letters), `int $scale` (default fraction digits, e.g. 2 for USD, 0 for JPY), `string $symbol`, `string $name`.
- Constructor validates `code` is exactly 3 uppercase A–Z letters and `scale >= 0`; invalid input throws an `InvalidCurrencyException extends MarkoException` (message/context/suggestion via static factory).
- Constants need explicit type declarations.

## Requirements (Test Descriptions)
- [x] `it creates a currency with code scale symbol and name`
- [x] `it exposes code scale symbol and name as readonly properties`
- [x] `it uppercases and accepts a valid three letter iso code`
- [x] `it throws InvalidCurrencyException when code is not three letters`
- [x] `it throws InvalidCurrencyException when scale is negative`
- [x] `it treats two currencies with the same code as equal via an equals method`

## Acceptance Criteria
- `packages/money/` builds and is registered in root `composer.json` (require + autoload-dev).
- `brick/math` resolves in the package `require`.
- All requirements have passing tests; coverage ≥ 80%.
- Code follows code standards (no `final`, `readonly class`, `@throws` tags, strict types).

## Implementation Notes
- `Currency` is a `readonly class` with a non-promoted `$code` property to allow uppercasing in the constructor body before assignment.
- Validation: code is uppercased then matched against `/^[A-Z]{3}$/`; negative scale throws immediately after the code check.
- `InvalidCurrencyException extends MarkoException` via two static factory methods: `forInvalidCode` and `forNegativeScale`.
- `equals(Currency $currency): bool` compares by normalized (uppercased) code only, matching ISO 4217 identity semantics.
- PHPStan level 8 passes; php-cs-fixer auto-corrected single-quote strings in the exception factory.
