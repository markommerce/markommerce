<?php

declare(strict_types=1);

it(
    'declares config-locale\'s name as markommerce/config-locale with type marko-module in composer.json',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['name'])->toBe('markommerce/config-locale')
            ->and($composer['type'])->toBe('marko-module');
    }
);

it(
    'requires markommerce/config-scope and markommerce/locale as self.version dependencies in config-locale\'s composer.json',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['require'])->toHaveKey('markommerce/config-scope')
            ->and($composer['require']['markommerce/config-scope'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('markommerce/locale')
            ->and($composer['require']['markommerce/locale'])->toBe('self.version');
    }
);

it(
    'declares the Markommerce\\\\ConfigLocale\\\\ namespace mapped to src/ in config-locale\'s autoload psr-4',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\ConfigLocale\\')
            ->and($composer['autoload']['psr-4']['Markommerce\\ConfigLocale\\'])->toBe('src/');
    }
);

it('is registered in the root composer.json require block under markommerce/config-locale', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $rootComposer = json_decode((string) file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('markommerce/config-locale');
});

it(
    'exposes a module.php that returns an array with require + a callable boot closure in config-locale',
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
