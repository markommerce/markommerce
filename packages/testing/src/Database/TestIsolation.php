<?php

declare(strict_types=1);

namespace Markommerce\Testing\Database;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Testing\DatabaseTestHelper;

/**
 * Per-test isolation lifecycle controller.
 *
 * Wraps each test in a transaction (Rollback mode, the default) or clears all
 * profile tables between tests (Truncate mode). Task 009's IntegrationTestCase
 * calls begin() in setUp and finish() in tearDown.
 *
 * CRITICAL: the connection passed to begin()/finish() MUST be the same object
 * that the booted store's container binds as ConnectionInterface::class
 * (i.e. DatabaseProvisioner::connection()). If they differ, rollback isolates
 * nothing — the transaction is on a different socket than the repositories.
 */
class TestIsolation
{
    private IsolationMode $currentMode;

    private ?DatabaseTestHelper $helper = null;

    /** @var array<string> */
    private array $tableNames = [];

    public function __construct(IsolationMode $mode = IsolationMode::Rollback)
    {
        $this->currentMode = $mode;
    }

    /**
     * Return the current isolation mode.
     */
    public function mode(): IsolationMode
    {
        return $this->currentMode;
    }

    /**
     * Begin per-test isolation on the shared worker-clone connection.
     *
     * In Rollback mode: begins a transaction.
     * In Truncate mode: stores the table list for use in finish().
     *
     * @param ConnectionInterface&TransactionInterface $connection The shared worker-clone connection
     * @param array<string> $tableNames Profile table names (from DatabaseProvisioner::tableNames())
     */
    public function begin(
        ConnectionInterface&TransactionInterface $connection,
        array $tableNames = [],
    ): void {
        $this->helper = new DatabaseTestHelper($connection);
        $this->tableNames = $tableNames;

        if ($this->currentMode === IsolationMode::Rollback) {
            $this->helper->beginTransaction();
        }
    }

    /**
     * Finish per-test isolation, resetting state for the next test.
     *
     * In Rollback mode: rolls back the transaction.
     * In Truncate mode: issues DELETE FROM on each profile table to clear rows.
     * Sequences (auto-increment IDs) are NOT reset in Truncate mode.
     */
    public function finish(): void
    {
        if ($this->helper === null) {
            return;
        }

        if ($this->currentMode === IsolationMode::Rollback) {
            $this->helper->rollback();
        } else {
            foreach ($this->tableNames as $table) {
                $this->helper->truncateTable($table);
            }
        }

        $this->helper = null;
        $this->tableNames = [];
    }
}
