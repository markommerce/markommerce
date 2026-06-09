# markommerce/testing

Integration test scaffolding for Markommerce --- per-worker databases, transaction rollback isolation, fluent fixture factories, and a three-profile invariant matrix.

## Installation

```bash
composer require --dev markommerce/testing
```

## Quick Example

```php
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

it('lists products in a category', function (): void {
    TestConnection::skipIfUnavailable();

    $vendorDir = dirname(__DIR__, 2) . '/vendor';
    $testCase  = new IntegrationTestCase(StoreProfile::simple($vendorDir));
    $testCase->setUpIntegration();

    try {
        $store    = $testCase->store;
        $category = CategoryFactory::new($store)->create();
        ProductFactory::new($store)->inCategory($category)->create();

        expect($store->get(CategoryAssignmentService::class)
            ->productsInCategory((int) $category->id)
        )->toHaveCount(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
```

## Documentation

Full usage, API reference, and examples: [markommerce/testing](https://markommerce.dev/docs/packages/testing/)
