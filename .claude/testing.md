# Testing Configuration

## Test Framework

**Pest PHP 4** — modern, expressive testing framework built on PHPUnit 12. Mirrors the Marko framework's test setup.

## TDD Methodology

Each task follows strict Red → Green → Refactor:

1. Write a failing test for one requirement
2. Write the minimum code to make it pass
3. Refactor while tests stay green
4. Repeat for the next requirement
5. Commit when the task is complete

## Commands

```bash
# Run all tests (parallel — default during development)
composer test

# Run all tests including destructive integration tests
composer test:all

# Run specific test file
./vendor/bin/pest packages/catalog/tests/Unit/ProductTest.php

# Run tests in a specific package
./vendor/bin/pest packages/catalog/tests/

# Run tests matching a filter
./vendor/bin/pest --filter="resolves product by id"

# Run with coverage
./vendor/bin/pest --parallel --coverage --min=80

# Type coverage
./vendor/bin/pest --type-coverage
```

## Parallel Execution

- **Default**: always run tests in parallel unless debugging a specific failure
- Parallel command: `./vendor/bin/pest --parallel` (via `composer test`)
- Sequential fallback: `./vendor/bin/pest` (use only when parallel causes flaky failures)

## Test File Locations

Each package owns its tests:

```
packages/catalog/
  tests/
    Unit/          # Pure unit tests — no I/O, no framework bootstrap
    Feature/       # Integration tests — full Marko app context
```

Unit tests mirror `src/` structure:
- `src/Services/ProductService.php` → `tests/Unit/Services/ProductServiceTest.php`

## Coverage Requirements

- Minimum: **80%**
- All new code must have tests before merging

## Test Naming Convention

- Test files: `{Subject}Test.php`
- Test descriptions: `it('does something specific')`

```php
it('returns null when product does not exist', function (): void {
    $repo = new FakeProductRepository();
    $service = new ProductService(productRepository: $repo);

    expect($service->find(id: 999))->toBeNull();
});
```

## Integration Test Group

Tests that mutate shared state (databases, filesystem) should be tagged:

```php
it('persists product to database', function (): void {
    // ...
})->group('integration-destructive');
```

Excluded from `composer test` by default. Run with `composer test:all`.

## Fakes Over Mocks

Prefer hand-written fakes over PHPUnit mocks for repository interfaces. Fakes are reusable across tests and make intent clearer:

```php
class FakeProductRepository implements ProductRepositoryInterface
{
    public array $products = [];

    public function find(int $id): ?Product
    {
        return array_find($this->products, fn (Product $p) => $p->id === $id);
    }
}
```
