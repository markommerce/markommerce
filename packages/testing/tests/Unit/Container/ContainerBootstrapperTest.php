<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\CatalogMarket\Services\CategoryTreeMarketAssignmentService;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Command\SetCommand;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\PgSql\PgsqlConfigStorage;
use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\ConfigScope\Cache\ScopedCachingConfigResolver;
use Markommerce\ConfigScope\Command\ScopedSetCommand;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\PgSql\PgsqlScopedConfigStorage;
use Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter;
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Testing\Container\ContainerBootstrapper;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Module\ModuleResolver;
use Markommerce\Testing\Profile\StoreProfile;
use Markommerce\Testing\Tests\Fixture\Container\NullConfigStorage;
use Markommerce\Testing\Tests\Fixture\Container\NullConnection;
use Markommerce\Testing\Tests\Fixture\Container\NullScopedConfigStorage;

// ─── Test helpers ─────────────────────────────────────────────────────────────

function bootstrapperVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function bootstrapperConfig(): ConfigRepository
{
    return new ConfigRepository([
        'scope' => [
            'axes' => [
                'locale' => [
                    'default' => 'default',
                    'scopes' => [
                        'default' => [],
                        'de' => [],
                    ],
                ],
                'market' => [
                    'default' => 'default',
                    'scopes' => [
                        'default' => [],
                    ],
                ],
            ],
        ],
    ]);
}

function buildConfigScopeManifests(): array
{
    $resolver = new ModuleResolver(bootstrapperVendorDir());

    return $resolver->resolveFrom(['markommerce/config-scope']);
}

function buildCatalogMarketManifests(): array
{
    $resolver = new ModuleResolver(bootstrapperVendorDir());

    return $resolver->resolveFrom(['markommerce/catalog-market']);
}

function buildConfigScopePgsqlManifests(): array
{
    $resolver = new ModuleResolver(bootstrapperVendorDir());

    return $resolver->resolveFrom(['markommerce/config-scope-pgsql', 'markommerce/config-pgsql']);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('discovers and registers preferences from modules with a path', function (): void {
    $manifests = buildConfigScopeManifests();

    $bootstrapper = new ContainerBootstrapper();
    $registry = $bootstrapper->discoverPreferences($manifests);

    expect($registry)->toBeInstanceOf(PreferenceRegistry::class);

    // ScopedConfigWriter replaces ConfigWriter — should be registered
    $preference = $registry->getPreference(ConfigWriter::class);
    expect($preference)->toBe(ScopedConfigWriter::class);
});

it('builds a container with core singletons bound', function (): void {
    $manifests = buildConfigScopeManifests();
    $config = bootstrapperConfig();
    $connection = new NullConnection();

    $bootstrapper = new ContainerBootstrapper();
    $container = $bootstrapper->build($manifests, $config, $connection);

    expect($container)->toBeInstanceOf(Container::class);

    // Core singletons
    expect($container->get(ContainerInterface::class))->toBe($container);
    expect($container->get(PreferenceRegistry::class))->toBeInstanceOf(PreferenceRegistry::class);
    expect($container->get(ConfigRepositoryInterface::class))->toBe($config);
    expect($container->get(ConnectionInterface::class))->toBe($connection);
    expect($container->get(ProjectPaths::class))->toBeInstanceOf(ProjectPaths::class);
    expect($container->get(ModuleRepositoryInterface::class))->toBeInstanceOf(ModuleRepositoryInterface::class);
});

it('registers bindings and singletons from each module manifest', function (): void {
    // config/module.php binds SecretCipherInterface which reads MARKOMMERCE_CONFIG_SECRET_KEY
    $envKey = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $envKey);

    try {
        $config = bootstrapperConfig();
        $connection = new NullConnection();

        // Use custom manifests with a simple binding and singleton to verify registration
        $manifestA = new ModuleManifest(
            name: 'test/a',
            version: '1.0.0',
            bindings: [
                ContainerInterface::class => Container::class,
            ],
            singletons: [
                ProjectPaths::class,
            ],
        );

        $bootstrapper = new ContainerBootstrapper();
        $container = $bootstrapper->build([$manifestA], $config, $connection);

        // Verify the binding was registered — resolving ContainerInterface returns the container
        expect($container->get(ContainerInterface::class))->toBeInstanceOf(Container::class);

        // For scope module: bindings from module manifests should be applied
        $scopeManifests = buildConfigScopeManifests();
        $container2 = $bootstrapper->build($scopeManifests, $config, $connection);

        // scope/module.php binds ScopeRegistryInterface — check it was registered and resolves
        expect($container2->get(ScopeRegistryInterface::class))
            ->toBeInstanceOf(ScopeRegistryInterface::class);

        // config/module.php registers ConfigResolver as a singleton — verify it's in singletons
        // (singleton registration is a type of binding registration from manifest)
        // We can verify by checking that it resolves to the same instance twice (shared/singleton)
        $container3 = $bootstrapper->build($scopeManifests, $config, $connection);
        $container3->instance(ConfigStorageInterface::class, new NullConfigStorage());
        $container3->instance(ScopedConfigStorageInterface::class, new NullScopedConfigStorage());
        $bootstrapper->boot($container3, $scopeManifests);

        $resolverA = $container3->get(ConfigResolver::class);
        $resolverB = $container3->get(ConfigResolver::class);
        expect($resolverA)->toBe($resolverB); // singleton — same instance
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
});

it('boots modules in dependency order', function (): void {
    $config = bootstrapperConfig();
    $connection = new NullConnection();

    $bootOrder = [];

    // Create custom manifests where we can track boot order
    $manifestA = new ModuleManifest(
        name: 'test/a',
        version: '1.0.0',
        boot: static function () use (&$bootOrder): void {
            $bootOrder[] = 'a';
        },
    );

    $manifestB = new ModuleManifest(
        name: 'test/b',
        version: '1.0.0',
        require: ['test/a' => '*'],
        boot: static function () use (&$bootOrder): void {
            $bootOrder[] = 'b';
        },
    );

    // B depends on A, so A must be booted first
    $testManifests = [$manifestB, $manifestA];

    $bootstrapper = new ContainerBootstrapper();
    $container = $bootstrapper->build($testManifests, $config, $connection);
    $bootstrapper->boot($container, $testManifests);

    expect($bootOrder)->toBe(['a', 'b']);
});

it('binds the connection so resolved repositories use the profile database', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));

    try {
        $conn = new TestConnection();

        // Create tables
        $configValuesEmitter = new ConfigValuesTableEmitter();
        foreach ($configValuesEmitter->createStatements() as $sql) {
            $conn->execute($sql);
        }
        $overridesEmitter = new ConfigValueOverridesTableEmitter();
        foreach ($overridesEmitter->createStatements() as $sql) {
            $conn->execute($sql);
        }

        try {
            // Use manifests that include the pgsql storage drivers so
            // ConfigStorageInterface and ScopedConfigStorageInterface get bound
            $manifests = buildConfigScopePgsqlManifests();
            $config = bootstrapperConfig();

            $bootstrapper = new ContainerBootstrapper();
            $container = $bootstrapper->build($manifests, $config, $conn);
            $bootstrapper->boot($container, $manifests);

            // ConfigStorageInterface resolves to PgsqlConfigStorage using our shared connection
            $storage = $container->get(ConfigStorageInterface::class);
            expect($storage)->toBeInstanceOf(PgsqlConfigStorage::class);

            // ScopedConfigStorageInterface resolves to PgsqlScopedConfigStorage using shared conn
            $scopedStorage = $container->get(ScopedConfigStorageInterface::class);
            expect($scopedStorage)->toBeInstanceOf(PgsqlScopedConfigStorage::class);

            // The resolved connection must be the SAME instance that was passed in (shared for rollback)
            $resolvedConn = $container->get(ConnectionInterface::class);
            expect($resolvedConn)->toBe($conn);
        } finally {
            $conn->execute('DROP TABLE IF EXISTS config_value_overrides');
            $conn->execute('DROP TABLE IF EXISTS config_values');
        }
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('applies a preference override so the replacement implementation is resolved', function (): void {
    // config/module.php binds SecretCipherInterface which reads MARKOMMERCE_CONFIG_SECRET_KEY
    $envKey = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $envKey);

    try {
        $manifests = buildConfigScopeManifests();
        $config = bootstrapperConfig();
        $connection = new NullConnection();

        $bootstrapper = new ContainerBootstrapper();
        $container = $bootstrapper->build($manifests, $config, $connection);

        // Bind null storage implementations so the resolution chain for SetCommand works
        $container->instance(ConfigStorageInterface::class, new NullConfigStorage());
        $container->instance(ScopedConfigStorageInterface::class, new NullScopedConfigStorage());

        // Boot populates ConfigRegistry singleton (needed to resolve SetCommand → ScopedSetCommand)
        $bootstrapper->boot($container, $manifests);

        // ScopedSetCommand has #[Preference(replaces: SetCommand::class)]
        // Resolving SetCommand::class should return ScopedSetCommand via preference
        $setCommand = $container->get(SetCommand::class);
        expect($setCommand)->toBeInstanceOf(ScopedSetCommand::class);
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
});

it('wires the plugin interceptor before resolving any decorated service', function (): void {
    $manifests = buildCatalogMarketManifests();
    $config = bootstrapperConfig();
    $connection = new NullConnection();

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    $bootstrapper = new ContainerBootstrapper();
    $container = $bootstrapper->build($manifests, $config, $connection);

    // Wire up repository instances needed by CategoryTreeService
    $container->instance(
        CategoryTreeRepositoryInterface::class,
        new CategoryTreeRepository($connection, $metadataFactory, $hydrator),
    );
    $container->instance(
        CategoryTreeNodeRepositoryInterface::class,
        new CategoryTreeNodeRepository($connection, $metadataFactory, $hydrator),
    );
    $container->instance(
        CategoryRepositoryInterface::class,
        new CategoryRepository($connection, $metadataFactory, $hydrator),
    );
    $container->instance(
        CategoryTreeMarketAssignmentRepositoryInterface::class,
        new CategoryTreeMarketAssignmentRepository($connection, $metadataFactory, $hydrator),
    );

    // PluginInterceptor must be wired before resolving decorated service
    $bootstrapper->wirePlugins($container, $manifests);

    // Verify the PluginInterceptor is registered
    expect($container->get(PluginInterceptor::class))->toBeInstanceOf(PluginInterceptor::class);
    expect($container->get(PluginRegistry::class))->toBeInstanceOf(PluginRegistry::class);
});

it('discovers and registers module plugins from manifests with a path', function (): void {
    $manifests = buildCatalogMarketManifests();
    $config = bootstrapperConfig();
    $connection = new NullConnection();

    $bootstrapper = new ContainerBootstrapper();
    $container = $bootstrapper->build($manifests, $config, $connection);
    $bootstrapper->wirePlugins($container, $manifests);

    // The PluginRegistry must know about the CategoryTreeServiceDeletePlugin
    $registry = $container->get(PluginRegistry::class);
    expect($registry)->toBeInstanceOf(PluginRegistry::class);

    // The plugin targets CategoryTreeServiceInterface
    expect($registry->hasPluginsFor(CategoryTreeServiceInterface::class))->toBeTrue();
});

it('wires plugins so a plugin-decorated service method is intercepted', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    try {
        $conn = new TestConnection();

        // Create catalog tables
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

        try {
            $manifests = buildCatalogMarketManifests();
            $config = bootstrapperConfig();

            $metadataFactory = new EntityMetadataFactory();
            $hydrator = new EntityHydrator($metadataFactory);

            $treeRepository = new CategoryTreeRepository($conn, $metadataFactory, $hydrator);
            $treeNodeRepository = new CategoryTreeNodeRepository($conn, $metadataFactory, $hydrator);
            $categoryRepository = new CategoryRepository($conn, $metadataFactory, $hydrator);
            $assignmentRepository = new CategoryTreeMarketAssignmentRepository($conn, $metadataFactory, $hydrator);

            $bootstrapper = new ContainerBootstrapper();
            $container = $bootstrapper->build($manifests, $config, $conn);

            $container->instance(CategoryTreeRepositoryInterface::class, $treeRepository);
            $container->instance(CategoryTreeNodeRepositoryInterface::class, $treeNodeRepository);
            $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
            $container->instance(CategoryTreeMarketAssignmentRepositoryInterface::class, $assignmentRepository);

            // Wire plugins BEFORE resolving CategoryTreeService
            $bootstrapper->wirePlugins($container, $manifests);
            $bootstrapper->boot($container, $manifests);

            // Resolve CategoryTreeService via container — must be plugin-proxied
            $treeService = $container->get(CategoryTreeService::class);
            expect($treeService)->toBeInstanceOf(PluginInterceptedInterface::class);

            // Create a tree and try to delete it — plugin should throw if market assignments exist
            $defaultTree = $treeService->createTree('default', 'Default', true);
            $usTree = $treeService->createTree('us', 'US Tree');

            $assignmentService = new CategoryTreeMarketAssignmentService(
                categoryTreeMarketAssignmentRepository: $assignmentRepository,
                categoryTreeRepository: $treeRepository,
            );
            $assignmentService->assignTreeToMarket((int) $usTree->id, 'us');

            expect(fn () => $treeService->deleteTree((int) $usTree->id))
                ->toThrow(TreeHasMarketAssignmentsException::class);
        } finally {
            $conn->execute('DROP TABLE IF EXISTS catalog_category_tree_market_assignments CASCADE');
            $conn->execute('DROP TABLE IF EXISTS catalog_category_tree_nodes CASCADE');
            $conn->execute('DROP TABLE IF EXISTS catalog_categories CASCADE');
            $conn->execute('DROP TABLE IF EXISTS catalog_category_trees CASCADE');
        }
    } finally {
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('keeps existing profiles booting unchanged when no base path is provided', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $envKey = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $envKey);

    try {
        $conn = new TestConnection();
        $profile = StoreProfile::simple(bootstrapperVendorDir());

        // boot() called WITHOUT base path — should behave exactly as before
        $store = $profile->boot($conn);

        $resolvedConn = $store->get(ConnectionInterface::class);
        expect($resolvedConn)->toBe($conn);

        // ProjectPaths should default to per-process temp dir
        $projectPaths = $store->container()->get(ProjectPaths::class);
        expect($projectPaths->base)->toBe(sys_get_temp_dir() . '/markommerce-bootstrapper-' . getmypid());
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('defaults ProjectPaths to a per-process temp base when no base path is given', function (): void {
    $manifests = buildConfigScopeManifests();
    $config = bootstrapperConfig();
    $connection = new NullConnection();
    $expectedDefault = sys_get_temp_dir() . '/markommerce-bootstrapper-' . getmypid();

    $bootstrapper = new ContainerBootstrapper();
    $container = $bootstrapper->build($manifests, $config, $connection);

    $projectPaths = $container->get(ProjectPaths::class);
    expect($projectPaths->base)->toBe($expectedDefault);
});

it('binds ProjectPaths to a caller-provided base path', function (): void {
    $manifests = buildConfigScopeManifests();
    $config = bootstrapperConfig();
    $connection = new NullConnection();
    $customBasePath = sys_get_temp_dir() . '/test-custom-base-' . getmypid();

    $bootstrapper = new ContainerBootstrapper();
    $container = $bootstrapper->build($manifests, $config, $connection, $customBasePath);

    $projectPaths = $container->get(ProjectPaths::class);
    expect($projectPaths)->toBeInstanceOf(ProjectPaths::class);
    expect($projectPaths->base)->toBe($customBasePath);
});

it(
    'skips the ConfigResolver and CachingConfigResolver preferences so the explicit factory binding wins',
    function (): void {
        // config/module.php binds SecretCipherInterface which reads MARKOMMERCE_CONFIG_SECRET_KEY
    $envKey = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $envKey);
    
        try {
            $manifests = buildConfigScopeManifests();
            $config = bootstrapperConfig();
            $connection = new NullConnection();
    
            $bootstrapper = new ContainerBootstrapper();
            $registry = $bootstrapper->discoverPreferences($manifests);
    
            // ConfigResolver and CachingConfigResolver preferences must be skipped
        // so the explicit factory from config-scope/module.php wins
        expect($registry->getPreference(ConfigResolver::class))->toBeNull();
            expect($registry->getPreference(CachingConfigResolver::class))->toBeNull();
    
            // Build the container and add null storage bindings so the resolution chain works
        $container = $bootstrapper->build($manifests, $config, $connection);
            $container->instance(ConfigStorageInterface::class, new NullConfigStorage());
            $container->instance(ScopedConfigStorageInterface::class, new NullScopedConfigStorage());
    
            // Boot populates ConfigRegistry singleton (needed to resolve ConfigResolver)
        $bootstrapper->boot($container, $manifests);
    
            $resolver = $container->get(ConfigResolver::class);
            // The explicit factory from config-scope/module.php returns ScopedCachingConfigResolver
        expect($resolver)->toBeInstanceOf(ScopedCachingConfigResolver::class);
        } finally {
            putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
        }
    }
);
