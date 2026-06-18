<?php

declare(strict_types=1);

it('has a valid composer.json with name markommerce/scope and extra.marko.module true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('markommerce/scope')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('requires PHP ^8.5, marko/core, marko/config, and marko/database-pgsql in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/config')
        ->and($composer['require'])->toHaveKey('marko/database-pgsql');
});

it('has no version field in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toHaveKey('version');
});

it('autoloads PSR-4 namespace Markommerce\Scope\ from packages/scope/src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Scope\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Scope\\'])->toBe('src/');
});

it('autoloads PSR-4 test namespace Markommerce\Scope\Tests\ from packages/scope/tests/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Scope\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\Scope\\Tests\\'])->toBe('tests/');
});

it('has a module.php returning an array with bindings and singletons keys', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray()
        ->and($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toBeArray();
});
