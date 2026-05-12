<?php

declare(strict_types=1);

$composerPath = dirname(__DIR__, 2) . '/composer.json';
$composerData = json_decode(file_get_contents($composerPath), true);

it('creates a composer.json for markommerce/catalog with type marko-module and the marko module flag', function () use ($composerData): void {
    expect($composerData['name'])->toBe('markommerce/catalog');
    expect($composerData['type'])->toBe('marko-module');
    expect($composerData['extra']['marko']['module'])->toBeTrue();
    expect($composerData['license'])->toBe('MIT');
});

it('declares the catalog autoload namespace as Markommerce\Catalog', function () use ($composerData): void {
    expect($composerData['autoload']['psr-4'])->toHaveKey('Markommerce\\Catalog\\');
    expect($composerData['autoload']['psr-4']['Markommerce\\Catalog\\'])->toBe('src/');
});

it('declares the test autoload namespace as Markommerce\Catalog\Tests covering tests/', function () use ($composerData): void {
    expect($composerData['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Catalog\\Tests\\');
    expect($composerData['autoload-dev']['psr-4']['Markommerce\\Catalog\\Tests\\'])->toBe('tests/');
});

it('requires php 8.5, markommerce/money interface package, marko/database, and marko/core', function () use ($composerData): void {
    expect($composerData['require'])->toHaveKey('php');
    expect($composerData['require']['php'])->toBe('^8.5');
    expect($composerData['require'])->toHaveKey('markommerce/money');
    expect($composerData['require'])->toHaveKey('marko/database');
    expect($composerData['require'])->toHaveKey('marko/core');
});

it('does not require markommerce/money-moneyphp directly per the architecture interface/driver rule', function () use ($composerData): void {
    $allRequire = array_merge(
        $composerData['require'] ?? [],
        $composerData['require-dev'] ?? [],
    );
    expect($allRequire)->not->toHaveKey('markommerce/money-moneyphp');
});

it('requires pestphp/pest and marko/testing as dev dependencies and does not require marko/database-mysql', function () use ($composerData): void {
    expect($composerData['require-dev'])->toHaveKey('pestphp/pest');
    expect($composerData['require-dev'])->toHaveKey('marko/testing');
    expect($composerData['require-dev'])->not->toHaveKey('marko/database-mysql');
});

it('is wired into the root composer.json require block', function (): void {
    $rootComposerPath = dirname(__DIR__, 4) . '/composer.json';
    $rootComposerData = json_decode(file_get_contents($rootComposerPath), true);
    expect($rootComposerData['require'])->toHaveKey('markommerce/catalog');
});

it('creates the required src tests Unit Feature and Support directory structure', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    expect(is_dir($packageRoot . '/src/Entity'))->toBeTrue();
    expect(is_dir($packageRoot . '/src/Repository'))->toBeTrue();
    expect(is_dir($packageRoot . '/src/Service'))->toBeTrue();
    expect(is_dir($packageRoot . '/src/Exception'))->toBeTrue();
    expect(is_dir($packageRoot . '/src/Event'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Unit'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Feature'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Support'))->toBeTrue();
});

it('does not create a database migrations directory because schema is declared via entity attributes', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    expect(is_dir($packageRoot . '/database/migrations'))->toBeFalse();
    expect(is_dir($packageRoot . '/database'))->toBeFalse();
});
