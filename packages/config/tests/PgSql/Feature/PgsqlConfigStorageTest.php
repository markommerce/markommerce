<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Tests\Feature;

use DateTimeImmutable;
use Markommerce\Config\PgSql\PgsqlConfigStorage;
use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Testing\Database\TestConnection;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function configStorageTableName(): string
{
    static $name = null;

    if ($name === null) {
        $name = 'config_values_storage_test_' . bin2hex(random_bytes(8));
    }

    return $name;
}

function makeRow(string $key, mixed $value = null, int $version = 0): ConfigRow
{
    return new ConfigRow(
        key: $key,
        value: $value,
        version: $version,
    );
}

// ─── Shared connection & lifecycle ────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();

    $this->conn = new TestConnection();
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
        /** @var TestConnection $conn */
        $connA = new TestConnection();
        $connB = new TestConnection();
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

        $emptyRow = makeRow($key, null);
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
    'serializes two simultaneous INSERTs to a brand-new key — exactly one succeeds, the other returns false',
    function (): void {
        $connA = new TestConnection();
        $connB = new TestConnection();
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

it('persists and reads config values via the in-package PgsqlConfigStorage', function (): void {
    /** @var PgsqlConfigStorage $storage */
    $storage = $this->storage;
    $key = 'markommerce/catalog.global_value';

    $row = makeRow($key, 'test_value');
    $result = $storage->compareAndSave($key, $row, 0);

    expect($result)->toBeTrue();

    $loaded = $storage->load($key);

    expect($loaded)->not->toBeNull()
        ->and($loaded->key)->toBe($key)
        ->and($loaded->value)->toBe('test_value')
        ->and($loaded->version)->toBe(1);
})->group('integration-destructive');

it(
    'loads a global value via PgsqlConfigStorage load and hydrates ConfigRow with key, value, version, updatedAt only',
    function (): void {
        /** @var PgsqlConfigStorage $storage */
        $storage = $this->storage;
        $key = 'markommerce/catalog.hydration_test';

        $storage->compareAndSave($key, makeRow($key, ['nested' => true]), 0);

        $loaded = $storage->load($key);

        expect($loaded)->not->toBeNull()
            ->and($loaded)->toBeInstanceOf(ConfigRow::class)
            ->and($loaded->key)->toBe($key)
            ->and($loaded->value)->toBe(['nested' => true])
            ->and($loaded->version)->toBe(1)
            ->and($loaded->updatedAt)->toBeInstanceOf(DateTimeImmutable::class);
    },
)->group('integration-destructive');

it(
    'deletes the row from PgsqlConfigStorage compareAndSave when the new row has value=null and the version matches',
    function (): void {
        /** @var PgsqlConfigStorage $storage */
        $storage = $this->storage;
        $key = 'markommerce/catalog.delete_test';

        $storage->compareAndSave($key, makeRow($key, 'to_delete'), 0);

        $emptyRow = makeRow($key, null);
        $result = $storage->compareAndSave($key, $emptyRow, 1);

        expect($result)->toBeTrue();
        expect($storage->load($key))->toBeNull();
    },
)->group('integration-destructive');
