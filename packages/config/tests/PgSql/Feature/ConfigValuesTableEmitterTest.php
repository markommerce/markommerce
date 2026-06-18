<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Tests\Feature;

use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\Testing\Database\TestConnection;

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
    TestConnection::skipIfUnavailable();

    $conn = new TestConnection();
    $tableName = configValuesTableName();

    // DROP IF EXISTS first (crash resilience from previous failed runs)
    $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));

    $this->conn = $conn;
    $this->tableName = $tableName;
    $this->emitter = new ConfigValuesTableEmitter();
});

afterEach(function (): void {
    if (isset($this->conn) && isset($this->tableName)) {
        $conn = $this->conn;
        $tableName = $this->tableName;
        $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('provisions the config_values table from the in-package emitter', function (): void {
    /** @var TestConnection $conn */
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
    /** @var TestConnection $conn */
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

it('creates the version column as integer defaulting to 0', function (): void {
    /** @var TestConnection $conn */
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
    /** @var TestConnection $conn */
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

it('is idempotent across multiple runs (no duplicate table errors)', function (): void {
    /** @var TestConnection $conn */
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

    // Verify table exists exactly once
    $tableRows = $conn->query(
        'SELECT table_name
         FROM information_schema.tables
         WHERE table_name = ?',
        [$tableName],
    );

    expect($tableRows)->toHaveCount(1);
})->group('integration-destructive');
