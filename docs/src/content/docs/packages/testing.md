---
title: markommerce/testing
description: Integration test scaffolding for Markommerce — per-worker databases, transaction rollback isolation, fluent fixture factories, and a three-profile invariant matrix.
---

Integration test scaffolding for Markommerce. `markommerce/testing` provides `IntegrationTestCase`, `StoreProfile`, `BootedStore`, `FixtureFactory`, and the database provisioning layer that makes real-Postgres integration tests fast, isolated, and parallelizable. It is a dev-only library --- never loaded in production.

## Installation

```bash
composer require --dev markommerce/testing
```

## Quick Start

Extend `IntegrationTestCase`, pick a profile, and write a factory-driven test. The case handles database provisioning, container boot, and per-test rollback automatically.

```php title="packages/my-module/tests/Feature/MyIntegrationTest.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

$vendorDir = dirname(__DIR__, 2) . '/vendor';

it('lists products assigned to a category', function () use ($vendorDir): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(StoreProfile::simple($vendorDir));
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->create();
        $product  = ProductFactory::new($store)->inCategory($category)->create();

        /** @var CategoryAssignmentService $service */
        $service = $store->get(CategoryAssignmentService::class);
        $products = $service->productsInCategory((int) $category->id);

        expect($products)->toHaveCount(1)
            ->and($products[0]->id)->toBe($product->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
```

Call `TestConnection::skipIfUnavailable()` at the top of every integration test. It skips the test gracefully when `DB_*` env vars are absent (e.g. in a unit-only CI job). Tag every integration test with `->group('integration-destructive')` so `composer test` excludes it; run it with `composer test:integration` or `composer test:all`.

### What `IntegrationTestCase` does

1. `setUpIntegration()` --- ensures the profile template and per-worker clone exist, boots the store on the shared connection, and opens a wrapping transaction.
2. `$testCase->store` --- the `BootedStore` for the current test; use `$store->get($id)` to resolve services.
3. `tearDownIntegration()` --- rolls back the wrapping transaction (default) or truncates all tables (`IsolationMode::Truncate`).
4. `tearDownClass()` --- drops the worker-clone database.

## Full-Stack HTTP Testing with `BootedStore::handle()`

`BootedStore::handle(Request): Response` dispatches an HTTP request through the **full** routing + middleware + Latte render pipeline: `CompileIfStaleMiddleware` compiles layout artifacts on demand, `MarkommerceLayoutMiddleware` matches the layout tree and renders real HTML, and the controller runs inside the pipeline. Short-circuit responses (302/410/404) are passed through unchanged.

Use this method to write storefront integration tests that verify real rendered markup, SEO headers, redirect behavior, and pagination controls — all against a live Postgres database. No fake views, no fake containers, no hand-built routers.

### `StoreProfile::storefront($vendorDir)`

Adds the full Tier 1 storefront stack on top of the simple catalog profile: routing, layout, Latte view, Vite frontend, theme-blank, and the markommerce/config DB-backed configuration pipeline. Use this profile whenever your test calls `$store->handle()`.

```php
$profile = StoreProfile::storefront($vendorDir);
```

### Vite handling in tests

The storefront profile sets `vite.useDevServer=true` so `Vite::headTags()` emits dev-server `<script type="module">` tags without reading a build manifest. Tests run without a compiled Vite bundle. Do **not** assert on specific Vite asset tags (they are dev-server URLs that vary by environment); assert on product/category markup instead.

### Request → HTML assertion pattern

The canonical example is `packages/testing/tests/Feature/Http/RequestDispatcherTest.php`. The pattern:

1. Boot via `IntegrationTestCase` with `StoreProfile::storefront($vendorDir)`.
2. Seed data with `CategoryFactory` / `ProductFactory`.
3. (Optional) Write config knobs via `ConfigWriterInterface::setGlobal()` **before the first `handle()` call**.
4. Dispatch via `$store->handle(new Request([...]))`.
5. Assert on status code, response headers, and real `<mk-*>` markup in the body.

```php title="packages/catalog-storefront/tests/Feature/CategorySeoTest.php (excerpt)"
<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

function catalogSeoVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function catalogSeoMakeTestCase(): IntegrationTestCase
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }
    return new IntegrationTestCase(StoreProfile::storefront(catalogSeoVendorDir()));
}

it('returns 410 gone when the requested page exceeds the max depth', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        $writer->setGlobal('catalog/pagination.maxPageDepth', 5);

        $category = CategoryFactory::new($store)->withName('Deep Category')->create();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '?page=6'],
            query: ['page' => '6'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(410);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');
```

**Config writes and the resolver cache**: The real `ConfigResolver` uses a request-scoped `RequestConfigCache`. Write config **before** the first `handle()` call in a test; the `ConfigCacheResetMiddleware` (global middleware declared by `markommerce/config`) clears the resolver's in-request cache at the start of each request. If you need to test different config values in the same test file, create a fresh `IntegrationTestCase` per config variant so the resolver cache starts empty.

**Body assertions**: assert on real `<mk-*>` markup (`<mk-heading>`, `<mk-load-more>`, `<mk-infinite-scroll>`, `catalog-pagination`, `catalog-product-card`, etc.) rather than fake-view `data-template=` strings.

**Header assertions**: short-circuit responses (302, 410, 404) and SEO `Link` headers are set by the controller before the layout middleware runs. Assert them with `$response->headers()['Link']` or `$response->headers()['Location']`.

## Store Profiles

A `StoreProfile` selects which Marko modules to load and which scope axes to declare. It is the single argument to `IntegrationTestCase`.

### Named presets

Three presets cover the most common testing scenarios.

#### `StoreProfile::simple($vendorDir)`

Catalog + PostgreSQL driver, no scope axes. Use this for tests that only need products, categories, and category trees without locale or market scoping.

```php
$profile = StoreProfile::simple($vendorDir);
```

Equivalent to:

```php
StoreProfile::of($vendorDir, 'markommerce/catalog', 'marko/database-pgsql');
```

#### `StoreProfile::singleMarketTwoLocales($vendorDir)`

Catalog + locale axis with `[en, de]` locales declared on a `default` market. Use this when your code reads locale-scoped overrides on catalog entities.

```php
$profile = StoreProfile::singleMarketTwoLocales($vendorDir);
```

#### `StoreProfile::twoMarketsTwoLocales($vendorDir)`

Catalog + market + locale axes with `[us, eu]` markets, one locale per market (`us/en`, `eu/de`). Includes `markommerce/catalog-market` and `markommerce/catalog-price-index-market`. Use this when your code branches on market context or reads market-scoped price overrides.

```php
$profile = StoreProfile::twoMarketsTwoLocales($vendorDir);
```

### Custom profiles with `StoreProfile::of(...)`

Build a profile from an explicit list of root packages. The provisioner transitively resolves all Marko-module dependencies:

```php
use Markommerce\Testing\Profile\StoreProfile;

$profile = StoreProfile::of(
    $vendorDir,
    'markommerce/catalog',
    'markommerce/locale',
    'marko/database-pgsql',
)->withLocales('default', 'en', 'de');
```

Fluent scope methods:

| Method | Description |
|---|---|
| `->withMarkets(string ...$markets)` | Declare market axis values (e.g. `'us'`, `'eu'`). Adds `market` to declared axes. |
| `->withLocale(string $market, string $locale)` | Declare one locale for a market. |
| `->withLocales(string $market, string ...$locales)` | Declare multiple locales for a market. |

### `fromInstalled` --- merchant / application usage

Merchant applications that have installed a full set of Markommerce packages can derive a profile automatically from `vendor/composer/installed.json` without listing every package name. Pass the path to the application's config directory so the real scope axis configuration (markets, locales) is read from the merchant's own config files:

```php
use Markommerce\Testing\Profile\StoreProfile;

$vendorDir    = dirname(__DIR__) . '/vendor';
$appConfigDir = dirname(__DIR__) . '/config';

$profile = StoreProfile::fromInstalled($vendorDir, $appConfigDir);
```

`fromInstalled` reads all installed `marko-module` packages from `installed.json` and builds the full module set. The `$appConfigPath` argument is required so scope axis values (the real markets and locales the merchant has configured) are loaded from the application's `config/` directory. As an alternative to the argument, set the `MARKO_APP_CONFIG_PATH` environment variable:

```bash
MARKO_APP_CONFIG_PATH=/path/to/app/config composer test:integration
```

If neither is supplied, `fromInstalled` throws `MissingAppConfigPathException`.

#### Custom entities with `fromInstalled`

Any entity class decorated with `#[Table]` in any installed package is auto-discovered by `SchemaProvisioner`. Merchant packages that add custom tables simply annotate their entities; no migration files are needed for test runs.

#### Writing factories for custom entities

Extend `FixtureFactory` and inject the `BootedStore` to resolve repositories from the live container:

```php
<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Markommerce\Testing\Fixtures\FixtureFactory;
use Markommerce\Testing\Profile\BootedStore;

class WarehouseFactory extends FixtureFactory
{
    private string $name = '';

    public static function new(BootedStore $store): self
    {
        return new self($store);
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function create(): object
    {
        /** @var WarehouseRepositoryInterface $repo */
        $repo = $this->store->get(WarehouseRepositoryInterface::class);

        $warehouse = new Warehouse();
        $warehouse->name = $this->name !== '' ? $this->name : 'Warehouse-' . self::nextCounter();

        $repo->save($warehouse);

        return $warehouse;
    }
}
```

`nextCounter()` returns a static incrementing integer shared across all factory calls in a test run, guaranteeing unique default values without random state.

## Isolation Model

### Per-(profile x worker) databases

`DatabaseProvisioner` maintains two layers of databases:

1. **Template database** --- one per profile (identified by an MD5 of sorted module names). Built once per test run: the schema is provisioned from `#[Table]` entity metadata, then the provisioning connection is closed. An advisory lock serialises concurrent workers so the template is built exactly once.

2. **Worker-clone database** --- one per ParaTest worker (`TEST_TOKEN` env var). Created via `CREATE DATABASE … TEMPLATE …` (a fast file-level copy). Reused across all tests in that worker. Dropped on `tearDownClass()`.

This means concurrent ParaTest workers each operate against their own isolated database clone, with no cross-worker interference.

### Transaction rollback (default)

`IsolationMode::Rollback` (the default) wraps each test in a database transaction that is rolled back at the end of the test. This is fast --- no DDL, no DELETE, just `BEGIN` and `ROLLBACK`. Use this mode for the vast majority of integration tests.

```php
// Rollback is the default; no explicit argument needed
$testCase = new IntegrationTestCase(StoreProfile::simple($vendorDir));
```

**Constraint**: the code under test must not open its own transaction. PostgreSQL has no nested-transaction savepoints, so a nested `BEGIN` inside the test body would throw `nestedTransactionNotSupported`.

### Truncate opt-out

`IsolationMode::Truncate` clears all profile tables between tests using `DELETE FROM` instead of transaction rollback. Use this for code paths that open their own transactions (e.g. batch indexers, queue workers):

```php
use Markommerce\Testing\Database\IsolationMode;

$testCase = new IntegrationTestCase(
    StoreProfile::simple($vendorDir),
    IsolationMode::Truncate,
);
```

Note: `DELETE FROM` preserves auto-increment sequences --- IDs keep incrementing across tests in the same worker. Tests must not rely on specific ID values.

## Scope Helpers

`BootedStore::inScope()` runs a closure within an active market and/or locale scope. Both arguments are nullable; only non-null values whose axis is declared by the profile are activated. After the closure returns, all scope values are cleared:

```php
$store->inScope(market: 'us', locale: null, fn: function () use ($service, $category): void {
    $products = $service->productsInCategory($category->id);
    // scope is 'us' inside this closure
});
// scope cleared here
```

If you pass a non-null axis value for an axis that was not declared by the profile, `inScope()` throws `UndeclaredAxisException`.

## The `storeProfiles` Invariant-Matrix Dataset

For behavior that must hold across all three named profiles, use the `storeProfiles` Pest dataset. Copy the dataset declaration into your package's `tests/Datasets.php` and decorate your test with `->with('storeProfiles')`:

```php title="tests/Datasets.php"
<?php

declare(strict_types=1);

use Markommerce\Testing\Profile\StoreProfile;

$vendorDir = dirname(__DIR__, 1) . '/vendor';

dataset('storeProfiles', static function () use ($vendorDir): array {
    return [
        'simple'      => [StoreProfile::simple($vendorDir)],
        'two-locales' => [StoreProfile::singleMarketTwoLocales($vendorDir)],
        'two-markets' => [StoreProfile::twoMarketsTwoLocales($vendorDir)],
    ];
});
```

```php title="tests/Feature/MyInvariantTest.php"
<?php

declare(strict_types=1);

use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

it('works correctly in every profile', function (StoreProfile $profile): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;
        // ... assertions that must hold across all three profiles ...
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->with('storeProfiles')->group('integration-destructive');
```

The canonical reference implementation is `packages/testing/tests/Feature/InvariantMatrixTest.php`, which exercises position sort ordering and conditional price-index sort orders across all three profiles.

## Schema-from-Entities

`SchemaProvisioner` builds the test database schema directly from `#[Table]` entity metadata. No migration files are read or required for integration tests. The provisioner discovers entity classes from every package in the profile, extracts column and foreign-key metadata, and applies the resulting `CREATE TABLE` statements to the template database.

### GIN index omission

The `config_values` table uses a JSONB `value` column. A GIN index on that column is intentionally omitted from the entity metadata --- `marko/database`'s `#[Index]` attribute cannot express `USING GIN`. The GIN index is a production-only performance index; it is created separately (outside entity metadata) in production deployments. Integration tests run without it.

### Migration round-trip suite

`packages/testing/tests/Feature/Migration/MigrationRoundTripTest.php` contains a self-contained schema/diff consistency suite. It does NOT read any host application's migration directory. It:

1. Builds the entity schema from fixture entities (`tests/Fixture/Entity/`) into an in-memory schema registry.
2. Provisions a scratch database and runs `SchemaProvisioner::provision()`.
3. Runs `DiffCalculator` between the entity-defined schema and the provisioned database.
4. Asserts zero diff --- proving the generate/diff path is consistent.

The suite is fully self-contained: all entity paths reference this package's fixture directory only.

## Running Locally

Set the `DB_*` environment variables to point at a local PostgreSQL instance before running integration tests. The database user must have `CREATEDB` privilege (required to create template and clone databases):

```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=marko_dev
export DB_USERNAME=marko
export DB_PASSWORD=secret

composer test:integration
```

In Docker (as used in this repo's development environment):

```bash
docker compose -f ~/www/marko/compose.yaml exec -T -w /workspace/markommerce app composer test:integration
```

`TestConnection::skipIfUnavailable()` checks for all five `DB_*` vars at the top of each test and calls `test()->markTestSkipped()` when any are missing, so integration tests are skipped rather than erroring in environments without a database.

## Running in CI

The included `.github/workflows/ci.yml` defines a separate `integration` job that:

1. Starts a PostgreSQL service container (`postgres:17`).
2. Sets `DB_*` environment variables pointing at the service container.
3. Grants `CREATEDB` to the test user via `psql -c "ALTER USER ... CREATEDB"`.
4. Runs `composer test:integration` (which maps to `./vendor/bin/pest --group=integration-destructive`).

PHPStan analysis is run with a raised memory limit (`php -d memory_limit=2G ./vendor/bin/phpstan analyse`) to avoid out-of-memory failures on large codebases.

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- `ProductFactory` and `CategoryFactory` are provided by this package's `tests/Support/` directory; used by the invariant matrix tests
- [markommerce/scope](/docs/packages/scope/) --- scope axis resolution used by `BootedStore::inScope()`
- [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) --- `ProductFactory::withIndexedPrice()` requires this package; throws when absent
