<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Tests\Feature;

require_once __DIR__ . '/Helpers/PostgresTestConnection.php';

use DateTimeImmutable;
use Markommerce\Config\PgSql\PgsqlConfigStorage;
use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\Config\PgSql\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Config\ValueObjects\ConfigRow;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function configStorageTableName(): string
{
    static $name = null;

    if ($name === null) {
        $name = 'config_values_storage_test_' . bin2hex(random_bytes(8));
    }

    return $name;
}

function makeRow(string $key, mixed $value = null, array $overrides = [], int $version = 0): ConfigRow
{
    return new ConfigRow(
        key: $key,
        value: $value,
        overrides: $overrides,
        version: $version,
    );
}

// ─── Shared connection & lifecycle ────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();

    $this->conn = new PostgresTestConnection();
    $this->tableName = configStorageTableName();

    // Ensure table exists
    $emitter = new ConfigValuesTableEmitter();

    foreach ($emitter->createStatements($this->tableName) as $sql) {
        $this->conn->execute($sql);
    }

    $this->storage = new PgsqlConfigStorage($this->conn, $this->tableName);
});

afterEach(function (): void {
    if (isset($this->conn) && isset($this->tableName)) {
        $this->conn->execute(sprintf('DELETE FROM "%s"', $this->tableName));
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('returns null from load when the config_key row does not exist', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;

    expect($storage->load('markommerce/catalog.missing_key'))->toBeNull();
})->group('integration-destructive');

it('returns a hydrated ConfigRow with decoded value and overrides from load when the row exists', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $storage->compareAndSave($key, makeRow($key, 20, ['channel:web' => 30]), 0);

    $loaded = $storage->load($key);

    expect($loaded)->not->toBeNull()
        ->and($loaded->key)->toBe($key)
        ->and($loaded->value)->toBe(20)
        ->and($loaded->overrides)->toBe(['channel:web' => 30])
        ->and($loaded->version)->toBe(1)
        ->and($loaded->updatedAt)->toBeInstanceOf(DateTimeImmutable::class);
})->group('integration-destructive');

it('returns an empty array from loadMany when no requested keys exist', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;

    $result = $storage->loadMany(['markommerce/catalog.missing_a', 'markommerce/catalog.missing_b']);

    expect($result)->toBe([]);
})->group('integration-destructive');

it('returns a map keyed by config_key with one entry per existing row from loadMany', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;

    $key1 = 'markommerce/catalog.grid_page_size';
    $key2 = 'markommerce/catalog.list_page_size';

    $storage->compareAndSave($key1, makeRow($key1, 20), 0);
    $storage->compareAndSave($key2, makeRow($key2, 10), 0);

    $result = $storage->loadMany([$key1, $key2, 'markommerce/catalog.missing']);

    expect($result)->toHaveCount(2)
        ->and(array_key_exists($key1, $result))->toBeTrue()
        ->and(array_key_exists($key2, $result))->toBeTrue()
        ->and($result[$key1]->value)->toBe(20)
        ->and($result[$key2]->value)->toBe(10);
})->group('integration-destructive');

it('inserts a new row via compareAndSave when expectedVersion is 0 and no row exists', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $result = $storage->compareAndSave($key, makeRow($key, 20), 0);

    expect($result)->toBeTrue();

    $loaded = $storage->load($key);

    expect($loaded)->not->toBeNull()
        ->and($loaded->value)->toBe(20)
        ->and($loaded->version)->toBe(1);
})->group('integration-destructive');

it('updates an existing row via compareAndSave when the stored version matches expectedVersion', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $storage->compareAndSave($key, makeRow($key, 20), 0);

    $result = $storage->compareAndSave($key, makeRow($key, 25), 1);

    expect($result)->toBeTrue();

    $loaded = $storage->load($key);

    expect($loaded->value)->toBe(25)
        ->and($loaded->version)->toBe(2);
})->group('integration-destructive');

it('bumps version by exactly 1 on every successful compareAndSave', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $storage->compareAndSave($key, makeRow($key, 10), 0);
    $loaded1 = $storage->load($key);
    expect($loaded1->version)->toBe(1);

    $storage->compareAndSave($key, makeRow($key, 20), 1);
    $loaded2 = $storage->load($key);
    expect($loaded2->version)->toBe(2);

    $storage->compareAndSave($key, makeRow($key, 30), 2);
    $loaded3 = $storage->load($key);
    expect($loaded3->version)->toBe(3);
})->group('integration-destructive');

it('returns false from compareAndSave when the stored version does not match expectedVersion', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $storage->compareAndSave($key, makeRow($key, 20), 0);

    $result = $storage->compareAndSave($key, makeRow($key, 25), 99);

    expect($result)->toBeFalse();

    $loaded = $storage->load($key);
    expect($loaded->value)->toBe(20)
        ->and($loaded->version)->toBe(1);
})->group('integration-destructive');

it('deletes the row when compareAndSave persists a row with null value and empty overrides', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $storage->compareAndSave($key, makeRow($key, 20), 0);

    $emptyRow = makeRow($key, null, []);
    $result = $storage->compareAndSave($key, $emptyRow, 1);

    expect($result)->toBeTrue();
    expect($storage->load($key))->toBeNull();
})->group('integration-destructive');

it('sets updated_at to NOW() on every successful compareAndSave', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.grid_page_size';

    $before = new DateTimeImmutable();

    $storage->compareAndSave($key, makeRow($key, 20), 0);

    $after = new DateTimeImmutable();

    $loaded = $storage->load($key);

    expect($loaded->updatedAt)->not->toBeNull()
        ->and($loaded->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp())
        ->and($loaded->updatedAt->getTimestamp())->toBeLessThanOrEqual($after->getTimestamp() + 1);
})->group('integration-destructive');

it(
    'is safe under concurrent compareAndSave calls — only one of two simultaneous writers with the same expectedVersion succeeds',
    function (): void {
        /** @var PostgresTestConnection $conn */
        $connA = new PostgresTestConnection();
        $connB = new PostgresTestConnection();
        $tableName = $this->tableName;

        $storageA = new PgsqlConfigStorage($connA, $tableName);
        $storageB = new PgsqlConfigStorage($connB, $tableName);

        $key = 'markommerce/catalog.concurrent_test';

        // Both try to insert at expectedVersion 0
        $row = makeRow($key, 'value');

        $resultA = $storageA->compareAndSave($key, $row, 0);
        $resultB = $storageB->compareAndSave($key, $row, 0);

        // Exactly one must succeed
        expect($resultA xor $resultB)->toBeTrue();

        // The stored version must be exactly 1
        $loaded = $this->storage->load($key);
        expect($loaded->version)->toBe(1);
    },
)->group('integration-destructive');

it(
    'returns true as a no-op when compareAndSave persists an empty row against an absent key with expectedVersion 0',
    function (): void {
        /** @var PgsqlConfigStorage $storage */
        $storage = $this->storage;
        $key = 'markommerce/catalog.absent_key';

        $emptyRow = makeRow($key, null, []);
        $result = $storage->compareAndSave($key, $emptyRow, 0);

        expect($result)->toBeTrue();
        expect($storage->load($key))->toBeNull();
    },
)->group('integration-destructive');

it(
    'returns false from compareAndSave when expectedVersion is greater than 0 but the row no longer exists (deleted by another writer)',
    function (): void {
        /** @var PgsqlConfigStorage $storage */
        $storage = $this->storage;
        $key = 'markommerce/catalog.deleted_key';

        // No row exists, but we claim expectedVersion = 5
        $result = $storage->compareAndSave($key, makeRow($key, 42), 5);

        expect($result)->toBeFalse();
        expect($storage->load($key))->toBeNull();
    },
)->group('integration-destructive');

it(
    'serializes a concurrent empty-mutation (delete) and non-empty mutation (override-add) to the same key without losing the non-empty mutation',
    function (): void {
        /** @var PostgresTestConnection $conn */
        $connA = new PostgresTestConnection();
        $connB = new PostgresTestConnection();
        $tableName = $this->tableName;

        $storageA = new PgsqlConfigStorage($connA, $tableName);
        $storageB = new PgsqlConfigStorage($connB, $tableName);

        $key = 'markommerce/catalog.serialize_test';

        // First, insert a row so both can claim expectedVersion 1
        $this->storage->compareAndSave($key, makeRow($key, 'initial'), 0);

        // A tries to delete (empty row), B tries to update with a new value
        $deleteRow = makeRow($key, null, []);
        $updateRow = makeRow($key, 'updated', ['channel:web' => 'override']);

        $resultA = $storageA->compareAndSave($key, $deleteRow, 1);
        $resultB = $storageB->compareAndSave($key, $updateRow, 1);

        // Exactly one must succeed
        expect($resultA xor $resultB)->toBeTrue();

        // If B succeeded (update), we must see the updated value
        if ($resultB) {
            $loaded = $this->storage->load($key);
            expect($loaded)->not->toBeNull()
                ->and($loaded->value)->toBe('updated');
        } else {
            // A succeeded (delete), B failed — row is gone
            expect($this->storage->load($key))->toBeNull();
        }
    },
)->group('integration-destructive');

it(
    'serializes two simultaneous INSERTs to a brand-new key — exactly one succeeds, the other returns false',
    function (): void {
        $connA = new PostgresTestConnection();
        $connB = new PostgresTestConnection();
        $tableName = $this->tableName;

        $storageA = new PgsqlConfigStorage($connA, $tableName);
        $storageB = new PgsqlConfigStorage($connB, $tableName);

        $key = 'markommerce/catalog.race_insert';

        $rowA = makeRow($key, 'writer_a');
        $rowB = makeRow($key, 'writer_b');

        $resultA = $storageA->compareAndSave($key, $rowA, 0);
        $resultB = $storageB->compareAndSave($key, $rowB, 0);

        // Exactly one INSERT must win
        expect($resultA xor $resultB)->toBeTrue();

        // The stored row must have version 1 (inserted once)
        $loaded = $this->storage->load($key);
        expect($loaded->version)->toBe(1);
    },
)->group('integration-destructive');
