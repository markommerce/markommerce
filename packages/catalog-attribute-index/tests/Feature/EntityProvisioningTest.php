<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function attributeIndexVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function makeAttributeIndexProfile(): StoreProfile
{
    return StoreProfile::of(
        attributeIndexVendorDir(),
        'markommerce/catalog-attribute-index',
        'marko/database-pgsql',
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('provisions the catalog_product_attribute_index table from the entity', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributeIndexProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'catalog_product_attribute_index'",
        );

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['table_name'])->toBe('catalog_product_attribute_index');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
