<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Tests\Feature;

require_once __DIR__ . '/Helpers/PostgresTestConnection.php';

use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\Config\PgSql\Tests\Feature\Helpers\PostgresTestConnection;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Returns a process-scoped unique table name to avoid collisions in parallel runs.
 */
function configValuesTableName(): string
{
    static $name = null;

    if ($name === null) {
        $name = 'config_values_test_' . bin2hex(random_bytes(8));
    }

    return $name;
}

// ─── Shared connection & lifecycle ────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $conn = new PostgresTestConnection();
    $tableName = configValuesTableName();

    // DROP IF EXISTS first (crash resilience from previous failed runs)
    $conn->execute(sprintf('DROP INDEX IF EXISTS "%s_overrides_gin"', $tableName));
    $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));

    $this->conn = $conn;
    $this->tableName = $tableName;
    $this->emitter = new ConfigValuesTableEmitter();
});

afterEach(function (): void {
    if (isset($this->conn) && isset($this->tableName)) {
        $conn = $this->conn;
        $tableName = $this->tableName;
        $conn->execute(sprintf('DROP INDEX IF EXISTS "%s_overrides_gin"', $tableName));
        $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('creates the config_values table with config_key as primary key', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $rows = $conn->query(
        "SELECT column_name, data_type
         FROM information_schema.columns
         WHERE table_name = ?
           AND column_name = 'config_key'",
        [$tableName],
    );

    expect($rows)->toHaveCount(1);

    // Verify it is the primary key
    $pkRows = $conn->query(
        "SELECT kcu.column_name
         FROM information_schema.table_constraints tc
         JOIN information_schema.key_column_usage kcu
           ON tc.constraint_name = kcu.constraint_name
          AND tc.table_name = kcu.table_name
         WHERE tc.constraint_type = 'PRIMARY KEY'
           AND tc.table_name = ?",
        [$tableName],
    );

    expect($pkRows)->toHaveCount(1)
        ->and($pkRows[0]['column_name'])->toBe('config_key');
})->group('integration-destructive');

it('creates the value column as JSONB and nullable', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $rows = $conn->query(
        "SELECT column_name, data_type, is_nullable
         FROM information_schema.columns
         WHERE table_name = ?
           AND column_name = 'value'",
        [$tableName],
    );

    expect($rows)->toHaveCount(1)
        ->and(strtolower($rows[0]['data_type']))->toBe('jsonb')
        ->and(strtolower($rows[0]['is_nullable']))->toBe('yes');
})->group('integration-destructive');

it('creates the overrides column as JSONB defaulting to empty object', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $rows = $conn->query(
        "SELECT column_name, data_type, is_nullable, column_default
         FROM information_schema.columns
         WHERE table_name = ?
           AND column_name = 'overrides'",
        [$tableName],
    );

    expect($rows)->toHaveCount(1)
        ->and(strtolower($rows[0]['data_type']))->toBe('jsonb')
        ->and(strtolower($rows[0]['is_nullable']))->toBe('no')
        ->and($rows[0]['column_default'])->toContain("'{}'");
})->group('integration-destructive');

it('creates the version column as integer defaulting to 0', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $rows = $conn->query(
        "SELECT column_name, data_type, is_nullable, column_default
         FROM information_schema.columns
         WHERE table_name = ?
           AND column_name = 'version'",
        [$tableName],
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['data_type'])->toBe('integer')
        ->and(strtolower($rows[0]['is_nullable']))->toBe('no')
        ->and($rows[0]['column_default'])->toBe('0');
})->group('integration-destructive');

it('creates the updated_at column as timestamptz', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $rows = $conn->query(
        "SELECT column_name, data_type, is_nullable
         FROM information_schema.columns
         WHERE table_name = ?
           AND column_name = 'updated_at'",
        [$tableName],
    );

    expect($rows)->toHaveCount(1)
        ->and(strtolower($rows[0]['data_type']))->toBe('timestamp with time zone')
        ->and(strtolower($rows[0]['is_nullable']))->toBe('no');
})->group('integration-destructive');

it('creates a GIN index on the overrides column', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $rows = $conn->query(
        'SELECT indexname, indexdef
         FROM pg_indexes
         WHERE tablename = ?
           AND indexname = ?',
        [$tableName, $tableName . '_overrides_gin'],
    );

    expect($rows)->toHaveCount(1)
        ->and(strtolower($rows[0]['indexdef']))->toContain('using gin')
        ->and(strtolower($rows[0]['indexdef']))->toContain('overrides');
})->group('integration-destructive');

it('is idempotent across multiple runs (no duplicate index / table errors)', function (): void {
    /** @var PostgresTestConnection $conn */
    $conn = $this->conn;
    $tableName = $this->tableName;

    /** @var ConfigValuesTableEmitter $emitter */
    $emitter = $this->emitter;
    $statements = $emitter->createStatements($tableName);

    // Run statements twice — must not throw on second run
    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    // Verify table and index exist exactly once
    $tableRows = $conn->query(
        'SELECT table_name
         FROM information_schema.tables
         WHERE table_name = ?',
        [$tableName],
    );

    $indexRows = $conn->query(
        'SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE tablename = ?
           AND indexname = ?',
        [$tableName, $tableName . '_overrides_gin'],
    );

    expect($tableRows)->toHaveCount(1)
        ->and((int) $indexRows[0]['cnt'])->toBe(1);
})->group('integration-destructive');
