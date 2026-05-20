<?php

declare(strict_types=1);

use Markommerce\Scope\PgSql\Query\PgSqlScopedFieldRenderer;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;

it('returns an array with bindings key', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');
});

it('binds ScopedFieldRendererInterface to PgSqlScopedFieldRenderer', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(ScopedFieldRendererInterface::class)
        ->and($module['bindings'][ScopedFieldRendererInterface::class])->toBe(PgSqlScopedFieldRenderer::class);
});

it('does not re-bind markommerce/scope interfaces', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    $scopeInterfaces = array_filter(
        array_keys($module['bindings']),
        fn (string $key): bool => str_starts_with(
            $key,
            'Markommerce\\Scope\\',
        ) && $key !== ScopedFieldRendererInterface::class,
    );

    expect($scopeInterfaces)->toBeEmpty();
});
