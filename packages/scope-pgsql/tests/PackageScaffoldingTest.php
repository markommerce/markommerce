<?php

declare(strict_types=1);

it('has a valid composer.json with name markommerce/scope-pgsql and extra.marko.module true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('markommerce/scope-pgsql')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('requires markommerce/scope and marko/database-pgsql in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/scope')
        ->and($composer['require'])->toHaveKey('marko/database-pgsql');
});

it('has no version field in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toHaveKey('version');
});

it('autoloads PSR-4 namespace Markommerce\Scope\PgSql\ from packages/scope-pgsql/src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Scope\\PgSql\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Scope\\PgSql\\'])->toBe('src/');
});

it('autoloads tests namespace Markommerce\Scope\PgSql\Tests\ from packages/scope-pgsql/tests/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Scope\\PgSql\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\Scope\\PgSql\\Tests\\'])->toBe('tests/');
});

it('has a module.php returning an array with a bindings key', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});
