<?php

declare(strict_types=1);

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Exceptions\DuplicateSkuException;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;
use Markommerce\Catalog\Services\ProductService;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;

it('creates a product with a unique sku', function (): void {
    $repository = new FakeProductRepository();
    $service = new ProductService(productRepository: $repository);

    $product = $service->createProduct(sku: 'PROD-001', name: 'Test Product');

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->sku)->toBe('PROD-001');
});

it('persists the created product through the repository', function (): void {
    $repository = new FakeProductRepository();
    $service = new ProductService(productRepository: $repository);

    $service->createProduct(sku: 'PROD-001', name: 'Test Product');

    expect($repository->products)->toHaveCount(1);
    expect(array_values($repository->products)[0]->sku)->toBe('PROD-001');
});

it('returns a Product carrying the given sku name and description', function (): void {
    $repository = new FakeProductRepository();
    $service = new ProductService(productRepository: $repository);

    $product = $service->createProduct(sku: 'PROD-001', name: 'Test Product', description: 'A great product');

    expect($product->sku)->toBe('PROD-001')
        ->and($product->name)->toBe('Test Product')
        ->and($product->description)->toBe('A great product');
});

it('throws DuplicateSkuException when creating a product whose sku already exists', function (): void {
    $repository = new FakeProductRepository();
    $service = new ProductService(productRepository: $repository);

    $service->createProduct(sku: 'PROD-001', name: 'First Product');

    expect(fn () => $service->createProduct(sku: 'PROD-001', name: 'Second Product'))
        ->toThrow(DuplicateSkuException::class);
});

it('converts a repository uniqueness violation on save into a DuplicateSkuException', function (): void {
    $repository = new class extends FakeProductRepository {
        public function save(\Marko\Database\Entity\Entity $entity): void
        {
            throw RepositoryException::invalidEntityType(self::class, Product::class, $entity::class);
        }
    };
    $service = new ProductService(productRepository: $repository);

    expect(fn () => $service->createProduct(sku: 'PROD-001', name: 'Test Product'))
        ->toThrow(DuplicateSkuException::class);
});

it('retrieves an existing product by id', function (): void {
    $repository = new FakeProductRepository();
    $service = new ProductService(productRepository: $repository);

    $created = $service->createProduct(sku: 'PROD-001', name: 'Test Product');
    $retrieved = $service->getProduct($created->id);

    expect($retrieved)->toBeInstanceOf(Product::class)
        ->and($retrieved->id)->toBe($created->id)
        ->and($retrieved->sku)->toBe('PROD-001');
});

it('throws ProductNotFoundException when retrieving a product id that does not exist', function (): void {
    $repository = new FakeProductRepository();
    $service = new ProductService(productRepository: $repository);

    expect(fn () => $service->getProduct(999))
        ->toThrow(ProductNotFoundException::class);
});
