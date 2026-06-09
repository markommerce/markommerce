<?php

declare(strict_types=1);

it('declares its name as markommerce/config-scope in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('markommerce/config-scope');
});

it('declares its type as marko-module in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module');
});

it('requires markommerce/config and markommerce/scope as self.version dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/config')
        ->and($composer['require']['markommerce/config'])->toBe('self.version')
        ->and($composer['require'])->toHaveKey('markommerce/scope')
        ->and($composer['require']['markommerce/scope'])->toBe('self.version');
});

it('declares the Markommerce\\ConfigScope\\ namespace mapped to src/ in autoload psr-4', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\ConfigScope\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\ConfigScope\\'])->toBe('src/');
});

it(
    'declares the Markommerce\\ConfigScope\\Tests\\ namespace mapped to tests/ in autoload-dev psr-4',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);
    
        expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\ConfigScope\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Markommerce\\ConfigScope\\Tests\\'])->toBe('tests/');
    }
);

it('declares extra.marko.module true in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('exposes a module.php that returns an array with a callable boot closure', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('boot')
        ->and($module['boot'])->toBeCallable();
});

it('is registered in the root composer.json require block under markommerce/config-scope', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $rootComposer = json_decode(file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('markommerce/config-scope');
});
