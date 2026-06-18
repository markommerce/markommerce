<?php

declare(strict_types=1);

use Marko\Database\Seed\Seeder;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogScope\Entity\CategoryScopedOverrides;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\CatalogScope\Seed\CatalogLocaleSeeder;

function makeCatalogLocaleSeeder(
    ?FakeProductRepository $productRepository = null,
    ?FakeCategoryRepository $categoryRepository = null,
): CatalogLocaleSeeder {
    return new CatalogLocaleSeeder(
        productRepository: $productRepository ?? new FakeProductRepository(),
        categoryRepository: $categoryRepository ?? new FakeCategoryRepository(),
    );
}

it(
    'CatalogLocaleSeeder runs after CatalogSeeder, fetches all products/categories, attaches scoped-override companions with German and French translations, and saves them through the catalog repositories',
    function (): void {
        $productRepository = new FakeProductRepository();
        $categoryRepository = new FakeCategoryRepository();

        // Pre-seed some products and categories (simulating CatalogSeeder having run)
        $product1 = new Product();
        $product1->sku = 'SKU-000001';
        $product1->name = 'Product 1';
        $product1->description = 'Description for product 1';
        $productRepository->save($product1);

        $product2 = new Product();
        $product2->sku = 'SKU-000002';
        $product2->name = 'Product 2';
        $product2->description = 'Description for product 2';
        $productRepository->save($product2);

        $category1 = new Category();
        $category1->name = 'Category 1';
        $category1->description = 'Description for category 1';
        $categoryRepository->save($category1);

        // Run the seeder
        $seeder = makeCatalogLocaleSeeder(
            productRepository: $productRepository,
            categoryRepository: $categoryRepository,
        );
        $seeder->run();

        // Verify products have scoped-override companions with German and French translations
        $savedProduct = $productRepository->find($product1->id);

        expect($savedProduct)->not->toBeNull();

        $productOverrides = $savedProduct->companion(ProductScopedOverrides::class);

        expect($productOverrides)->not->toBeNull()
            ->and($productOverrides)->toBeInstanceOf(ProductScopedOverrides::class)
            ->and($productOverrides->override('locale:de', 'name'))->toBe('Produkt 1')
            ->and($productOverrides->override('locale:de', 'description'))->toBe('Beschreibung für Produkt 1')
            ->and($productOverrides->override('locale:fr', 'name'))->toBe('Produit 1')
            ->and($productOverrides->override('locale:fr', 'description'))->toBe('Description pour le produit 1');

        // Verify categories have scoped-override companions
        $savedCategory = $categoryRepository->find($category1->id);

        expect($savedCategory)->not->toBeNull();

        $categoryOverrides = $savedCategory->companion(CategoryScopedOverrides::class);

        expect($categoryOverrides)->not->toBeNull()
            ->and($categoryOverrides)->toBeInstanceOf(CategoryScopedOverrides::class)
            ->and($categoryOverrides->override('locale:de', 'name'))->toBe('Kategorie 1')
            ->and($categoryOverrides->override('locale:de', 'description'))->toBe('Beschreibung für Kategorie 1')
            ->and($categoryOverrides->override('locale:fr', 'name'))->toBe('Catégorie 1')
            ->and($categoryOverrides->override('locale:fr', 'description'))->toBe('Description pour la catégorie 1');
    },
);
