<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature\Integration;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Testing\Database\IsolationMode;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\BootedStore;
use Markommerce\Testing\Profile\StoreProfile;
use PHPUnit\Framework\SkippedWithMessageException;

function integrationTestCaseVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

dataset('storeProfiles', static function (): array {
    $vendorDir = integrationTestCaseVendorDir();

    return [
        'simple' => [StoreProfile::simple($vendorDir)],
        'two-locales' => [StoreProfile::singleMarketTwoLocales($vendorDir)],
        'two-markets' => [StoreProfile::twoMarketsTwoLocales($vendorDir)],
    ];
});

it('rolls back changes so each test starts clean', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(integrationTestCaseVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        // Wire no-op event dispatcher so ProductRepository can be instantiated
        $noOpDispatcher = new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        };
        $testCase->store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);

        $product = new Product();
        $product->sku = 'ROLLBACK-TEST-001';
        $product->name = 'Rollback Test Product';
        $repo->save($product);

        // Row is visible within the open isolation window
        expect($repo->findBySku('ROLLBACK-TEST-001'))->not->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
    }

    // Second simulated test: setup fresh isolation, row must be absent
    $testCase->setUpIntegration();

    try {
        $noOpDispatcher = new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        };
        $testCase->store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);

        expect($repo->findBySku('ROLLBACK-TEST-001'))->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('resolves real repositories from the booted store that hit the worker database', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(integrationTestCaseVendorDir());
    $testCase = new IntegrationTestCase($profile);
    $testCase->setUpIntegration();

    try {
        $noOpDispatcher = new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        };
        $testCase->store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);

        $product = new Product();
        $product->sku = 'REPO-HIT-001';
        $product->name = 'Repository Hit Test';
        $repo->save($product);

        $found = $repo->findBySku('REPO-HIT-001');
        expect($found)->not->toBeNull();
        expect($found?->sku)->toBe('REPO-HIT-001');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it(
    'runs the same test body across all profiles via the storeProfiles dataset',
    function (StoreProfile $profile): void {
        TestConnection::skipIfUnavailable();

        $testCase = new IntegrationTestCase($profile);
        $testCase->setUpIntegration();

        try {
            expect($testCase->store)->toBeInstanceOf(BootedStore::class);
            expect($testCase->store->get(ConnectionInterface::class))->toBeInstanceOf(ConnectionInterface::class);
        } finally {
            $testCase->tearDownIntegration();
        }
    },
)
    ->with('storeProfiles')
    ->group('integration-destructive');

it('skips when the database is unavailable', function (): void {
    // Temporarily remove DB env vars to simulate unavailable DB
    $savedVars = [];
    $requiredVars = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    foreach ($requiredVars as $var) {
        $savedVars[$var] = getenv($var);
        putenv($var);
    }

    try {
        TestConnection::skipIfUnavailable();

        // Should have been skipped — fail if we reach here
        expect(false)->toBeTrue('Test should have been skipped due to missing DB env vars');
    } catch (SkippedWithMessageException $e) {
        expect($e->getMessage())->toContain('Integration test requires env vars');
    } finally {
        foreach ($savedVars as $var => $value) {
            if ($value !== false && $value !== '') {
                putenv("$var=$value");
            } else {
                putenv($var);
            }
        }
    }
});

it('runs a test in truncate isolation mode when selected', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(integrationTestCaseVendorDir());
    $testCase = new IntegrationTestCase($profile, IsolationMode::Truncate);
    $testCase->setUpIntegration();

    try {
        $noOpDispatcher = new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        };
        $testCase->store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);

        $product = new Product();
        $product->sku = 'TRUNCATE-MODE-001';
        $product->name = 'Truncate Mode Test';
        $repo->save($product);

        expect($repo->findBySku('TRUNCATE-MODE-001'))->not->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
    }

    // After tearDown with truncate mode, the row must be absent
    $testCase->setUpIntegration();

    try {
        $noOpDispatcher = new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        };
        $testCase->store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);

        expect($repo->findBySku('TRUNCATE-MODE-001'))->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');
