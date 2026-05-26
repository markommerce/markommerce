<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;

it('declares a locale axis with default \'default\' and a single scope path \'default\' in config/scope.php', function (): void {
    $configPath = dirname(__DIR__) . '/config/scope.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('axes')
        ->and($config['axes'])->toHaveKey('locale')
        ->and($config['axes']['locale']['default'])->toBe('default')
        ->and($config['axes']['locale']['scopes'])->toBe(['default' => []]);
});

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

it('uses the Markommerce\\Locale\\ namespace for any future autoload (even though P2 ships no classes)', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Locale\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Locale\\'])->toBe('src/');
});

it('merges its locale axis into scope.axes when discovered alongside scope\'s own config/scope.php', function (): void {
    $discovery = new ConfigDiscovery(
        loader: new ConfigLoader(),
        merger: new ConfigMerger(),
    );

    $result = $discovery->discover(
        modulePaths: [
            dirname(__DIR__, 2) . '/scope',
            dirname(__DIR__),
        ],
        rootConfigPath: sys_get_temp_dir(),
    );

    expect($result)->toHaveKey('scope')
        ->and($result['scope']['axes'])->toHaveKey('market')
        ->and($result['scope']['axes'])->toHaveKey('channel')
        ->and($result['scope']['axes'])->toHaveKey('locale')
        ->and($result['scope']['axes']['locale']['default'])->toBe('default')
        ->and($result['scope']['axes']['locale']['scopes'])->toBe(['default' => []]);
});
