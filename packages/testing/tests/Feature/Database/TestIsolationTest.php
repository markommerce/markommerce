<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature\Database;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\DatabaseProvisioner;
use Markommerce\Testing\Database\IsolationMode;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Database\TestIsolation;
use Markommerce\Testing\Profile\StoreProfile;

function testIsolationVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

it('defaults to rollback isolation mode', function (): void {
    expect(IsolationMode::Rollback->value)->toBe('rollback');
    expect(IsolationMode::Truncate->value)->toBe('truncate');

    $isolation = new TestIsolation();
    expect($isolation->mode())->toBe(IsolationMode::Rollback);
});

it('rolls back row changes between tests in rollback mode', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(testIsolationVendorDir());
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();
        $provisioner->ensureWorkerClone();

        $connection = $provisioner->connection();

        // Boot the store on the shared connection so repositories use the same connection
        $store = $profile->boot($connection);

        // Bind a no-op EventDispatcherInterface so the repository can be instantiated.
        // In the full app this is done by Application; in test containers it must be explicit.
        $noOpDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(Event $event): void {}
        };
        $store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $productRepo */
        $productRepo = $store->get(ProductRepositoryInterface::class);

        $isolation = new TestIsolation(IsolationMode::Rollback);

        // --- Simulated test 1: write a row then roll back ---
        $isolation->begin($connection, $provisioner->tableNames());

        $product = new Product();
        $product->sku = 'ISO-TEST-001';
        $product->name = 'Isolation Test Product';
        $productRepo->save($product);

        // Row is visible within the open transaction
        $found = $productRepo->findBySku('ISO-TEST-001');
        expect($found)->not->toBeNull();
        expect($found?->sku)->toBe('ISO-TEST-001');

        $isolation->finish();

        // --- After rollback, row must be gone ---
        $found = $productRepo->findBySku('ISO-TEST-001');
        expect($found)->toBeNull();
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('resets profile tables between tests in truncate mode', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(testIsolationVendorDir());
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();
        $provisioner->ensureWorkerClone();

        $connection = $provisioner->connection();
        $store = $profile->boot($connection);

        $noOpDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(Event $event): void {}
        };
        $store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $productRepo */
        $productRepo = $store->get(ProductRepositoryInterface::class);

        $isolation = new TestIsolation(IsolationMode::Truncate);

        // --- Simulated test 1: write a row ---
        $isolation->begin($connection, $provisioner->tableNames());

        $product = new Product();
        $product->sku = 'TRUNCATE-TEST-001';
        $product->name = 'Truncate Mode Product';
        $productRepo->save($product);

        // Row is visible
        $found = $productRepo->findBySku('TRUNCATE-TEST-001');
        expect($found)->not->toBeNull();

        // finish() deletes all rows (DELETE FROM) — no rollback since no wrapping transaction
        $isolation->finish();

        // --- After truncate, the row must be gone ---
        $found = $productRepo->findBySku('TRUNCATE-TEST-001');
        expect($found)->toBeNull();
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('begins and rolls back on the same connection instance the container resolves', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(testIsolationVendorDir());
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();
        $provisioner->ensureWorkerClone();

        $connection = $provisioner->connection();
        $store = $profile->boot($connection);

        // The connection the container resolves must be the SAME object as the provisioner's
        $containerConnection = $store->get(ConnectionInterface::class);
        expect($containerConnection)->toBe($connection);

        $noOpDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(Event $event): void {}
        };
        $store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        /** @var ProductRepositoryInterface $productRepo */
        $productRepo = $store->get(ProductRepositoryInterface::class);

        $isolation = new TestIsolation(IsolationMode::Rollback);

        // Begin on the same connection the container resolves
        $isolation->begin($connection, $provisioner->tableNames());

        // The connection must now be in a transaction
        expect($connection)->toBeInstanceOf(TransactionInterface::class);
        /** @var TransactionInterface $transactionalConnection */
        $transactionalConnection = $connection;
        expect($transactionalConnection->inTransaction())->toBeTrue();

        // Write via the container-resolved repository — uses the SAME connection
        $product = new Product();
        $product->sku = 'ISO-SAME-CONN-001';
        $product->name = 'Same Connection Test Product';
        $productRepo->save($product);

        $isolation->finish();

        // After rollback on the shared connection, the repository (also using
        // that connection) must see no row
        $found = $productRepo->findBySku('ISO-SAME-CONN-001');
        expect($found)->toBeNull();
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('selects truncate mode for code that opens its own transaction', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = StoreProfile::simple(testIsolationVendorDir());
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();
        $provisioner->ensureWorkerClone();

        $connection = $provisioner->connection();
        $store = $profile->boot($connection);

        $noOpDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(Event $event): void {}
        };
        $store->container()->instance(EventDispatcherInterface::class, $noOpDispatcher);

        $isolation = new TestIsolation(IsolationMode::Truncate);

        // Begin in Truncate mode — does NOT wrap in a transaction
        $isolation->begin($connection, $provisioner->tableNames());

        // Verify no wrapping transaction is active
        expect($connection)->toBeInstanceOf(TransactionInterface::class);
        /** @var TransactionInterface $transactionalConnection */
        $transactionalConnection = $connection;
        expect($transactionalConnection->inTransaction())->toBeFalse();

        // Code under test opens its own transaction — this would throw
        // nestedTransactionNotSupported if TestIsolation had begun a wrapping transaction
        $transactionalConnection->beginTransaction();
        $connection->execute(
            "INSERT INTO catalog_products (sku, name) VALUES (?, ?)",
            ['OWN-TX-001', 'Own Transaction Product'],
        );
        $transactionalConnection->commit();

        // After the code's own transaction commits, the row is visible
        $rows = $connection->query(
            "SELECT sku FROM catalog_products WHERE sku = ?",
            ['OWN-TX-001'],
        );
        expect($rows)->toHaveCount(1);

        // finish() clears the tables via DELETE FROM (truncate mode) — no rollback
        $isolation->finish();

        // Row must be gone
        $rows = $connection->query(
            "SELECT sku FROM catalog_products WHERE sku = ?",
            ['OWN-TX-001'],
        );
        expect($rows)->toHaveCount(0);
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');
