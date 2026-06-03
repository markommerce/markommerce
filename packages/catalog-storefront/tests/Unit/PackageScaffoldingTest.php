<?php

declare(strict_types=1);

it(
    'creates packages/catalog-storefront/composer.json declaring markommerce/catalog-storefront as a marko-module with the correct require list',
    function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
    
        expect(file_exists($composerPath))->toBeTrue();
    
        $composer = json_decode(file_get_contents($composerPath), true);
    
        expect($composer['name'])->toBe('markommerce/catalog-storefront')
            ->and($composer['type'])->toBe('marko-module')
            ->and($composer['extra']['marko']['module'])->toBeTrue()
            ->and($composer['require'])->toHaveKey('php')
            ->and($composer['require']['php'])->toBe('^8.5')
            ->and($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require'])->toHaveKey('marko/database')
            ->and($composer['require'])->toHaveKey('marko/routing')
            ->and($composer['require'])->toHaveKey('marko/view')
            ->and($composer['require'])->toHaveKey('marko/view-latte')
            ->and($composer['require'])->toHaveKey('marko/config')
            ->and($composer['require'])->toHaveKey('markommerce/catalog')
            ->and($composer['require'])->toHaveKey('markommerce/layout')
            ->and($composer['require'])->toHaveKey('markommerce/frontend')
            ->and($composer['require'])->toHaveKey('markommerce/theme-blank');
    }
);

it('declares Markommerce\\CatalogStorefront\\ PSR-4 autoload mapping to src/', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\CatalogStorefront\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\CatalogStorefront\\'])->toBe('src/');
});

it('declares Markommerce\\CatalogStorefront\\Tests\\ PSR-4 autoload-dev mapping to tests/', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\CatalogStorefront\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\CatalogStorefront\\Tests\\'])->toBe('tests/');
});

it(
    'includes markommerce/catalog, markommerce/layout, markommerce/frontend, markommerce/theme-blank, marko/routing, marko/view, marko/view-latte in the require block',
    function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);
    
        expect($composer['require'])->toHaveKey('markommerce/catalog')
            ->and($composer['require'])->toHaveKey('markommerce/layout')
            ->and($composer['require'])->toHaveKey('markommerce/frontend')
            ->and($composer['require'])->toHaveKey('markommerce/theme-blank')
            ->and($composer['require'])->toHaveKey('marko/routing')
            ->and($composer['require'])->toHaveKey('marko/view')
            ->and($composer['require'])->toHaveKey('marko/view-latte');
    }
);

it(
    'creates packages/catalog-storefront/LICENSE, .gitattributes, src/.gitkeep, and tests/Pest.php matching the project scaffolding conventions',
    function (): void {
        $packageRoot = dirname(__DIR__, 2);
    
        expect(file_exists($packageRoot . '/LICENSE'))->toBeTrue();
        expect(file_exists($packageRoot . '/.gitattributes'))->toBeTrue();
        expect(file_exists($packageRoot . '/src/.gitkeep'))->toBeTrue();
        expect(file_exists($packageRoot . '/tests/Pest.php'))->toBeTrue();
    
        $license = file_get_contents($packageRoot . '/LICENSE');
        expect($license)->toContain('MIT License')
            ->and($license)->toContain('Devtomic LLC');
    
        $gitattributes = file_get_contents($packageRoot . '/.gitattributes');
        expect($gitattributes)->toContain('/tests')
            ->and($gitattributes)->toContain('export-ignore');
    }
);

it(
    'adds markommerce/catalog-storefront to the root composer.json require block alphabetically within the markommerce/* group',
    function (): void {
        $rootComposerPath = dirname(__DIR__, 4) . '/composer.json';
        $composer = json_decode(file_get_contents($rootComposerPath), true);
    
        expect($composer['require'])->toHaveKey('markommerce/catalog-storefront');
    
        $keys = array_keys($composer['require']);
        $markommerceKeys = array_values(
            array_filter($keys, fn (string $k): bool => str_starts_with($k, 'markommerce/'))
        );
        $storefrontIndex = array_search('markommerce/catalog-storefront', $markommerceKeys);
    
        expect($storefrontIndex)->not->toBeFalse();
    
        $sorted = $markommerceKeys;
        sort($sorted);
        expect($markommerceKeys)->toBe($sorted);
    }
);

it(
    'adds Markommerce\\CatalogStorefront\\Tests\\ to the root composer.json autoload-dev.psr-4 block',
    function (): void {
        $rootComposerPath = dirname(__DIR__, 4) . '/composer.json';
        $composer = json_decode(file_get_contents($rootComposerPath), true);
    
        expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\CatalogStorefront\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Markommerce\\CatalogStorefront\\Tests\\'])
            ->toBe('packages/catalog-storefront/tests/');
    }
);

it('passes composer validate on packages/catalog-storefront/composer.json', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $output = shell_exec(
        'composer validate --no-check-publish ' . escapeshellarg($packageRoot . '/composer.json') . ' 2>&1'
    );

    expect($output)->toContain('composer.json is valid');
});
