<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers/PostgresTestConnection.php';

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarketCategoryTrees\Exceptions\TreeHasMarketAssignmentsException;
use Markommerce\CatalogMarketCategoryTrees\Plugins\CategoryTreeServiceDeletePlugin;
use Markommerce\CatalogMarketCategoryTrees\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\CatalogMarketCategoryTrees\Services\CategoryTreeMarketAssignmentService;
use Markommerce\CatalogMarketCategoryTrees\Services\CategoryTreeMarketResolver;
use Markommerce\CatalogMarketCategoryTrees\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container pre-wired with all Tier 3 module bindings/singletons.
 *
 * Includes:
 * - scope module singletons and bindings
 * - catalog repository interfaces bound to pre-built instances (so the container
 *   can resolve CategoryTreeService without needing a database driver binding)
 * - catalog-market-category-trees repository interface bound as instance
 * - PluginInterceptor + PluginRegistry wired BEFORE any service resolution
 */
function buildTier3Container(PostgresTestConnection $connection): Container
{
    $config = new ConfigRepository([
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

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    // ── Scope module singletons ──────────────────────────────────────────────
    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // ── Catalog repository instances (avoid database driver auto-wiring issue) ─
    // Repositories require ConnectionInterface + nullable dependencies that the
    // container's auto-wiring cannot satisfy without a full driver binding.
    // Pre-build instances and register them so CategoryTreeService resolves cleanly.
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $treeRepository = new CategoryTreeRepository($connection, $metadataFactory, $hydrator);
    $treeNodeRepository = new CategoryTreeNodeRepository($connection, $metadataFactory, $hydrator);
    $categoryRepository = new CategoryRepository($connection, $metadataFactory, $hydrator);
    $assignmentRepository = new CategoryTreeMarketAssignmentRepository($connection, $metadataFactory, $hydrator);

    $container->instance(CategoryTreeRepositoryInterface::class, $treeRepository);
    $container->instance(CategoryTreeNodeRepositoryInterface::class, $treeNodeRepository);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(CategoryTreeMarketAssignmentRepositoryInterface::class, $assignmentRepository);

    // ── Wire PluginInterceptor BEFORE any service resolution ─────────────────
    $pluginRegistry = new PluginRegistry();
    $interceptor = new PluginInterceptor($container, $pluginRegistry, new InterceptorClassGenerator());
    $container->setPluginInterceptor($interceptor);
    $container->instance(PluginInterceptor::class, $interceptor);
    $container->instance(PluginRegistry::class, $pluginRegistry);

    // ── Discover and register CategoryTreeServiceDeletePlugin ────────────────
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';

    $bridgeManifest = new ModuleManifest(
        name: 'markommerce/catalog-market-category-trees',
        version: '1.0.0',
        require: $bridgeModule['require'] ?? ['markommerce/catalog' => '*', 'markommerce/market' => '*'],
        path: dirname(__DIR__, 2),
    );

    $pluginDiscovery = new PluginDiscovery();

    foreach ($pluginDiscovery->discoverInModule($bridgeManifest) as $definition) {
        $pluginRegistry->register($definition);
    }

    return $container;
}

/**
 * Build a ModuleManifest for the scope module.
 */
function tier3ScopeManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 3) . '/scope/module.php';

    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
        boot: $module['boot'],
    );
}

/**
 * Build a ModuleManifest for the locale module.
 */
function tier3LocaleManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/locale',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the market module (provides market scope axis config).
 */
function tier3MarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the catalog module.
 */
function tier3CatalogManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the catalog-scope module.
 */
function tier3CatalogScopeManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog-scope',
        version: '1.0.0',
        require: [
            'markommerce/catalog' => '*',
            'markommerce/scope' => '*',
        ],
    );
}

/**
 * Build a ModuleManifest for the catalog-locale module.
 */
function tier3CatalogLocaleManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 3) . '/catalog-locale/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-locale',
        version: '1.0.0',
        require: $bridgeModule['require'],
        boot: $bridgeModule['boot'],
    );
}

/**
 * Build a ModuleManifest for the catalog-market module (no-op boot).
 */
function tier3CatalogMarketManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 3) . '/catalog-market/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-market',
        version: '1.0.0',
        require: $bridgeModule['require'] ?? ['markommerce/catalog-scope' => '*', 'markommerce/market' => '*'],
        boot: $bridgeModule['boot'] ?? null,
    );
}

/**
 * Build a ModuleManifest for the catalog-market-category-trees bridge module.
 * The path is set to the absolute package path so PluginDiscovery can scan src/Plugins/.
 */
function tier3BridgeManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-market-category-trees',
        version: '1.0.0',
        require: $bridgeModule['require'] ?? ['markommerce/catalog' => '*', 'markommerce/market' => '*'],
        path: dirname(__DIR__, 2),
    );
}

/**
 * Boot all modules in dependency order.
 *
 * @param ModuleManifest[] $ordered
 */
function runTier3BootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

/**
 * Resolve manifests via DependencyResolver and run boot closures.
 */
function bootTier3(Container $container): void
{
    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        tier3ScopeManifest(),
        tier3LocaleManifest(),
        tier3MarketManifest(),
        tier3CatalogManifest(),
        tier3CatalogScopeManifest(),
        tier3CatalogLocaleManifest(),
        tier3CatalogMarketManifest(),
        tier3BridgeManifest(),
    ]);

    runTier3BootLoop($ordered, $container);
}

/**
 * Create the required database tables for the Tier 3 test.
 */
function createTier3Tables(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_category_tree_market_assignments CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_category_tree_nodes CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_category_trees CASCADE');

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_trees (
            id         SERIAL PRIMARY KEY,
            code       VARCHAR(64) NOT NULL UNIQUE,
            name       VARCHAR(255) NOT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE
        )',
    );

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_categories (
            id          SERIAL PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            description TEXT
        )',
    );

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_tree_nodes (
            id             SERIAL PRIMARY KEY,
            tree_id        INTEGER NOT NULL REFERENCES catalog_category_trees(id) ON DELETE CASCADE,
            category_id    INTEGER NOT NULL REFERENCES catalog_categories(id) ON DELETE RESTRICT,
            parent_node_id INTEGER REFERENCES catalog_category_tree_nodes(id) ON DELETE CASCADE,
            position       INTEGER NOT NULL DEFAULT 0
        )',
    );

    $conn->execute(
        'CREATE TABLE IF NOT EXISTS catalog_category_tree_market_assignments (
            market  VARCHAR(64) PRIMARY KEY,
            tree_id INTEGER REFERENCES catalog_category_trees(id) ON DELETE RESTRICT
        )',
    );
}

/**
 * Drop the Tier 3 tables.
 */
function dropTier3Tables(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS catalog_category_tree_market_assignments CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_category_tree_nodes CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
    $conn->execute('DROP TABLE IF EXISTS catalog_category_trees CASCADE');
}

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $this->conn = new PostgresTestConnection();
    createTier3Tables($this->conn);

    // buildTier3Container creates repositories internally and registers them as
    // instances. PluginInterceptor is wired before CategoryTreeService is resolved.
    $this->container = buildTier3Container($this->conn);
    bootTier3($this->container);

    // Retrieve the pre-built repository instances from the container.
    $this->treeRepository = $this->container->get(CategoryTreeRepositoryInterface::class);
    $this->assignmentRepository = $this->container->get(CategoryTreeMarketAssignmentRepositoryInterface::class);

    // CategoryTreeService MUST be resolved through the container so the plugin interceptor fires.
    $this->treeService = $this->container->get(CategoryTreeService::class);

    $this->assignmentService = new CategoryTreeMarketAssignmentService(
        categoryTreeMarketAssignmentRepository: $this->assignmentRepository,
        categoryTreeRepository: $this->treeRepository,
    );

    $this->resolver = new CategoryTreeMarketResolver(
        categoryTreeMarketAssignmentRepository: $this->assignmentRepository,
        categoryTreeRepository: $this->treeRepository,
    );
});

afterEach(function (): void {
    if (isset($this->conn)) {
        dropTier3Tables($this->conn);
    }
});

it('wires PluginInterceptor into the test container BEFORE resolving CategoryTreeService (otherwise the delete-guard test is meaningless)', function (): void {
    // The container was built by buildTier3Container() which wires PluginInterceptor
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
    $manifest = tier3BridgeManifest();

    $expectedPath = dirname(__DIR__, 2);

    expect($manifest->path)->toBe($expectedPath)
        ->and(is_dir($manifest->path . '/src/Plugins'))->toBeTrue();
})->group('integration-destructive');

it('discovers and registers CategoryTreeServiceDeletePlugin via PluginDiscovery::discoverInModule against the bridge manifest', function (): void {
    $manifest = tier3BridgeManifest();

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
