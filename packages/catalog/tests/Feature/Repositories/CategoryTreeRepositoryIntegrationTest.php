<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Repositories;

use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catTreeRepoVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function makeCategoryTree(string $code, string $name, bool $isDefault = false): CategoryTree
{
    $tree = new CategoryTree();
    $tree->code = $code;
    $tree->name = $name;
    $tree->isDefault = $isDefault;

    return $tree;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('finds a tree by its unique code', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catTreeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $repository */
        $repository = $store->get(CategoryTreeRepository::class);

        $tree = makeCategoryTree('sale', 'Sale Tree');
        $repository->save($tree);

        $found = $repository->findByCode('sale');

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('sale')
            ->and($found->name)->toBe('Sale Tree');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns null when finding by an unknown code', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catTreeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $repository */
        $repository = $store->get(CategoryTreeRepository::class);

        $result = $repository->findByCode('nonexistent');

        expect($result)->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('finds the default tree when one exists', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catTreeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $repository */
        $repository = $store->get(CategoryTreeRepository::class);

        $tree = makeCategoryTree('default', 'Default Tree', true);
        $repository->save($tree);

        $nonDefault = makeCategoryTree('other', 'Other Tree', false);
        $repository->save($nonDefault);

        $found = $repository->findDefault();

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('default')
            ->and($found->isDefault)->toBeTrue();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('throws DefaultTreeMissingException when no default tree exists', function (): void {
    TestConnection::skipIfUnavailable();

    $profile  = StoreProfile::simple(catTreeRepoVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var CategoryTreeRepository $repository */
        $repository = $store->get(CategoryTreeRepository::class);

        $tree = makeCategoryTree('nodefs', 'No Default Tree', false);
        $repository->save($tree);

        expect(fn () => $repository->findDefault())
            ->toThrow(DefaultTreeMissingException::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
