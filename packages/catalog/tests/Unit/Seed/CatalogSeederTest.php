<?php

declare(strict_types=1);

use Marko\Database\Seed\Seeder;
use Markommerce\Catalog\Seed\CatalogSeeder;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Scope\Storage\DefaultScopeGuard;

beforeEach(function (): void {
    DefaultScopeGuard::configure(['locale' => 'default']);
});

afterEach(function (): void {
    DefaultScopeGuard::reset();
});

function makeSeeder(): CatalogSeeder
{
    return new CatalogSeeder(
        productRepository: new FakeProductRepository(),
        categoryRepository: new FakeCategoryRepository(),
        assignmentRepository: new FakeProductCategoryAssignmentRepository(),
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
    $seeder = new CatalogSeeder(
        productRepository: new FakeProductRepository(),
        categoryRepository: $categoryRepository,
        assignmentRepository: new FakeProductCategoryAssignmentRepository(),
    );

    $seeder->run();

    expect($categoryRepository->categories)->toHaveCount(5);
});

it('seeds the configured number of products', function (): void {
    $productRepository = new FakeProductRepository();
    $seeder = new CatalogSeeder(
        productRepository: $productRepository,
        categoryRepository: new FakeCategoryRepository(),
        assignmentRepository: new FakeProductCategoryAssignmentRepository(),
    );

    $seeder->run();

    expect($productRepository->products)->toHaveCount(30);
});

it('gives every seeded product a unique sku', function (): void {
    $productRepository = new FakeProductRepository();
    $seeder = new CatalogSeeder(
        productRepository: $productRepository,
        categoryRepository: new FakeCategoryRepository(),
        assignmentRepository: new FakeProductCategoryAssignmentRepository(),
    );

    $seeder->run();

    $skus = array_map(fn ($p) => $p->sku, array_values($productRepository->products));

    expect(array_unique($skus))->toHaveCount(count($skus));
});

it('assigns seeded products to seeded categories', function (): void {
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();
    $seeder = new CatalogSeeder(
        productRepository: new FakeProductRepository(),
        categoryRepository: new FakeCategoryRepository(),
        assignmentRepository: $assignmentRepository,
    );

    $seeder->run();

    expect($assignmentRepository->assignments)->not->toBeEmpty();
});

it('writes locale-scoped name overrides on seeded products using the de and fr locales', function (): void {
    $productRepository = new FakeProductRepository();
    $seeder = new CatalogSeeder(
        productRepository: $productRepository,
        categoryRepository: new FakeCategoryRepository(),
        assignmentRepository: new FakeProductCategoryAssignmentRepository(),
    );

    $seeder->run();

    $products = array_values($productRepository->products);
    $hasDeOverride = array_any($products, fn ($p) => $p->hasOverride('locale:de', 'name'));
    $hasFrOverride = array_any($products, fn ($p) => $p->hasOverride('locale:fr', 'name'));

    expect($hasDeOverride)->toBeTrue()
        ->and($hasFrOverride)->toBeTrue();
});

it('writes locale-scoped overrides on seeded categories', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $seeder = new CatalogSeeder(
        productRepository: new FakeProductRepository(),
        categoryRepository: $categoryRepository,
        assignmentRepository: new FakeProductCategoryAssignmentRepository(),
    );

    $seeder->run();

    $categories = array_values($categoryRepository->categories);
    $hasDeOverride = array_any($categories, fn ($c) => $c->hasOverride('locale:de', 'name'));
    $hasFrOverride = array_any($categories, fn ($c) => $c->hasOverride('locale:fr', 'name'));

    expect($hasDeOverride)->toBeTrue()
        ->and($hasFrOverride)->toBeTrue();
});
