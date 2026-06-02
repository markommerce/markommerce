<?php

declare(strict_types=1);

use Markommerce\Config\ValueObjects\ConfigRow;

it('constructs ConfigRow with key, value, version, and updatedAt but no overrides property', function (): void {
    $updatedAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        version: 3,
        updatedAt: $updatedAt,
    );

    expect($row)->toBeInstanceOf(ConfigRow::class)
        ->and($row->key)->toBe('markommerce/catalog.grid_page_size')
        ->and($row->value)->toBe(20)
        ->and($row->version)->toBe(3)
        ->and($row->updatedAt)->toBe($updatedAt);
});

it('returns a new ConfigRow with the global value replaced via withGlobal', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        version: 1,
    );

    $updated = $row->withGlobal(50);

    expect($updated)->not->toBe($row)
        ->and($updated->value)->toBe(50)
        ->and($updated->key)->toBe($row->key)
        ->and($updated->version)->toBe($row->version);
});

it('returns a new ConfigRow with the global cleared to null via withoutGlobal', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        version: 1,
    );

    $cleared = $row->withoutGlobal();

    expect($cleared)->not->toBe($row)
        ->and($cleared->value)->toBeNull()
        ->and($cleared->key)->toBe($row->key)
        ->and($cleared->version)->toBe($row->version);
});

it(
    'keeps withGlobal and withoutGlobal methods on ConfigRow (used by ConfigWriter\'s writeWithRetry mutate closures; no signature change)',
    function (): void {
        $row = new ConfigRow(
            key: 'markommerce/catalog.grid_page_size',
            value: 20,
            version: 1,
        );

        $withGlobal = $row->withGlobal(50);
        $withoutGlobal = $row->withoutGlobal();

        expect($withGlobal)->toBeInstanceOf(ConfigRow::class)
            ->and($withGlobal->value)->toBe(50)
            ->and($withoutGlobal)->toBeInstanceOf(ConfigRow::class)
            ->and($withoutGlobal->value)->toBeNull();
    },
);

it('rejects withOverride and withoutOverride method calls on ConfigRow (methods removed)', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        version: 1,
    );

    expect(fn () => $row->withOverride('store=2', 10))->toThrow(Error::class);
    expect(fn () => $row->withoutOverride('store=2'))->toThrow(Error::class);
});

it('preserves the version on all with* mutations so the writer controls version bumps explicitly', function (): void {
    $row = new ConfigRow(
        key: 'markommerce/catalog.grid_page_size',
        value: 20,
        version: 7,
    );

    expect($row->withGlobal(99)->version)->toBe(7)
        ->and($row->withoutGlobal()->version)->toBe(7);
});
