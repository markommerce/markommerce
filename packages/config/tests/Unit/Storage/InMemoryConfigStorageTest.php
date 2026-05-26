<?php

declare(strict_types=1);

use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;

it('returns null from load when the key is not stored', function (): void {
    $storage = new InMemoryConfigStorage();

    expect($storage->load('markommerce/catalog.grid_page_size'))->toBeNull();
});

it('returns the row from load after a compareAndSave with expectedVersion 0', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $loaded = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded)->not->toBeNull()
        ->and($loaded->key)->toBe('markommerce/catalog.grid_page_size')
        ->and($loaded->value)->toBe(20);
});

it('bumps version by one on each successful compareAndSave', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);
    $loaded1 = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded1->version)->toBe(1);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $loaded1->withGlobal(30), 1);
    $loaded2 = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded2->version)->toBe(2);
});

it('returns false from compareAndSave when expectedVersion does not match the stored version', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $row->withGlobal(30), 99);
    expect($result)->toBeFalse();
});

it('leaves the stored row unchanged after a failed compareAndSave', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row->withGlobal(99), 99);

    $loaded = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded->value)->toBe(20)
        ->and($loaded->version)->toBe(1);
});

it('returns multiple rows from loadMany in a single call', function (): void {
    $storage = new InMemoryConfigStorage();
    $row1 = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);
    $row2 = new ConfigRow(key: 'markommerce/catalog.list_page_size', value: 10, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row1, 0);
    $storage->compareAndSave('markommerce/catalog.list_page_size', $row2, 0);

    $result = $storage->loadMany(['markommerce/catalog.grid_page_size', 'markommerce/catalog.list_page_size']);

    expect($result)->toHaveCount(2)
        ->and($result['markommerce/catalog.grid_page_size']->value)->toBe(20)
        ->and($result['markommerce/catalog.list_page_size']->value)->toBe(10);
});

it('omits keys absent from storage in the loadMany result map', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $result = $storage->loadMany(['markommerce/catalog.grid_page_size', 'markommerce/catalog.missing_key']);

    expect($result)->toHaveCount(1)
        ->and(array_key_exists('markommerce/catalog.missing_key', $result))->toBeFalse();
});

it('deletes the row when compareAndSave persists a row with null value and empty overrides', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, overrides: [], version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $emptyRow = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: null, overrides: [], version: 0);
    $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $emptyRow, 1);

    expect($result)->toBeTrue()
        ->and($storage->load('markommerce/catalog.grid_page_size'))->toBeNull();
});

it(
    'returns true from compareAndSave as a no-op when persisting an empty row against an absent key with expectedVersion 0',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $emptyRow = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: null, overrides: [], version: 0);

        $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $emptyRow, 0);

        expect($result)->toBeTrue()
            ->and($storage->load('markommerce/catalog.grid_page_size'))->toBeNull();
    },
);
