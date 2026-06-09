<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Markommerce\Testing\Module\ModuleResolver;

it('reads all installed marko modules via module discovery', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $modules = $resolver->resolveAllInstalled();

    expect($modules)->not->toBeEmpty();
    expect($modules)->each->toBeInstanceOf(ModuleManifest::class);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);
    expect($names)->toContain('markommerce/catalog');
    expect($names)->toContain('marko/core');
});

it('includes a module.php boot closure when the package provides one', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $modules = $resolver->resolveFrom(['markommerce/catalog']);

    $catalog = array_find($modules, fn (ModuleManifest $m) => $m->name === 'markommerce/catalog');

    expect($catalog)->not->toBeNull();

    // catalog/module.php declares a boot closure
    expect($catalog->boot)->toBeInstanceOf(Closure::class);
});

it('resolves the full installed module set for fromInstalled', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $allModules = $resolver->resolveAllInstalled();
    $names = array_map(fn (ModuleManifest $m) => $m->name, $allModules);

    // Must include modules from both marko and markommerce vendors
    expect($names)->toContain('marko/core');
    expect($names)->toContain('markommerce/catalog');
    expect($names)->toContain('markommerce/config');

    // Count must cover all installed marko modules (42 known from installed.json)
    expect(count($allModules))->toBeGreaterThanOrEqual(42);
});

it('populates require on each manifest for dependency ordering', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $modules = $resolver->resolveFrom(['markommerce/catalog']);

    $catalog = array_find($modules, fn (ModuleManifest $m) => $m->name === 'markommerce/catalog');

    expect($catalog)->not->toBeNull();

    // require is populated and contains only non-platform entries
    expect($catalog->require)->not->toBeEmpty();

    // should contain module dependencies (filtered by ManifestParser to non-platform)
    expect($catalog->require)->toHaveKey('markommerce/config');
    expect($catalog->require)->toHaveKey('marko/core');

    // platform requirements must not appear in require
    expect($catalog->require)->not->toHaveKey('php');
    expect($catalog->require)->not->toHaveKey('ext-pdo');
});

it('resolves an absolute install path for each module that contains the package source', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $modules = $resolver->resolveFrom(['markommerce/catalog']);

    $catalog = array_find($modules, fn (ModuleManifest $m) => $m->name === 'markommerce/catalog');

    expect($catalog)->not->toBeNull();
    expect($catalog->path)->not->toBeEmpty();

    // Path must be absolute
    expect($catalog->path)->toStartWith('/');

    // Path must contain the source directory (src/Entity exists)
    expect(is_dir($catalog->path . '/src/Entity'))->toBeTrue();
});

it('excludes php platform requirements and non-module packages from the set', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $modules = $resolver->resolveFrom(['markommerce/catalog']);
    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    // Platform requirements must not appear
    expect($names)->not->toContain('php');
    expect($names)->not->toContain('ext-pdo');

    // Non-module composer packages must not appear
    expect($names)->not->toContain('psr/container');
    expect($names)->not->toContain('pestphp/pest');
});

it('resolves the transitive marko module set from a single root package', function (): void {
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver = new ModuleResolver($vendorDir);

    $modules = $resolver->resolveFrom(['markommerce/catalog']);

    expect($modules)->not->toBeEmpty();
    expect($modules)->each->toBeInstanceOf(ModuleManifest::class);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    // catalog itself
    expect($names)->toContain('markommerce/catalog');
    // direct dependencies that are marko modules
    expect($names)->toContain('markommerce/config');
    expect($names)->toContain('markommerce/currency');
    expect($names)->toContain('markommerce/money');
    expect($names)->toContain('markommerce/criteria');
    expect($names)->toContain('marko/core');
    expect($names)->toContain('marko/database');
});
