<?php

declare(strict_types=1);
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

it('requires markommerce/catalog-scope and markommerce/market in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog-scope')
        ->and($composer['require'])->toHaveKey('markommerce/market')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['require'])->toHaveKey('markommerce/catalog');
});

it('declares itself as a marko-module via composer extra.marko.module=true', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('uses the Markommerce\\CatalogMarket\\ namespace for any future autoload', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\CatalogMarket\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\CatalogMarket\\'])->toBe('src/');
});

it('declares require entries for markommerce/catalog-scope and markommerce/market in module.php', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('require')
        ->and($module['require'])->toHaveKey('markommerce/catalog-scope')
        ->and($module['require'])->toHaveKey('markommerce/market')
        ->and($module['require'])->toHaveKey('markommerce/catalog');
});

it('ships an empty-but-callable boot closure typed on ScopedFieldRegistry', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toHaveKey('boot')
        ->and($module['boot'])->toBeCallable();

    $reflection = new ReflectionFunction($module['boot']);
    $parameters = $reflection->getParameters();

    expect($parameters)->toHaveCount(1);

    $type = $parameters[0]->getType();
    expect($type)->not->toBeNull()
        ->and((string) $type)->toBe(ScopedFieldRegistry::class);
});

it('registers the package in the root composer.json require block and adds Markommerce\\CatalogMarket\\Tests\\ to autoload-dev.psr-4', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $rootComposer = json_decode(file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('markommerce/catalog-market')
        ->and($rootComposer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\CatalogMarket\\Tests\\')
        ->and($rootComposer['autoload-dev']['psr-4']['Markommerce\\CatalogMarket\\Tests\\'])->toBe('packages/catalog-market/tests/');
});

it('registers no scoped fields when the boot closure runs against a fresh ScopedFieldRegistry', function (): void {
    $fakeScopeRegistry = new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return true;
        }

        public function getAxis(string $name): ScopeAxis
        {
            return new ScopeAxis(
                name: $name,
                hierarchy: ScopeHierarchy::fromPaths(['default']),
                default: 'default',
            );
        }

        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return ScopeHierarchy::fromPaths(['default']);
        }
    };

    $registry = new ScopedFieldRegistry(scopeRegistry: $fakeScopeRegistry);
    $boot = (require dirname(__DIR__) . '/module.php')['boot'];
    $boot($registry);

    expect($registry->hasScopedProperties(Product::class))->toBeFalse();
});
