<?php

declare(strict_types=1);

use Markommerce\Config\ValueObjects\ConfigRow;

it('constructs a ConfigRow with key, value, overrides, version, and updatedAt', function (): void {
    $updatedAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        overrides: ['website=1' => 50, 'store=2' => 10],
        version: 3,
        updatedAt: $updatedAt,
    );

    expect($row)->toBeInstanceOf(ConfigRow::class);
});

it('returns a new ConfigRow with the global value replaced via withGlobal', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        overrides: [],
        version: 1,
    );

    $updated = $row->withGlobal(50);

    expect($updated)->not->toBe($row)
        ->and($updated->value)->toBe(50)
        ->and($updated->key)->toBe($row->key)
        ->and($updated->overrides)->toBe($row->overrides)
        ->and($updated->version)->toBe($row->version);
});

it('returns a new ConfigRow with the global cleared to null via withoutGlobal', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        overrides: [],
        version: 1,
    );

    $cleared = $row->withoutGlobal();

    expect($cleared)->not->toBe($row)
        ->and($cleared->value)->toBeNull()
        ->and($cleared->key)->toBe($row->key)
        ->and($cleared->version)->toBe($row->version);
});

it('returns a new ConfigRow with an override added or replaced via withOverride keyed by signature string', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        overrides: ['website=1' => 50],
        version: 1,
    );

    $withNew = $row->withOverride('store=2', 10);
    $withReplaced = $row->withOverride('website=1', 99);

    expect($withNew)->not->toBe($row)
        ->and($withNew->overrides)->toBe(['website=1' => 50, 'store=2' => 10])
        ->and($withNew->version)->toBe($row->version);

    expect($withReplaced->overrides)->toBe(['website=1' => 99])
        ->and($withReplaced->version)->toBe($row->version);
});

it('returns a new ConfigRow with a specific override removed via withoutOverride', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        overrides: ['website=1' => 50, 'store=2' => 10],
        version: 1,
    );

    $withoutOverride = $row->withoutOverride('website=1');

    expect($withoutOverride)->not->toBe($row)
        ->and($withoutOverride->overrides)->toBe(['store=2' => 10])
        ->and($withoutOverride->version)->toBe($row->version);
});

it('preserves the version on all with* mutations so the writer controls version bumps explicitly', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        overrides: ['website=1' => 50],
        version: 7,
    );

    expect($row->withGlobal(99)->version)->toBe(7)
        ->and($row->withoutGlobal()->version)->toBe(7)
        ->and($row->withOverride('store=2', 10)->version)->toBe(7)
        ->and($row->withoutOverride('website=1')->version)->toBe(7);
});
