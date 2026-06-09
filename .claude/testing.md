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

## Test Environment (self-contained Docker — default)

Tests run in the **self-contained Docker stack** defined by `compose.yaml` in the
repo root. It clones the marko framework at the pinned `.marko-version` and runs
its own Postgres — markommerce needs no local `../marko` checkout. This is what
CI uses; use it locally too. (A developer with the full `~/www/marko` workspace
may instead run inside the workspace `app` container — see `CLAUDE.local.md` —
but the self-contained stack is the default reference.)

Start an interactive container once (entrypoint clones marko + installs deps,
which then persist in volumes), then run commands inside it:

```bash
docker compose run --rm tests bash
# ...then inside the container:
```

Inside the container (or as one-off `docker compose run --rm tests <cmd>`):

```bash
# Run the unit / non-DB suite (parallel; excludes integration-destructive)
composer test

# Run ONLY the DB-backed integration suite (parallel, real Postgres)
composer test:integration

# Run everything (unit + integration)
composer test:all

# Run a specific file / package / filter
./vendor/bin/pest packages/catalog/tests/Unit/ProductTest.php
./vendor/bin/pest packages/catalog/tests/
./vendor/bin/pest --filter="resolves product by id"

# Coverage / type coverage
./vendor/bin/pest --parallel --coverage --min=80
./vendor/bin/pest --type-coverage

# Static analysis — REQUIRES the raised memory limit (default 128M OOMs)
php -d memory_limit=2G ./vendor/bin/phpstan analyse
```

One-off integration run without a shell: `docker compose up --abort-on-container-exit`.

## Integration tests (`integration-destructive` group)

DB-backed tests are tagged `->group('integration-destructive')` and use the
`markommerce/testing` harness (`Markommerce\Testing\IntegrationTestCase` + a
`StoreProfile`): schema is built from entity metadata into per-(profile × worker)
Postgres template-clone databases, with transaction-rollback isolation — so they
run parallel-safe and never touch dev data. `composer test` excludes this group;
`composer test:integration` runs only it; `composer test:all` runs both. See the
`markommerce/testing` docs page for writing them.

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
