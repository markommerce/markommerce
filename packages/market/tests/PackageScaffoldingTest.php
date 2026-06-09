<?php

declare(strict_types=1);

it(
    'declares a market axis with default \'default\' and a single scope path \'default\' in config/scope.php',
    function (): void {
        $configPath = dirname(__DIR__) . '/config/scope.php';
    
        expect(file_exists($configPath))->toBeTrue();
    
        $config = require $configPath;
    
        expect($config)->toBeArray()
            ->and($config)->toHaveKey('axes')
            ->and($config['axes'])->toHaveKey('market')
            ->and($config['axes']['market']['default'])->toBe('default')
            ->and($config['axes']['market']['scopes'])->toBe(['default' => []]);
    }
);

it('requires markommerce/scope in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/scope');
});

it('declares itself as a marko-module via composer extra.marko.module=true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('uses the Markommerce\\Market\\ namespace for any future autoload', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Market\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Market\\'])->toBe('src/');
});

it(
    'registers the package in the root composer.json require block and adds Markommerce\\Market\\Tests\\ to autoload-dev.psr-4',
    function (): void {
        $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
        $rootComposer = json_decode(file_get_contents($rootComposerPath), true);
    
        expect($rootComposer['require'])->toHaveKey('markommerce/market')
            ->and($rootComposer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Market\\Tests\\')
            ->and($rootComposer['autoload-dev']['psr-4']['Markommerce\\Market\\Tests\\'])->toBe(
                'packages/market/tests/'
            );
    }
);
