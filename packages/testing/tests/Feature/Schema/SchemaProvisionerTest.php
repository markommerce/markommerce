<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature\Schema;

use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Schema\SchemaProvisioner;
use Markommerce\Testing\Tests\Fixture\Entity\ChildFixtureEntity;
use Markommerce\Testing\Tests\Fixture\Entity\ParentFixtureEntity;

it('deduplicates entities discovered from overlapping directories', function (): void {
    $provisioner = new SchemaProvisioner();

    $catalogEntityDir = realpath(__DIR__ . '/../../../../../packages/catalog/src/Entity');
    expect($catalogEntityDir)->not->toBeFalse();

    // Pass the same directory twice — should not throw duplicate registration error
    $tableNames = $provisioner->tableNames([$catalogEntityDir, $catalogEntityDir]);

    // The unique table names from catalog entities
    $uniqueTableNames = array_unique($tableNames);
    expect($uniqueTableNames)->toHaveCount(count($tableNames));
    expect($tableNames)->toContain('catalog_products');
    expect($tableNames)->toContain('catalog_categories');
    expect($tableNames)->toContain('catalog_product_category');
});

it('discovers table entities across multiple entity directories', function (): void {
    $provisioner = new SchemaProvisioner();

    $catalogEntityDir = realpath(__DIR__ . '/../../../../../packages/catalog/src/Entity');
    $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');

    expect($catalogEntityDir)->not->toBeFalse()
        ->and($fixtureEntityDir)->not->toBeFalse();

    $tableNames = $provisioner->tableNames([$catalogEntityDir, $fixtureEntityDir]);

    expect($tableNames)->toContain('catalog_products')
        ->and($tableNames)->toContain('catalog_categories')
        ->and($tableNames)->toContain('catalog_product_category')
        ->and($tableNames)->toContain('fixture_parents')
        ->and($tableNames)->toContain('fixture_children');
});

it('creates all discovered tables in a fresh database', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_provisioner_tables_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $catalogEntityDir = realpath(__DIR__ . '/../../../../../packages/catalog/src/Entity');
        expect($catalogEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [$catalogEntityDir]);

        $tables = $conn->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );

        $tableNames = array_map(static fn (array $row): string => (string) $row['table_name'], $tables);

        expect($tableNames)->toContain('catalog_products')
            ->and($tableNames)->toContain('catalog_categories')
            ->and($tableNames)->toContain('catalog_product_category');
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('creates foreign keys after all tables exist using a fixture entity with a table-dot-column reference', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_provisioner_fks_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
        expect($fixtureEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [$fixtureEntityDir]);

        $constraints = $conn->query(
            "SELECT constraint_name, constraint_type FROM information_schema.table_constraints"
            . " WHERE table_name = 'fixture_children' AND constraint_type = 'FOREIGN KEY' AND table_schema = 'public'",
        );

        expect($constraints)->not->toBeEmpty();

        $constraintNames = array_map(
            static fn (array $row): string => (string) $row['constraint_name'],
            $constraints,
        );

        expect($constraintNames)->toContain('fk_fixture_children_parent_id');
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('creates declared indexes including the catalog product-category unique index', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_provisioner_indexes_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $catalogEntityDir = realpath(__DIR__ . '/../../../../../packages/catalog/src/Entity');
        expect($catalogEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [$catalogEntityDir]);

        $indexes = $conn->query(
            "SELECT indexname, indexdef FROM pg_indexes"
            . " WHERE tablename = 'catalog_product_category' AND schemaname = 'public'",
        );

        $indexNames = array_map(static fn (array $row): string => (string) $row['indexname'], $indexes);

        expect($indexNames)->toContain('uniq_catalog_product_category');

        $uniqueIndex = array_find(
            $indexes,
            static fn (array $row): bool => $row['indexname'] === 'uniq_catalog_product_category',
        );

        expect($uniqueIndex)->not->toBeNull();
        expect((string) $uniqueIndex['indexdef'])->toContain('UNIQUE');
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('provisions the catalog product and category tables from the catalog entity dir', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_provisioner_catalog_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $catalogEntityDir = realpath(__DIR__ . '/../../../../../packages/catalog/src/Entity');
        expect($catalogEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [$catalogEntityDir]);

        // Verify tables exist and can accept data
        $conn->execute("INSERT INTO catalog_categories (name) VALUES ('Test Category')");
        $conn->execute("INSERT INTO catalog_products (sku, name) VALUES ('TEST-001', 'Test Product')");

        $categories = $conn->query("SELECT id, name FROM catalog_categories");
        $products = $conn->query("SELECT id, sku, name FROM catalog_products");

        expect($categories)->toHaveCount(1)
            ->and((string) $categories[0]['name'])->toBe('Test Category')
            ->and($products)->toHaveCount(1)
            ->and((string) $products[0]['sku'])->toBe('TEST-001');
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('exposes the provisioned table names', function (): void {
    $provisioner = new SchemaProvisioner();

    $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
    expect($fixtureEntityDir)->not->toBeFalse();

    $tableNames = $provisioner->tableNames([$fixtureEntityDir]);

    expect($tableNames)->toBeArray()
        ->and($tableNames)->toContain('fixture_parents')
        ->and($tableNames)->toContain('fixture_children')
        ->and($tableNames)->toHaveCount(2);
});

it('creates columns matching the entity metadata', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_provisioner_columns_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $catalogEntityDir = realpath(__DIR__ . '/../../../../../packages/catalog/src/Entity');
        expect($catalogEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [$catalogEntityDir]);

        $columns = $conn->query(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'catalog_products' AND table_schema = 'public' ORDER BY column_name",
        );

        $columnNames = array_map(static fn (array $row): string => (string) $row['column_name'], $columns);

        expect($columnNames)->toContain('id')
            ->and($columnNames)->toContain('sku')
            ->and($columnNames)->toContain('name')
            ->and($columnNames)->toContain('description')
            ->and($columnNames)->toContain('price_amount');
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');
