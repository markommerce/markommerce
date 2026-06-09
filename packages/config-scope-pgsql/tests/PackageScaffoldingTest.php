<?php

declare(strict_types=1);
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;

it('declares its name as markommerce/config-scope-pgsql with type marko-module in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('markommerce/config-scope-pgsql')
        ->and($composer['type'])->toBe('marko-module');
});

it('requires markommerce/config-scope and marko/database-pgsql as self.version dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/config-scope')
        ->and($composer['require']['markommerce/config-scope'])->toBe('self.version')
        ->and($composer['require'])->toHaveKey('marko/database-pgsql')
        ->and($composer['require']['marko/database-pgsql'])->toBe('self.version');
});

it('declares the Markommerce\\ConfigScope\\PgSql\\ namespace mapped to src/ in autoload psr-4', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\ConfigScope\\PgSql\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\ConfigScope\\PgSql\\'])->toBe('src/');
});

it(
    'declares the Markommerce\\ConfigScope\\PgSql\\Tests\\ namespace mapped to tests/ in autoload-dev psr-4',
    function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);
    
        expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\ConfigScope\\PgSql\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Markommerce\\ConfigScope\\PgSql\\Tests\\'])->toBe('tests/');
    }
);

it('binds ScopedConfigStorageInterface to a PgsqlScopedConfigStorage factory in module.php', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray()
        ->and($module['bindings'])->toHaveKey(ScopedConfigStorageInterface::class);
});

it('is registered in the root composer.json require block under markommerce/config-scope-pgsql', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/config-scope-pgsql')
        ->and($composer['require']['markommerce/config-scope-pgsql'])->toBe('self.version');
});
