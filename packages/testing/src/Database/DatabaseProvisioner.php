<?php

declare(strict_types=1);

namespace Markommerce\Testing\Database;

use Marko\Core\Module\ModuleManifest;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Testing\Profile\StoreProfile;
use Markommerce\Testing\Schema\SchemaProvisioner;

/**
 * Manages the database lifecycle for integration testing.
 *
 * Strategy:
 *   - One TEMPLATE database per store profile (name: marko_test_tmpl_<profileKey>).
 *     Built lazily once per test run: admin creates an empty DB, SchemaProvisioner
 *     writes the profile's schema into it, then the provisioning connection is closed
 *     so the template can be cloned. An advisory lock serialises concurrent workers.
 *
 *   - One CLONE database per parallel worker (name: marko_test_<profileKey>_<token>).
 *     Created via CREATE DATABASE … TEMPLATE … (a fast file copy) on first use and
 *     reused across all tests in that worker. Dropped on teardown().
 *
 * Profile key: MD5 of the sorted module names — deterministic, short, identifier-safe.
 */
class DatabaseProvisioner
{
    private const string DB_PREFIX = 'marko_test';

    /** Shared worker-clone connection, created once by ensureWorkerClone(). */
    private ?ConnectionInterface $workerConnection = null;

    /** Resolved table names from the profile's entity dirs.
     * @var array<string>|null
     */
    private ?array $tableNames = null;

    public function __construct(
        private readonly AdminConnection $adminConnection,
        private readonly StoreProfile $storeProfile,
    ) {}

    /**
     * Compute a stable key for this profile from its sorted module names.
     * Uses md5 to get a short, fixed-length, lowercase hex string.
     */
    public function profileKey(): string
    {
        $modules = $this->storeProfile->modules();
        $names = array_map(fn (ModuleManifest $m): string => $m->name, $modules);
        sort($names);

        return md5(implode(',', $names));
    }

    /**
     * The name of the template database for this profile.
     */
    public function templateName(): string
    {
        return self::DB_PREFIX . '_tmpl_' . $this->profileKey();
    }

    /**
     * The name of the worker-clone database for this profile and worker token.
     */
    public function workerCloneName(): string
    {
        $token = $this->workerToken();

        return self::DB_PREFIX . '_' . $this->profileKey() . '_' . $token;
    }

    /**
     * Build the profile template database if it does not already exist.
     *
     * Uses a Postgres session-level advisory lock on the admin connection so
     * concurrent ParaTest workers serialise and only one creates the template.
     *
     * IMPORTANT: the provisioning connection is disconnected before returning,
     * because Postgres cannot clone a template that has active sessions.
     */
    public function ensureTemplate(): void
    {
        $lockId = $this->profileLockId();
        $templateName = $this->templateName();

        // Acquire a session advisory lock — released on disconnect or explicit unlock.
        $this->adminConnection->query("SELECT pg_advisory_lock($lockId)");

        try {
            $exists = $this->adminConnection->query(
                'SELECT 1 FROM pg_database WHERE datname = :name',
                ['name' => $templateName],
            );

            if ($exists === []) {
                // Build the template: create DB, provision schema, then close.
                $this->adminConnection->createDatabase($templateName);
                $provisioningConn = $this->adminConnection->connectionFor($templateName);

                $provisioner = new SchemaProvisioner();
                $provisioner->provision($provisioningConn, $this->storeProfile->entityDirs());

                // Cache table names from this provisioning run
                $this->tableNames = $provisioner->tableNames($this->storeProfile->entityDirs());

                // Disconnect BEFORE any clone attempt — Postgres requirement.
                $provisioningConn->disconnect();
            }
        } finally {
            $this->adminConnection->query("SELECT pg_advisory_unlock($lockId)");
        }
    }

    /**
     * Create the per-worker clone from the template (if not yet created).
     *
     * Uses a fresh AdminConnection for the CREATE DATABASE statement so it is
     * never issued on a connection targeting the template or clone itself.
     *
     * Retries with exponential backoff to handle two transient failure modes:
     *   1. "source database is being accessed" — another worker is mid-clone.
     *   2. "template database does not exist" — another test dropped the template;
     *      ensureTemplate() is called to recreate it before retrying.
     */
    public function ensureWorkerClone(): void
    {
        $cloneName = $this->workerCloneName();

        $exists = $this->adminConnection->query(
            'SELECT 1 FROM pg_database WHERE datname = :name',
            ['name' => $cloneName],
        );

        if ($exists === []) {
            // Use a fresh admin connection for CREATE DATABASE so we never hold
            // an open session to the template while cloning it.
            // Retry with backoff to handle transient race conditions in parallel runs.
            $maxAttempts = 10;
            $lastException = null;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $cloneAdmin = new AdminConnection();
                    $cloneAdmin->createDatabaseFromTemplate($cloneName, $this->templateName());
                    $cloneAdmin->disconnect();
                    $lastException = null;
                    break;
                } catch (\Exception $e) {
                    $lastException = $e;

                    if ($attempt < $maxAttempts) {
                        // If the template was dropped (e.g. by a competing test), recreate it.
                        $this->ensureTemplate();

                        // Exponential backoff: 100ms, 200ms, 400ms, 800ms, …
                        usleep(100_000 * (2 ** ($attempt - 1)));
                    }
                }
            }

            if ($lastException !== null) {
                throw $lastException;
            }
        }

        if ($this->workerConnection === null) {
            $this->workerConnection = $this->adminConnection->connectionFor($cloneName);
        }
    }

    /**
     * The single shared ConnectionInterface bound to the worker-clone database.
     *
     * This is the SAME object that must be bound in the container and used by
     * the isolation layer (task 016) for transaction rollback.
     */
    public function connection(): ConnectionInterface
    {
        if ($this->workerConnection === null) {
            $this->ensureWorkerClone();
        }

        /** @var ConnectionInterface $connection */
        $connection = $this->workerConnection;

        return $connection;
    }

    /**
     * The list of table names provisioned by the profile's entity dirs.
     *
     * Used by the isolation layer (task 016) for truncate-based cleanup.
     *
     * @return array<string>
     */
    public function tableNames(): array
    {
        if ($this->tableNames === null) {
            $provisioner = new SchemaProvisioner();
            $this->tableNames = $provisioner->tableNames($this->storeProfile->entityDirs());
        }

        return $this->tableNames;
    }

    /**
     * Drop the worker-clone database and close the worker connection.
     *
     * The template is intentionally kept between test runs because it is
     * expensive to rebuild. Drop it manually if the schema changes.
     */
    public function teardown(): void
    {
        if ($this->workerConnection !== null) {
            $this->workerConnection->disconnect();
            $this->workerConnection = null;
        }

        $this->adminConnection->dropDatabase($this->workerCloneName());
    }

    /**
     * Derive the worker token from the ParaTest TEST_TOKEN env var.
     *
     * When running outside ParaTest (no TEST_TOKEN), fall back to '0'
     * so non-parallel runs use a predictable, single clone database.
     */
    private function workerToken(): string
    {
        $token = getenv('TEST_TOKEN');

        if ($token === false || $token === '') {
            return '0';
        }

        return $token;
    }

    /**
     * Derive a 32-bit signed integer lock ID from the profile key for
     * pg_advisory_lock. Truncates to PHP_INT_MAX range to stay in Postgres bounds.
     */
    private function profileLockId(): int
    {
        return (int) (hexdec(substr($this->profileKey(), 0, 8)) & 0x7FFFFFFF);
    }

}
