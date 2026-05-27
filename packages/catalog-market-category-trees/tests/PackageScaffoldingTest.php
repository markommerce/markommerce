<?php

declare(strict_types=1);

it('requires markommerce/catalog, markommerce/market, marko/core, and marko/database in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog')
        ->and($composer['require'])->toHaveKey('markommerce/market')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/database');
});

it('declares itself as a marko-module via composer extra.marko.module=true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('autoloads the Markommerce\\CatalogMarketCategoryTrees\\ namespace from src/', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\CatalogMarketCategoryTrees\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\CatalogMarketCategoryTrees\\'])->toBe('src/');
});

it('autoloads the Markommerce\\CatalogMarketCategoryTrees\\Tests\\ namespace from tests/ in autoload-dev', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\CatalogMarketCategoryTrees\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\CatalogMarketCategoryTrees\\Tests\\'])->toBe('tests/');
});

it('ships a module.php returning a valid Marko module manifest array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray();
});

it('registers the package in the root composer.json require block and adds Markommerce\\CatalogMarketCategoryTrees\\Tests\\ to autoload-dev.psr-4', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog-market-category-trees')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\CatalogMarketCategoryTrees\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\CatalogMarketCategoryTrees\\Tests\\'])
        ->toBe('packages/catalog-market-category-trees/tests/');
});
