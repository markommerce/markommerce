<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Repositories;

require_once __DIR__ . '/../Helpers/PostgresTestConnection.php';

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Feature\Helpers\PostgresTestConnection;

// ─── Shared connection & lifecycle ───────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_products (
            id   SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            sku  VARCHAR(255) NOT NULL UNIQUE
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_categories (
            id          SERIAL PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            description TEXT
        )',
    );

    $this->conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_product_category (
            id          SERIAL PRIMARY KEY,
            product_id  INTEGER REFERENCES catalog_products(id) ON DELETE CASCADE,
            category_id INTEGER REFERENCES catalog_categories(id) ON DELETE CASCADE,
            position    INTEGER NOT NULL DEFAULT 0
        )',
    );

    // Add position column if it does not yet exist (e.g. table existed before migration)
    $this->conn->execute(
        "DO \$\$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'catalog_product_category'
                  AND column_name = 'position'
            ) THEN
                ALTER TABLE catalog_product_category ADD COLUMN position INTEGER NOT NULL DEFAULT 0;
            END IF;
        END
        \$\$",
    );

    $this->conn->execute(
        "DO \$\$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'catalog_product_category'
                  AND indexname = 'uniq_catalog_product_category'
            ) THEN
                CREATE UNIQUE INDEX uniq_catalog_product_category
                ON catalog_product_category (product_id, category_id);
            END IF;
        END
        \$\$",
    );

    $this->conn->execute("INSERT INTO catalog_products (name, sku) VALUES ('PCA Test Product', 'PCA-SKU-1')");
    $result = $this->conn->query("SELECT id FROM catalog_products WHERE sku = 'PCA-SKU-1' LIMIT 1");
    $this->productId = (int) $result[0]['id'];

    $this->conn->execute("INSERT INTO catalog_categories (name) VALUES ('PCA Test Category')");
    $result = $this->conn->query("SELECT id FROM catalog_categories WHERE name = 'PCA Test Category' LIMIT 1");
    $this->categoryId = (int) $result[0]['id'];

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);
    $this->repository = new ProductCategoryAssignmentRepository($this->conn, $metadataFactory, $hydrator);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        $this->conn->execute('DELETE FROM catalog_product_category');
        $this->conn->execute("DELETE FROM catalog_products WHERE sku = 'PCA-SKU-1'");
        $this->conn->execute("DELETE FROM catalog_categories WHERE name = 'PCA Test Category'");
    }
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('it persists and reads back the assignment position', function (): void {
    /** @var ProductCategoryAssignmentRepository $repository */
    $repository = $this->repository;

    $assignment = new ProductCategoryAssignment();
    $assignment->productId = $this->productId;
    $assignment->categoryId = $this->categoryId;
    $assignment->position = 5;

    $repository->save($assignment);

    expect($assignment->id)->not->toBeNull();

    /** @var ProductCategoryAssignmentRepository $repository */
    $found = $repository->find($assignment->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($assignment->id)
        ->and($found->position)->toBe(5);
})->group('integration-destructive');
