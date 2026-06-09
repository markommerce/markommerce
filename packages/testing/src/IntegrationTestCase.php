<?php

declare(strict_types=1);

namespace Markommerce\Testing;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\DatabaseProvisioner;
use Markommerce\Testing\Database\IsolationMode;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Database\TestIsolation;
use Markommerce\Testing\Profile\BootedStore;
use Markommerce\Testing\Profile\StoreProfile;

/**
 * Developer-facing integration test base for Markommerce packages.
 *
 * Orchestrates the full lifecycle:
 *  1. DatabaseProvisioner creates/re-uses the profile template + worker-clone.
 *  2. StoreProfile::boot() boots the container against the SAME shared connection.
 *  3. TestIsolation::begin() wraps each test (rollback by default, truncate opt-in).
 *  4. TestIsolation::finish() resets state after each test.
 *  5. DatabaseProvisioner::teardown() drops the worker clone at the end.
 *
 * Usage (Pest):
 *   uses(IntegrationTestCase::class);
 *   beforeAll(fn () => IntegrationTestCase::setUpClass(StoreProfile::simple($vendorDir)));
 *
 * Usage (manual, as in task's own tests):
 *   $testCase = new IntegrationTestCase($profile);
 *   $testCase->setUpIntegration();   // call in setUp / beforeEach
 *   // … test body …
 *   $testCase->tearDownIntegration(); // call in tearDown / afterEach
 *   $testCase->tearDownClass();       // call once after all tests
 */
class IntegrationTestCase
{
    /** The booted store for the current test. Accessible from test bodies. */
    public BootedStore $store;

    private DatabaseProvisioner $provisioner;

    private TestIsolation $isolation;

    public function __construct(
        private readonly StoreProfile $profile,
        private readonly IsolationMode $isolationMode = IsolationMode::Rollback,
    ) {
        $this->provisioner = new DatabaseProvisioner(new AdminConnection(), $this->profile);
        $this->isolation = new TestIsolation($this->isolationMode);
    }

    /**
     * Skip the current test when the database environment variables are missing.
     *
     * Delegates to TestConnection::skipIfUnavailable() so callers do not need
     * to import TestConnection directly.
     */
    public static function skipIfUnavailable(): void
    {
        TestConnection::skipIfUnavailable();
    }

    /**
     * Set up the integration test lifecycle for one test.
     *
     * - Ensures the profile template and worker clone exist.
     * - Boots the store on the shared worker-clone connection.
     * - Begins per-test isolation (rollback or truncate).
     *
     * Call this in Pest's beforeEach() or PHPUnit's setUp().
     */
    public function setUpIntegration(): void
    {
        $this->provisioner->ensureTemplate();
        $this->provisioner->ensureWorkerClone();

        $connection = $this->provisioner->connection();

        $this->store = $this->profile->boot($connection);

        /** @var ConnectionInterface&TransactionInterface $transactional */
        $transactional = $connection;

        $this->isolation = new TestIsolation($this->isolationMode);
        $this->isolation->begin($transactional, $this->provisioner->tableNames());
    }

    /**
     * Tear down per-test isolation.
     *
     * In Rollback mode: rolls back the wrapping transaction.
     * In Truncate mode: issues DELETE FROM on all profile tables.
     *
     * Call this in Pest's afterEach() or PHPUnit's tearDown().
     */
    public function tearDownIntegration(): void
    {
        $this->isolation->finish();
    }

    /**
     * Drop the worker-clone database.
     *
     * Call this once after all tests in the test class/file are done.
     * In Pest, call it in afterAll(); in PHPUnit, call it in tearDownAfterClass().
     */
    public function tearDownClass(): void
    {
        $this->provisioner->teardown();
    }

    /**
     * Convenience accessor: resolve a service from the booted store container.
     */
    public function get(string $id): mixed
    {
        return $this->store->get($id);
    }

    /**
     * Convenience accessor: run a closure within an active market/locale scope.
     */
    public function inScope(
        ?string $market,
        ?string $locale,
        callable $fn,
    ): void {
        $this->store->inScope($market, $locale, $fn);
    }
}
