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
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $loaded = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded)->not->toBeNull()
        ->and($loaded->key)->toBe('markommerce/catalog.grid_page_size')
        ->and($loaded->value)->toBe(20);
});

it('bumps version by one on each successful compareAndSave', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);
    $loaded1 = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded1->version)->toBe(1);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $loaded1->withGlobal(30), 1);
    $loaded2 = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded2->version)->toBe(2);
});

it('returns false from compareAndSave when expectedVersion does not match the stored version', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $row->withGlobal(30), 99);
    expect($result)->toBeFalse();
});

it('leaves the stored row unchanged after a failed compareAndSave', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row->withGlobal(99), 99);

    $loaded = $storage->load('markommerce/catalog.grid_page_size');
    expect($loaded->value)->toBe(20)
        ->and($loaded->version)->toBe(1);
});

it('returns multiple rows from loadMany in a single call', function (): void {
    $storage = new InMemoryConfigStorage();
    $row1 = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);
    $row2 = new ConfigRow(key: 'markommerce/catalog.list_page_size', value: 10, version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row1, 0);
    $storage->compareAndSave('markommerce/catalog.list_page_size', $row2, 0);

    $result = $storage->loadMany(['markommerce/catalog.grid_page_size', 'markommerce/catalog.list_page_size']);

    expect($result)->toHaveCount(2)
        ->and($result['markommerce/catalog.grid_page_size']->value)->toBe(20)
        ->and($result['markommerce/catalog.list_page_size']->value)->toBe(10);
});

it('omits keys absent from storage in the loadMany result map', function (): void {
    $storage = new InMemoryConfigStorage();
    $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);

    $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

    $result = $storage->loadMany(['markommerce/catalog.grid_page_size', 'markommerce/catalog.missing_key']);

    expect($result)->toHaveCount(1)
        ->and(array_key_exists('markommerce/catalog.missing_key', $result))->toBeFalse();
});

it(
    'treats InMemoryConfigStorage compareAndSave as empty-row when value is null regardless of any pre-existing state',
    function (): void {
        $storage = new InMemoryConfigStorage();

        // Store a row with a value first
        $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);
        $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

        // Now compareAndSave with null value — should be treated as empty regardless of prior state
        $nullRow = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: null, version: 0);
        $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $nullRow, 1);

        expect($result)->toBeTrue()
            ->and($storage->load('markommerce/catalog.grid_page_size'))->toBeNull();
    },
);

it(
    'deletes the existing in-memory row when compareAndSave is called with value=null and the stored version matches',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 20, version: 0);

        $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

        $nullRow = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: null, version: 0);
        $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $nullRow, 1);

        expect($result)->toBeTrue()
            ->and($storage->load('markommerce/catalog.grid_page_size'))->toBeNull();
    },
);

it(
    'persists a new row with value set, version 1, when compareAndSave is called with expectedVersion=0 and a non-null value',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $row = new ConfigRow(key: 'markommerce/catalog.grid_page_size', value: 42, version: 0);

        $result = $storage->compareAndSave('markommerce/catalog.grid_page_size', $row, 0);

        expect($result)->toBeTrue();
        $loaded = $storage->load('markommerce/catalog.grid_page_size');
        expect($loaded)->not->toBeNull()
            ->and($loaded->value)->toBe(42)
            ->and($loaded->version)->toBe(1);
    },
);
