<?php

declare(strict_types=1);

it('has a composer.json declaring markommerce/config with PHP 8.5 requirement', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('markommerce/config')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('declares marko-module type with the marko.module extra flag', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('asserts packages/config/composer.json does NOT require markommerce/scope', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->not->toHaveKey('markommerce/scope');
});

it('autoloads the Markommerce\Config namespace from src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Config\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Config\\'])->toBe('src/');
});

it('ships an empty module.php returning a valid bindings array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

it('is discoverable via composer dump-autoload from the workspace root', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    // Verify autoload namespace is correctly defined so composer can discover it
    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Config\\')
        ->and(is_dir(dirname(__DIR__) . '/src'))->toBeTrue();
});

it('the root composer.json require block lists markommerce/config: self.version', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode((string) file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/config')
        ->and($composer['require']['markommerce/config'])->toBe('self.version');
});
