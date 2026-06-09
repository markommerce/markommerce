<?php

declare(strict_types=1);

namespace Markommerce\Testing\Database;

/**
 * Per-test database isolation strategy.
 *
 * Rollback (default): wraps each test in a transaction and rolls it back at the
 * end. Fast — no DDL, no I/O beyond begin/rollback. Requires that the code
 * under test does NOT open its own transaction (Postgres has no savepoints, so a
 * nested beginTransaction() would throw nestedTransactionNotSupported).
 *
 * Truncate: instead of a wrapping transaction, clears all profile tables between
 * tests using DatabaseTestHelper::truncateTable() which issues DELETE FROM (not
 * TRUNCATE). Behaviour: rows are deleted but sequences are NOT reset (auto-
 * increment IDs keep incrementing across tests). Use this mode for code paths
 * that open their own transactions (e.g. batch indexers that call transaction()).
 */
enum IsolationMode: string
{
    case Rollback = 'rollback';
    case Truncate = 'truncate';
}
