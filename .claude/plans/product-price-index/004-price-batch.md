# Task 004: catalog — `PriceBatch` working object

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `PriceBatch` value object that carries a set of products through the contributor pipeline. It holds caller-keyed products, an accumulating per-key amount (decimal string), and the base `Currency` for the batch. Contributors mutate amounts set-wise; the resolver reads them out at the end. This is the shared working surface that makes "resolve one" and "reindex N" the same code path.

## Context
- New file `packages/catalog/src/Pricing/PriceBatch.php` (mutable, NOT readonly — amounts accumulate).
- Keys are caller-supplied (`array-key`): product id for the indexer, `0` for the single-resolve path. Transient products (no id) are fine — the key is external.
- Shape:
  ```php
  final-less class PriceBatch  // no `final` per project rule
  {
      /** @param array<array-key, Product> $products @param array<array-key, ?string> $amounts */
      public function __construct(
          private array $products,
          private Currency $currency,
          private array $amounts = [],
      ) {}

      public static function of(array $products, Currency $currency): self;   // amounts start empty
      /** @return array<array-key, Product> */
      public function products(): array;
      public function currency(): Currency;
      public function amount(int|string $key): ?string;        // null if unset
      public function setAmount(int|string $key, ?string $amount): void;
      /** @return list<array-key> */
      public function keys(): array;                            // keys of products()
  }
  ```
- `setAmount` with a key not present in `products()` should throw a loud exception (`message`/`context`/`suggestion`) — contributors must only touch batch members.
- No DB, no scope, no currency math here — pure container.

## Requirements (Test Descriptions)
- [x] `it exposes the products it was built with keyed as given`
- [x] `it exposes the base currency of the batch`
- [x] `it returns null for an amount that has not been set`
- [x] `it stores and returns an amount for a product key`
- [x] `it overwrites a previously set amount for a product key`
- [x] `it lists the product keys in the batch`
- [x] `it throws when setting an amount for a key not in the batch`
- [x] `it preserves non sequential and string keys`

## Acceptance Criteria
- `PriceBatch` has no dependency on scope, database, or the resolver.
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
- `PriceBatch` implemented in `packages/catalog/src/Pricing/PriceBatch.php` — mutable class (no `readonly`), amounts accumulate via `setAmount`.
- `InvalidBatchKeyException` added in `packages/catalog/src/Pricing/Exceptions/InvalidBatchKeyException.php` — extends `MarkoException` with `message`/`context`/`suggestion`.
- `setAmount` uses `array_key_exists` to guard against invalid keys.
- Test file at `packages/catalog/tests/Unit/Pricing/PriceBatchTest.php` with 8 tests covering all requirements.
- PHPStan level 8 clean; phpcbf auto-fixed multiline method signatures per Slevomat rules.
