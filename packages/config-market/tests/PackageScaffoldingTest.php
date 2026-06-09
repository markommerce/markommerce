<?php

declare(strict_types=1);

it(
    'declares config-market\'s name as markommerce/config-market with type marko-module in composer.json',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['name'])->toBe('markommerce/config-market')
            ->and($composer['type'])->toBe('marko-module');
    }
);

it(
    'requires markommerce/config-scope and markommerce/market as self.version dependencies in config-market\'s composer.json',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['require'])->toHaveKey('markommerce/config-scope')
            ->and($composer['require']['markommerce/config-scope'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('markommerce/market')
            ->and($composer['require']['markommerce/market'])->toBe('self.version');
    }
);

it(
    'declares the Markommerce\\\\ConfigMarket\\\\ namespace mapped to src/ in config-market\'s autoload psr-4',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\ConfigMarket\\')
            ->and($composer['autoload']['psr-4']['Markommerce\\ConfigMarket\\'])->toBe('src/');
    }
);

it('is registered in the root composer.json require block under markommerce/config-market', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $rootComposer = json_decode((string) file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('markommerce/config-market');
});

it(
    'exposes a module.php that returns an array with require + a callable boot closure in config-market',
    function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';
    
        expect(file_exists($modulePath))->toBeTrue();
    
        $module = require $modulePath;
    
        expect($module)->toBeArray()
            ->and($module)->toHaveKey('require')
            ->and($module)->toHaveKey('boot')
            ->and($module['boot'])->toBeCallable();
    }
);
