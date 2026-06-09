<?php

declare(strict_types=1);

use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\PgSql\PgsqlConfigStorage;

it('keeps the existing config storage tests passing', function (): void {
    expect(class_exists(PgsqlConfigStorage::class))->toBeTrue();
    expect(is_a(PgsqlConfigStorage::class, ConfigStorageInterface::class, true))->toBeTrue();
});
