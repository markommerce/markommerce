<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\PgSql\Tests\Feature;

use Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter;
use Markommerce\Testing\Database\TestConnection;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function configValueOverridesTableName(): string
{
    static $name = null;

    if ($name === null) {
        $name = 'config_value_overrides_test_' . bin2hex(random_bytes(8));
    }

    return $name;
}

// ─── Shared connection & lifecycle ────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();

    $conn = new TestConnection();
    $tableName = configValueOverridesTableName();

    // DROP IF EXISTS first (crash resilience from previous failed runs)
    $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));

    $this->conn = $conn;
    $this->tableName = $tableName;
    $this->emitter = new ConfigValueOverridesTableEmitter();
});

afterEach(function (): void {
    if (isset($this->conn) && isset($this->tableName)) {
        $conn = $this->conn;
        $tableName = $this->tableName;
        $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'emits a CREATE TABLE config_value_overrides statement with config_key, signature, value, version, updated_at columns and a composite PRIMARY KEY (config_key, signature)',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        /** @var ConfigValueOverridesTableEmitter $emitter */
        $emitter = $this->emitter;
        $statements = $emitter->createStatements($tableName);

        foreach ($statements as $sql) {
            $conn->execute($sql);
        }

        $columns = $conn->query(
            'SELECT column_name
             FROM information_schema.columns
             WHERE table_name = ?
             ORDER BY ordinal_position',
            [$tableName],
        );

        $columnNames = array_column($columns, 'column_name');

        expect($columnNames)->toContain('config_key')
            ->and($columnNames)->toContain('signature')
            ->and($columnNames)->toContain('value')
            ->and($columnNames)->toContain('version')
            ->and($columnNames)->toContain('updated_at');

        // Verify composite primary key
        $pkRows = $conn->query(
            "SELECT kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_name = kcu.table_name
             WHERE tc.constraint_type = 'PRIMARY KEY'
               AND tc.table_name = ?
             ORDER BY kcu.ordinal_position",
            [$tableName],
        );

        $pkColumns = array_column($pkRows, 'column_name');

        expect($pkColumns)->toContain('config_key')
            ->and($pkColumns)->toContain('signature')
            ->and(count($pkColumns))->toBe(2);
    },
)->group('integration-destructive');

it(
    'returns a list with exactly one statement from ConfigValueOverridesTableEmitter createStatements',
    function (): void {
        /** @var ConfigValueOverridesTableEmitter $emitter */
        $emitter = $this->emitter;
        $statements = $emitter->createStatements($this->tableName);
    
        expect($statements)->toHaveCount(1);
    }
)->group('integration-destructive');
