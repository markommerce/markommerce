<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;
use Markommerce\CatalogMarket\Plugins\CategoryTreeServiceDeletePlugin;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketResolver;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Testing\Container\ContainerBootstrapper;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Module\ModuleResolver;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function tier3VendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

/**
 * Build the config repository for the Tier 3 end-to-end test.
 */
function tier3BuildConfig(): ConfigRepository
{
    return new ConfigRepository([
        'scope' => [
            'axes' => [
                'locale' => [
                    'default' => 'default',
                    'scopes' => ['default' => []],
                ],
                'market' => [
                    'default' => 'default',
                    'scopes' => ['default' => []],
                ],
            ],
        ],
    ]);
}

/**
 * Build the manifest list for the Tier 3 end-to-end test.
 *
 * Uses ModuleResolver to get the transitive closure of catalog-market dependencies.
 *
 * @return list<ModuleManifest>
 */
function tier3BuildManifests(): array
{
    $resolver = new ModuleResolver(tier3VendorDir());

    return $resolver->resolveFrom(['markommerce/catalog-market']);
}

/**
 * Extract the catalog-market ModuleManifest from the resolved manifest list.
 *
 * Used by tests that verify PluginDiscovery against the bridge manifest.
 *
 * @param list<ModuleManifest> $manifests
 */
function tier3FindBridgeManifest(array $manifests): ModuleManifest
{
    foreach ($manifests as $manifest) {
        if ($manifest->name === 'markommerce/catalog-market') {
            return $manifest;
        }
    }

    throw new RuntimeException('catalog-market manifest not found in resolved manifest list');
}

/**
 * StoreProfile for the Tier 3 database provisioning.
 *
 * Uses catalog-market + pgsql driver so the DatabaseProvisioner can provision
 * the full catalog-market schema into a per-worker cloned database.
 */
function tier3StoreProfile(): StoreProfile
{
    return StoreProfile::of(
        tier3VendorDir(),
        'markommerce/catalog-market',
        'marko/database-pgsql',
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    // Use IntegrationTestCase to provision the database schema via per-worker clone.
    // This eliminates cross-worker table conflicts in parallel execution.
    $dbTestCase = new IntegrationTestCase(tier3StoreProfile());
    $dbTestCase->setUpIntegration();
    $this->dbTestCase = $dbTestCase;

    // Extract the shared connection from the provisioned store.
    // This is the same connection the DatabaseProvisioner's worker clone uses.
    /** @var ConnectionInterface $conn */
    $conn = $dbTestCase->store->container()->get(ConnectionInterface::class);

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $treeRepository = new CategoryTreeRepository($conn, $metadataFactory, $hydrator);
    $treeNodeRepository = new CategoryTreeNodeRepository($conn, $metadataFactory, $hydrator);
    $categoryRepository = new CategoryRepository($conn, $metadataFactory, $hydrator);
    $assignmentRepository = new CategoryTreeMarketAssignmentRepository($conn, $metadataFactory, $hydrator);

    $manifests = tier3BuildManifests();
    $config = tier3BuildConfig();

    $bootstrapper = new ContainerBootstrapper();

    // Build the container without booting yet so we can pre-bind repository instances.
    // The repositories require ConnectionInterface + EntityMetadataFactory + EntityHydrator;
    // pre-building them avoids needing a full pgsql driver in the manifest set.
    $this->container = $bootstrapper->build($manifests, $config, $conn);

    $this->container->instance(CategoryTreeRepositoryInterface::class, $treeRepository);
    $this->container->instance(CategoryTreeNodeRepositoryInterface::class, $treeNodeRepository);
    $this->container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $this->container->instance(CategoryTreeMarketAssignmentRepositoryInterface::class, $assignmentRepository);

    // Wire plugins BEFORE resolving CategoryTreeService — otherwise the delete-guard plugin
    // will not intercept deleteTree() calls.
    $bootstrapper->wirePlugins($this->container, $manifests);
    $bootstrapper->boot($this->container, $manifests);

    // Retrieve the pre-built repository instances from the container.
    $this->treeRepository = $this->container->get(CategoryTreeRepositoryInterface::class);
    $this->assignmentRepository = $this->container->get(CategoryTreeMarketAssignmentRepositoryInterface::class);

    // CategoryTreeService MUST be resolved through the container so the plugin interceptor fires.
    $this->treeService = $this->container->get(CategoryTreeService::class);

    $this->assignmentService = new CategoryTreeMarketAssignmentService(
        categoryTreeMarketAssignmentRepository: $assignmentRepository,
        categoryTreeRepository: $treeRepository,
    );

    $this->resolver = new CategoryTreeMarketResolver(
        categoryTreeMarketAssignmentRepository: $assignmentRepository,
        categoryTreeRepository: $treeRepository,
    );
});

afterEach(function (): void {
    DefaultScopeGuard::reset();

    if (isset($this->dbTestCase)) {
        $this->dbTestCase->tearDownIntegration();
        $this->dbTestCase->tearDownClass();
    }
});

it('wires PluginInterceptor into the test container BEFORE resolving CategoryTreeService (otherwise the delete-guard test is meaningless)', function (): void {
    // The container was built by the ContainerBootstrapper which wires PluginInterceptor
    // before any CategoryTreeService resolution. Verify the returned service is a
    // plugin proxy (not the raw CategoryTreeService class itself).
    $service = $this->container->get(CategoryTreeService::class);

    // The plugin targets CategoryTreeServiceInterface, so the proxy uses the interface
    // wrapper strategy — it implements PluginInterceptedInterface rather than being a
    // subclass of CategoryTreeService.
    expect($service)->toBeInstanceOf(PluginInterceptedInterface::class)
        ->and(get_class($service))->not->toBe(CategoryTreeService::class);
})->group('integration-destructive');

it('constructs the bridge ModuleManifest with the absolute filesystem path so PluginDiscovery can scan src/Plugins/', function (): void {
    $manifests = tier3BuildManifests();
    $manifest = tier3FindBridgeManifest($manifests);

    // The manifest path points to catalog-market (either direct source path or via vendor symlink).
    // Resolve symlinks to compare canonical paths.
    $expectedPath = (string) realpath(dirname(__DIR__, 2));
    $actualPath = (string) realpath($manifest->path);

    expect($actualPath)->toBe($expectedPath)
        ->and(is_dir($manifest->path . '/src/Plugins'))->toBeTrue();
})->group('integration-destructive');

it('discovers and registers CategoryTreeServiceDeletePlugin via PluginDiscovery::discoverInModule against the bridge manifest', function (): void {
    $manifests = tier3BuildManifests();
    $manifest = tier3FindBridgeManifest($manifests);

    $pluginDiscovery = new PluginDiscovery();
    $definitions = $pluginDiscovery->discoverInModule($manifest);

    $pluginClasses = array_map(fn ($d) => $d->pluginClass, $definitions);

    expect($pluginClasses)->toContain(CategoryTreeServiceDeletePlugin::class);
})->group('integration-destructive');

it('resolves the assigned non-default tree for each market that has an assignment', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    // Create a default tree and two non-default trees
    $defaultTree = $treeService->createTree('default', 'Default Tree', true);
    $usTree = $treeService->createTree('us', 'US Tree');
    $deTree = $treeService->createTree('de', 'DE Tree');

    /** @var CategoryTreeMarketAssignmentService $assignmentService */
    $assignmentService = $this->assignmentService;

    $assignmentService->assignTreeToMarket((int) $usTree->id, 'us');
    $assignmentService->assignTreeToMarket((int) $deTree->id, 'de');

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    $resolvedUs = $resolver->resolveTreeForMarket('us');
    $resolvedDe = $resolver->resolveTreeForMarket('de');

    expect($resolvedUs->id)->toBe($usTree->id)
        ->and($resolvedUs->code)->toBe('us')
        ->and($resolvedDe->id)->toBe($deTree->id)
        ->and($resolvedDe->code)->toBe('de');
})->group('integration-destructive');

it('falls back to the default tree for a market with no assignment', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    $defaultTree = $treeService->createTree('default', 'Default Tree', true);

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    $resolved = $resolver->resolveTreeForMarket('fr');

    expect($resolved->id)->toBe($defaultTree->id)
        ->and($resolved->isDefault)->toBeTrue();
})->group('integration-destructive');

it('throws DefaultTreeMissingException when neither an assignment nor a default tree exists', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    // Create a non-default tree only — no default tree exists
    $treeService->createTree('special', 'Special Tree');

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    expect(fn () => $resolver->resolveTreeForMarket('unknown'))
        ->toThrow(DefaultTreeMissingException::class);
})->group('integration-destructive');

it('throws TreeHasMarketAssignmentsException via the plugin when deleteTree is called on a tree that still serves a market', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    $defaultTree = $treeService->createTree('default', 'Default Tree', true);
    $usTree = $treeService->createTree('us', 'US Tree');

    /** @var CategoryTreeMarketAssignmentService $assignmentService */
    $assignmentService = $this->assignmentService;

    $assignmentService->assignTreeToMarket((int) $usTree->id, 'us');

    // deleteTree must be called on the plugin-proxied $treeService — not a raw instance
    expect(fn () => $treeService->deleteTree((int) $usTree->id))
        ->toThrow(TreeHasMarketAssignmentsException::class);
})->group('integration-destructive');

it('allows deleteTree on a non-default tree with no market assignments', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    $defaultTree = $treeService->createTree('default', 'Default Tree', true);
    $orphanTree = $treeService->createTree('orphan', 'Orphan Tree');

    // No assignment — deleteTree must succeed without throwing
    $treeService->deleteTree((int) $orphanTree->id);

    $found = $this->treeRepository->find((int) $orphanTree->id);
    expect($found)->toBeNull();
})->group('integration-destructive');

it('replaces an existing assignment when assignTreeToMarket is called twice for the same market with different trees', function (): void {
    /** @var CategoryTreeService $treeService */
    $treeService = $this->treeService;

    $defaultTree = $treeService->createTree('default', 'Default Tree', true);
    $firstTree = $treeService->createTree('first', 'First Tree');
    $secondTree = $treeService->createTree('second', 'Second Tree');

    /** @var CategoryTreeMarketAssignmentService $assignmentService */
    $assignmentService = $this->assignmentService;

    $assignmentService->assignTreeToMarket((int) $firstTree->id, 'us');
    $assignmentService->assignTreeToMarket((int) $secondTree->id, 'us');

    /** @var CategoryTreeMarketResolver $resolver */
    $resolver = $this->resolver;

    $resolved = $resolver->resolveTreeForMarket('us');

    expect($resolved->id)->toBe($secondTree->id)
        ->and($resolved->code)->toBe('second');
})->group('integration-destructive');
