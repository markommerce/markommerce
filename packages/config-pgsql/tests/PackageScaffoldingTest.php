<?php

declare(strict_types=1);

it('has a composer.json declaring markommerce/config-pgsql with PHP 8.5 requirement', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('markommerce/config-pgsql')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('declares marko-module type with the marko.module extra flag', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('requires markommerce/config and marko/database-pgsql as self-version deps', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/config')
        ->and($composer['require']['markommerce/config'])->toBe('self.version')
        ->and($composer['require'])->toHaveKey('marko/database-pgsql')
        ->and($composer['require']['marko/database-pgsql'])->toBe('self.version');
});

it('autoloads the Markommerce\Config\PgSql namespace from src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Config\\PgSql\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Config\\PgSql\\'])->toBe('src/');
});

it('ships an empty module.php returning a valid bindings array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

it('the root composer.json require block lists markommerce/config-pgsql: self.version', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/config-pgsql')
        ->and($composer['require']['markommerce/config-pgsql'])->toBe('self.version');
});
