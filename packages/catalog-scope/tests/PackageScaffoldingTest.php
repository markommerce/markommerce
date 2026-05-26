<?php

declare(strict_types=1);

it('requires markommerce/catalog and markommerce/scope in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog')
        ->and($composer['require'])->toHaveKey('markommerce/scope');
});

it('declares itself as a marko-module via composer extra.marko.module=true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('uses the Markommerce\\CatalogScope\\ namespace for autoload', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\CatalogScope\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\CatalogScope\\'])->toBe('src/');
});

it('declares the Markommerce\\CatalogScope\\Seed\\ PSR-4 autoload entry for the seeder', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\CatalogScope\\Seed\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\CatalogScope\\Seed\\'])->toBe('Seed/');
});
