<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\PgSql\PgsqlScopedConfigStorage;

it(
    'binds ScopedConfigStorageInterface to PgsqlScopedConfigStorage from config-scope\'s own module',
    function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';

        $bindings = $module['bindings'] ?? [];

        expect($bindings)->toHaveKey(ScopedConfigStorageInterface::class);

        $factory = $bindings[ScopedConfigStorageInterface::class];

        expect($factory)->toBeInstanceOf(Closure::class);

        // Inspect the factory closure's return type via reflection to confirm it returns PgsqlScopedConfigStorage
        $reflection = new ReflectionFunction($factory);
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect((string) $returnType)->toBe(PgsqlScopedConfigStorage::class);
    },
);
