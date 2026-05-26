<?php

declare(strict_types=1);

use Marko\Database\Seed\Seeder;
use Markommerce\Catalog\Seed\CatalogSeeder;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeMarketAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;

function makeSeederCategoryTreeService(
    ?FakeCategoryTreeRepository $treeRepository = null,
    ?FakeCategoryTreeMarketAssignmentRepository $marketAssignmentRepository = null,
    ?FakeCategoryTreeNodeRepository $nodeRepository = null,
    ?FakeCategoryRepository $categoryRepository = null,
): CategoryTreeService {
    return new CategoryTreeService(
        categoryTreeRepository: $treeRepository ?? new FakeCategoryTreeRepository(),
        categoryTreeMarketAssignmentRepository: $marketAssignmentRepository ?? new FakeCategoryTreeMarketAssignmentRepository(),
        categoryTreeNodeRepository: $nodeRepository ?? new FakeCategoryTreeNodeRepository(),
        categoryRepository: $categoryRepository ?? new FakeCategoryRepository(),
    );
}

function makeCatalogSeeder(
    ?FakeProductRepository $productRepository = null,
    ?FakeCategoryRepository $categoryRepository = null,
    ?FakeProductCategoryAssignmentRepository $assignmentRepository = null,
    ?CategoryTreeService $categoryTreeService = null,
    ?FakeCategoryTreeNodeRepository $categoryTreeNodeRepository = null,
): CatalogSeeder {
    $sharedCategoryRepository = $categoryRepository ?? new FakeCategoryRepository();

    return new CatalogSeeder(
        productRepository: $productRepository ?? new FakeProductRepository(),
        categoryRepository: $sharedCategoryRepository,
        assignmentRepository: $assignmentRepository ?? new FakeProductCategoryAssignmentRepository(),
        categoryTreeService: $categoryTreeService ?? makeSeederCategoryTreeService(categoryRepository: $sharedCategoryRepository),
        categoryTreeNodeRepository: $categoryTreeNodeRepository ?? new FakeCategoryTreeNodeRepository(),
    );
}

it('is annotated with the Seeder attribute named catalog', function (): void {
    $reflection = new ReflectionClass(CatalogSeeder::class);
    $attributes = $reflection->getAttributes(Seeder::class);

    expect($attributes)->toHaveCount(1);

    $seeder = $attributes[0]->newInstance();

    expect($seeder->name)->toBe('catalog');
});

it('seeds the configured number of categories', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $seeder = makeCatalogSeeder(categoryRepository: $categoryRepository);

    $seeder->run();

    expect($categoryRepository->categories)->toHaveCount(5);
});

it('seeds the configured number of products', function (): void {
    $productRepository = new FakeProductRepository();
    $seeder = makeCatalogSeeder(productRepository: $productRepository);

    $seeder->run();

    expect($productRepository->products)->toHaveCount(5000);
});

it('gives every seeded product a unique sku', function (): void {
    $productRepository = new FakeProductRepository();
    $seeder = makeCatalogSeeder(productRepository: $productRepository);

    $seeder->run();

    $skus = array_map(fn ($p) => $p->sku, array_values($productRepository->products));

    expect(array_unique($skus))->toHaveCount(count($skus));
});

it('assigns seeded products to seeded categories', function (): void {
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();
    $seeder = makeCatalogSeeder(assignmentRepository: $assignmentRepository);

    $seeder->run();

    expect($assignmentRepository->assignments)->not->toBeEmpty();
});

it('existing seeder unit tests continue to pass with the new CategoryTreeService dependency', function (): void {
    $seeder = makeCatalogSeeder();

    expect($seeder)->toBeInstanceOf(CatalogSeeder::class);
});

it('seeds plain Product rows with no setOverride() calls from CatalogSeeder', function (): void {
    $productRepository = new FakeProductRepository();
    $seeder = makeCatalogSeeder(productRepository: $productRepository);

    $seeder->run();

    $products = array_values($productRepository->products);

    expect($products)->not->toBeEmpty();

    foreach ($products as $product) {
        expect(method_exists($product, 'setOverride'))->toBeFalse();
        expect($product->name)->not->toBeEmpty();
        expect($product->sku)->not->toBeEmpty();
    }
});

it('seeds plain Category rows with no setOverride() calls from CatalogSeeder', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $seeder = makeCatalogSeeder(categoryRepository: $categoryRepository);

    $seeder->run();

    $categories = array_values($categoryRepository->categories);

    expect($categories)->not->toBeEmpty();

    foreach ($categories as $category) {
        expect(method_exists($category, 'setOverride'))->toBeFalse();
        expect($category->name)->not->toBeEmpty();
    }
});

it('has no Markommerce\\Scope namespace imports in CatalogSeeder.php', function (): void {
    $file = (new ReflectionClass(CatalogSeeder::class))->getFileName();
    $contents = file_get_contents($file);

    expect($contents)->not->toContain('Markommerce\\Scope');
});
