<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\PgSql\Tests\Feature;

use Markommerce\ConfigScope\PgSql\PgsqlScopedConfigStorage;
use Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter;
use Markommerce\Testing\Database\TestConnection;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function configScopedStorageTableName(): string
{
    static $name = null;

    if ($name === null) {
        $name = 'config_value_overrides_storage_test_' . bin2hex(random_bytes(8));
    }

    return $name;
}

// ─── Shared connection & lifecycle ────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();

    $this->conn = new TestConnection();
    $this->tableName = configScopedStorageTableName();

    // Ensure table exists
    $emitter = new ConfigValueOverridesTableEmitter();

    foreach ($emitter->createStatements($this->tableName) as $sql) {
        $this->conn->execute($sql);
    }

    $this->storage = new PgsqlScopedConfigStorage($this->conn, $this->tableName);
});

afterEach(function (): void {
    if (isset($this->conn) && isset($this->tableName)) {
        $this->conn->execute(sprintf('DELETE FROM "%s"', $this->tableName));
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it('inserts a new override row via PgsqlScopedConfigStorage saveOverride', function (): void {
    /** @var PgsqlScopedConfigStorage $storage */
    $storage = $this->storage;

    $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_1', 20);

    $result = $storage->loadOverrides('markommerce/catalog.grid_page_size');

    expect($result)->toHaveKey('store_1')
        ->and($result['store_1'])->toBe(20);
})->group('integration-destructive');

it(
    'overwrites an existing override row via PgsqlScopedConfigStorage saveOverride using ON CONFLICT DO UPDATE',
    function (): void {
        /** @var PgsqlScopedConfigStorage $storage */
        $storage = $this->storage;
    
        $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_1', 20);
        $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_1', 50);
    
        $result = $storage->loadOverrides('markommerce/catalog.grid_page_size');
    
        expect($result)->toHaveKey('store_1')
            ->and($result['store_1'])->toBe(50)
            ->and(count($result))->toBe(1);
    }
)->group('integration-destructive');

it(
    'loads all overrides for a given config_key as a signature=>value map via PgsqlScopedConfigStorage loadOverrides',
    function (): void {
        /** @var PgsqlScopedConfigStorage $storage */
        $storage = $this->storage;
    
        $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_1', 20);
        $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_2', 30);
    
        $result = $storage->loadOverrides('markommerce/catalog.grid_page_size');
    
        expect($result)->toHaveCount(2)
            ->and($result)->toHaveKey('store_1')
            ->and($result['store_1'])->toBe(20)
            ->and($result)->toHaveKey('store_2')
            ->and($result['store_2'])->toBe(30);
    }
)->group('integration-destructive');

it('returns an empty array from PgsqlScopedConfigStorage loadOverrides for an unknown config_key', function (): void {
    /** @var PgsqlScopedConfigStorage $storage */
    $storage = $this->storage;

    $result = $storage->loadOverrides('markommerce/catalog.unknown_key');

    expect($result)->toBe([]);
})->group('integration-destructive');

it('returns multiple keys as a nested map from PgsqlScopedConfigStorage loadManyOverrides', function (): void {
    /** @var PgsqlScopedConfigStorage $storage */
    $storage = $this->storage;

    $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_1', 20);
    $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_2', 30);
    $storage->saveOverride('markommerce/catalog.list_page_size', 'store_1', 10);

    $result = $storage->loadManyOverrides([
        'markommerce/catalog.grid_page_size',
        'markommerce/catalog.list_page_size',
    ]);

    expect($result)->toHaveCount(2)
        ->and($result)->toHaveKey('markommerce/catalog.grid_page_size')
        ->and($result['markommerce/catalog.grid_page_size'])->toHaveCount(2)
        ->and($result['markommerce/catalog.grid_page_size']['store_1'])->toBe(20)
        ->and($result['markommerce/catalog.grid_page_size']['store_2'])->toBe(30)
        ->and($result)->toHaveKey('markommerce/catalog.list_page_size')
        ->and($result['markommerce/catalog.list_page_size'])->toHaveCount(1)
        ->and($result['markommerce/catalog.list_page_size']['store_1'])->toBe(10);
})->group('integration-destructive');

it(
    'removes a single override via PgsqlScopedConfigStorage deleteOverride leaving other overrides for the same key intact',
    function (): void {
        /** @var PgsqlScopedConfigStorage $storage */
        $storage = $this->storage;
    
        $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_1', 20);
        $storage->saveOverride('markommerce/catalog.grid_page_size', 'store_2', 30);
    
        $storage->deleteOverride('markommerce/catalog.grid_page_size', 'store_1');
    
        $result = $storage->loadOverrides('markommerce/catalog.grid_page_size');
    
        expect($result)->toHaveCount(1)
            ->and($result)->not->toHaveKey('store_1')
            ->and($result)->toHaveKey('store_2')
            ->and($result['store_2'])->toBe(30);
    }
)->group('integration-destructive');
