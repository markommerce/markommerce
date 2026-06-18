<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature\Database;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\DatabaseProvisioner;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Profile\StoreProfile;

function dbProvisionerVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

/**
 * Return a profile with a UNIQUE key not shared by any other test.
 *
 * Uses catalog + scope + pgsql so the profile key (md5 of sorted module names)
 * differs from StoreProfile::simple() and StoreProfile::singleMarketTwoLocales().
 * This prevents DatabaseProvisionerTest from destroying the shared template that
 * other parallel tests depend on.
 */
function dbProvisionerTestProfile(): StoreProfile
{
    return StoreProfile::of(
        dbProvisionerVendorDir(),
        'markommerce/catalog',
        'marko/database-pgsql',
        'markommerce/scope',
    );
}

it('builds a profile template database once with the profile schema', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = dbProvisionerTestProfile();
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();

        $admin = new AdminConnection();
        $templateName = $provisioner->templateName();

        $result = $admin->query(
            'SELECT datname FROM pg_database WHERE datname = :name',
            ['name' => $templateName],
        );
        expect($result)->toHaveCount(1);

        // Template must have the profile's schema tables
        $conn = $admin->connectionFor($templateName);
        $tables = $conn->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );
        $tableNames = array_map(static fn (array $row): string => (string) $row['table_name'], $tables);

        expect($tableNames)->toContain('catalog_products');
        expect($tableNames)->toContain('catalog_categories');
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('clones a per-worker database from the profile template', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = dbProvisionerTestProfile();
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $cloneName = $provisioner->workerCloneName();
        $provisioner->ensureTemplate();
        $provisioner->ensureWorkerClone();

        $admin = new AdminConnection();
        $result = $admin->query(
            'SELECT datname FROM pg_database WHERE datname = :name',
            ['name' => $cloneName],
        );
        expect($result)->toHaveCount(1);

        // Clone must have the schema
        $conn = $admin->connectionFor($cloneName);
        $tables = $conn->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );
        $tableNames = array_map(static fn (array $row): string => (string) $row['table_name'], $tables);
        expect($tableNames)->toContain('catalog_products');
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('does not rebuild the template when it already exists', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = dbProvisionerTestProfile();
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();

        // Add a sentinel table manually to detect if template is rebuilt
        $admin = new AdminConnection();
        $templateConn = $admin->connectionFor($provisioner->templateName());
        $templateConn->execute('CREATE TABLE rebuild_sentinel (id SERIAL PRIMARY KEY)');
        $templateConn->disconnect();

        // Call ensureTemplate again — should NOT rebuild (sentinel must still be there)
        $provisioner2 = new DatabaseProvisioner(new AdminConnection(), $profile);
        $provisioner2->ensureTemplate();

        $templateConn2 = (new AdminConnection())->connectionFor($provisioner->templateName());
        $tables = $templateConn2->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );
        $tableNames = array_map(static fn (array $row): string => (string) $row['table_name'], $tables);

        expect($tableNames)->toContain('rebuild_sentinel');
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('isolates two profiles into separate databases with different schemas', function (): void {
    TestConnection::skipIfUnavailable();

    $simpleProfile = dbProvisionerTestProfile();
    $twoMarketsProfile = StoreProfile::twoMarketsTwoLocales(dbProvisionerVendorDir());

    $simpleProvisioner = new DatabaseProvisioner(new AdminConnection(), $simpleProfile);
    $twoMarketsProvisioner = new DatabaseProvisioner(new AdminConnection(), $twoMarketsProfile);

    try {
        $simpleProvisioner->ensureTemplate();
        $twoMarketsProvisioner->ensureTemplate();

        expect($simpleProvisioner->templateName())->not->toBe($twoMarketsProvisioner->templateName());

        // Test profile must NOT have catalog_category_tree_market_assignments
        $simpleConn = (new AdminConnection())->connectionFor($simpleProvisioner->templateName());
        $simpleTables = $simpleConn->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );
        $simpleTableNames = array_map(static fn (array $row): string => (string) $row['table_name'], $simpleTables);
        expect($simpleTableNames)->not->toContain('catalog_category_tree_market_assignments');

        // Two-markets profile MUST have catalog_category_tree_market_assignments
        $twoMarketsConn = (new AdminConnection())->connectionFor($twoMarketsProvisioner->templateName());
        $twoMarketsTables = $twoMarketsConn->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );
        $twoMarketsTableNames = array_map(
            static fn (array $row): string => (string) $row['table_name'],
            $twoMarketsTables,
        );
        expect($twoMarketsTableNames)->toContain('catalog_category_tree_market_assignments');
    } finally {
        $simpleProvisioner->teardown();
        $twoMarketsProvisioner->teardown();
        (new AdminConnection())->dropDatabase($simpleProvisioner->templateName());
        (new AdminConnection())->dropDatabase($twoMarketsProvisioner->templateName());
    }
})->group('integration-destructive');

it('falls back to a single-worker token when not running under paratest', function (): void {
    // Remove paratest env vars to simulate non-parallel environment
    $originalToken = getenv('TEST_TOKEN');
    $originalParatest = getenv('PARATEST');
    putenv('TEST_TOKEN');
    putenv('PARATEST');

    try {
        $profile = StoreProfile::simple(dbProvisionerVendorDir());
        $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

        $cloneName = $provisioner->workerCloneName();

        // Must not throw and must contain a valid DB name (no hyphens/spaces)
        expect($cloneName)->toBeString();
        expect($cloneName)->toMatch('/^[a-zA-Z_][a-zA-Z0-9_]*$/');
    } finally {
        if ($originalToken !== false) {
            putenv("TEST_TOKEN=$originalToken");
        }

        if ($originalParatest !== false) {
            putenv("PARATEST=$originalParatest");
        }
    }
});

it('exposes one shared connection instance bound to the worker clone database', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = dbProvisionerTestProfile();
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    try {
        $provisioner->ensureTemplate();
        $provisioner->ensureWorkerClone();

        $conn1 = $provisioner->connection();
        $conn2 = $provisioner->connection();

        // Must be the exact same object
        expect($conn1)->toBe($conn2);
        expect($conn1)->toBeInstanceOf(ConnectionInterface::class);

        // Must be connected to the worker clone
        $result = $conn1->query('SELECT current_database() AS db');
        expect($result[0]['db'])->toBe($provisioner->workerCloneName());
    } finally {
        $provisioner->teardown();
        (new AdminConnection())->dropDatabase($provisioner->templateName());
    }
})->group('integration-destructive');

it('drops worker clone databases on teardown', function (): void {
    TestConnection::skipIfUnavailable();

    $profile = dbProvisionerTestProfile();
    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);

    $provisioner->ensureTemplate();
    $provisioner->ensureWorkerClone();
    $cloneName = $provisioner->workerCloneName();

    // Confirm clone exists
    $admin = new AdminConnection();
    $before = $admin->query(
        'SELECT datname FROM pg_database WHERE datname = :name',
        ['name' => $cloneName],
    );
    expect($before)->toHaveCount(1);

    $provisioner->teardown();

    // Clone must be gone
    $after = $admin->query(
        'SELECT datname FROM pg_database WHERE datname = :name',
        ['name' => $cloneName],
    );
    expect($after)->toHaveCount(0);

    // Cleanup template
    (new AdminConnection())->dropDatabase($provisioner->templateName());
})->group('integration-destructive');
