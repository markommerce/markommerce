<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Marko\Core\Module\ModuleRepository;

// ─── Compile-time helpers (no DB needed) ──────────────────────────────────────

/**
 * Paths to the Tier 1 packages, resolved relative to the test file location.
 *
 * __DIR__ = packages/catalog-storefront/tests/Unit
 * dirname 4 levels up = markommerce root
 * append /packages to reach the packages directory
 */
function tier1CompilePackagesRoot(): string
{
    return dirname(__DIR__, 4) . '/packages';
}

/**
 * Path to the marko framework packages root.
 *
 * __DIR__ = packages/catalog-storefront/tests/Unit
 * dirname 5 levels up = parent of markommerce root
 * append /marko/packages to reach the marko framework packages
 */
function tier1CompileMarkoPackagesRoot(): string
{
    return dirname(__DIR__, 5) . '/marko/packages';
}

/**
 * Build all Tier 1 module manifests pointing at their real package paths.
 *
 * @return list<ModuleManifest>
 */
function buildTier1CompileManifests(): array
{
    $pkgRoot = tier1CompilePackagesRoot();
    $markoRoot = tier1CompileMarkoPackagesRoot();

    return [
        new ModuleManifest(
            name: 'marko/config',
            version: '1.0.0',
            path: $markoRoot . '/config',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/core',
            version: '1.0.0',
            path: $markoRoot . '/core',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/database',
            version: '1.0.0',
            path: $markoRoot . '/database',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/routing',
            version: '1.0.0',
            path: $markoRoot . '/routing',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/view',
            version: '1.0.0',
            path: $markoRoot . '/view',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/view-latte',
            version: '1.0.0',
            path: $markoRoot . '/view-latte',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/catalog',
            version: '1.0.0',
            path: $pkgRoot . '/catalog',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $pkgRoot . '/catalog-storefront',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/config',
            version: '1.0.0',
            path: $pkgRoot . '/config',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/config-pgsql',
            version: '1.0.0',
            path: $pkgRoot . '/config-pgsql',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/layout',
            version: '1.0.0',
            path: $pkgRoot . '/layout',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/frontend',
            version: '1.0.0',
            path: $pkgRoot . '/frontend',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/theme-blank',
            version: '1.0.0',
            path: $pkgRoot . '/theme-blank',
            source: 'vendor',
        ),
    ];
}

/**
 * Build a focused ModuleRepository for layout discovery and template resolution.
 *
 * Only includes modules that contribute valid layouts and templates for the Tier 1
 * storefront rendering pipeline. Excludes markommerce/catalog which still contains
 * a legacy layout file from before Task 002 moved the storefront code.
 */
function buildTier1CompileRenderModuleRepository(): ModuleRepository
{
    $pkgRoot = tier1CompilePackagesRoot(); // .../packages

    return new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $pkgRoot . '/catalog-storefront',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/theme-blank',
            version: '1.0.0',
            path: $pkgRoot . '/theme-blank',
            source: 'vendor',
        ),
    ]);
}

/**
 * Compile the catalog-storefront + theme-blank layouts for the Tier 1 test.
 *
 * Uses the focused render module repository (catalog-storefront + theme-blank only).
 *
 * @return array<string, PreparedTree>
 */
function buildTier1CompileArtifact(): array
{
    $moduleRepository = buildTier1CompileRenderModuleRepository();
    $layoutDiscovery = new LayoutDiscovery($moduleRepository);
    $resolutionPhase = new ResolutionPhase();
    $validationPhase = new ValidationPhase();
    $treeBuilder = new PreparedTreeBuilder();
    $compiler = new Compiler($layoutDiscovery, $resolutionPhase, $validationPhase, $treeBuilder);

    return $compiler->compile();
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'boots the Tier 1 module manifest stack — catalog + catalog-storefront + config + config-pgsql + layout + frontend + theme-blank — without referencing any scope or locale module',
    function (): void {
        $manifests = buildTier1CompileManifests();

        $names = array_map(fn (ModuleManifest $m) => $m->name, $manifests);

        // Tier 1 stack is present
        expect($names)->toContain('markommerce/catalog')
            ->and($names)->toContain('markommerce/catalog-storefront')
            ->and($names)->toContain('markommerce/config')
            ->and($names)->toContain('markommerce/config-pgsql')
            ->and($names)->toContain('markommerce/layout')
            ->and($names)->toContain('markommerce/frontend')
            ->and($names)->toContain('markommerce/theme-blank');

        // Scope and locale modules are absent from this manifest list
        expect($names)->not->toContain('markommerce/scope')
            ->and($names)->not->toContain('markommerce/scope-pgsql')
            ->and($names)->not->toContain('markommerce/locale')
            ->and($names)->not->toContain('markommerce/catalog-scope')
            ->and($names)->not->toContain('markommerce/catalog-locale')
            ->and($names)->not->toContain('markommerce/catalog-storefront-scope');

        // All paths point to real directories
        foreach ($manifests as $manifest) {
            if ($manifest->path !== '') {
                expect(is_dir($manifest->path))->toBeTrue(
                    "Module $manifest->name path does not exist: $manifest->path",
                );
            }
        }
    },
);

it(
    'compiles the catalog-storefront category_show layout against the LayoutDiscovery and yields a PreparedTree for the controller handle',
    function (): void {
        $trees = buildTier1CompileArtifact();

        $handleKey = CategoryController::class . '::show';
        expect($trees)->toHaveKey($handleKey);
        expect($trees[$handleKey])->toBeInstanceOf(PreparedTree::class);
        expect($trees[$handleKey]->handleKey)->toBe($handleKey);
    },
);
