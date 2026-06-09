<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\CatalogScope\Entity\CategoryScopedOverrides;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container pre-wired with the scope module's singletons/bindings
 * and a config that includes the locale axis with de and fr scopes.
 */
function buildTier2Container(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'locale' => [
                    'default' => 'default',
                    'scopes' => [
                        'default' => [],
                        'de' => [],
                        'fr' => [],
                    ],
                ],
            ],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

/**
 * Build a ModuleManifest for the scope module (no boot — boot is run via closure in module.php).
 */
function tier2ScopeManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 3) . '/scope/module.php';

    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
        boot: $module['boot'],
    );
}

/**
 * Build a ModuleManifest for the locale module (no boot — it only contributes config).
 */
function tier2LocaleManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/locale',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the catalog module (no boot).
 */
function tier2CatalogManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the catalog-scope module (no boot — it's a structural package).
 */
function tier2CatalogScopeManifest(): ModuleManifest
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
 * Build a ModuleManifest for the catalog-locale bridge using its real module.php.
 */
function tier2CatalogLocaleManifest(): ModuleManifest
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
 * Boot all 5 modules in dependency order.
 *
 * @param ModuleManifest[] $ordered
 */
function runTier2BootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

/**
 * Resolve all 5 manifests via DependencyResolver and run boot.
 */
function bootTier2(Container $container): void
{
    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        tier2ScopeManifest(),
        tier2LocaleManifest(),
        tier2CatalogManifest(),
        tier2CatalogScopeManifest(),
        tier2CatalogLocaleManifest(),
    ]);

    runTier2BootLoop($ordered, $container);
}

/**
 * Create a round-trip in-memory connection for Product.
 */
function makeTier2ProductConnection(): ConnectionInterface
{
    $insertedScopes = null;
    $lastId = 0;

    return new class ($insertedScopes, $lastId) implements ConnectionInterface
    {
        public function __construct(
            private mixed &$insertedScopes,
            private int &$lastId,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array
        {
            if (str_contains($sql, 'WHERE id = ?')) {
                return [[
                    'id' => $this->lastId,
                    'sku' => 'SKU-001',
                    'name' => 'Shirt',
                    'description' => null,
                    'scopes' => $this->insertedScopes,
                ]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int
        {
            if (str_starts_with($sql, 'INSERT')) {
                $this->lastId++;
                $this->insertedScopes = end($bindings);
            }

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastId;
        }
    };
}

/**
 * Create a round-trip in-memory connection for Category.
 */
function makeTier2CategoryConnection(): ConnectionInterface
{
    $insertedScopes = null;
    $lastId = 0;

    return new class ($insertedScopes, $lastId) implements ConnectionInterface
    {
        public function __construct(
            private mixed &$insertedScopes,
            private int &$lastId,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array
        {
            if (str_contains($sql, 'WHERE id = ?')) {
                return [[
                    'id' => $this->lastId,
                    'name' => 'Clothing',
                    'description' => null,
                    'scopes' => $this->insertedScopes,
                ]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int
        {
            if (str_starts_with($sql, 'INSERT')) {
                $this->lastId++;
                $this->insertedScopes = end($bindings);
            }

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastId;
        }
    };
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'registers the locale axis in ScopeRegistryInterface after booting all of scope + locale + catalog + catalog-scope + catalog-locale',
    function (): void {
        DefaultScopeGuard::reset();
    
        $container = buildTier2Container();
        bootTier2($container);
    
        $registry = $container->get(ScopeRegistryInterface::class);
    
        expect($registry->hasAxis('locale'))->toBeTrue()
            ->and($registry->listAxes())->toContain('locale');
    }
);

it(
    'exposes Product.name as locale-scoped via ScopedFieldRegistry after the catalog-locale bridge boot runs',
    function (): void {
        DefaultScopeGuard::reset();
    
        $container = buildTier2Container();
        bootTier2($container);
    
        $registry = $container->get(ScopedFieldRegistry::class);
    
        expect($registry->axesForProperty(Product::class, 'name'))->toBe(['locale']);
    }
);

it('exposes Product.description, Category.name, Category.description as locale-scoped after boot', function (): void {
    DefaultScopeGuard::reset();

    $container = buildTier2Container();
    bootTier2($container);

    $registry = $container->get(ScopedFieldRegistry::class);

    expect($registry->axesForProperty(Product::class, 'description'))->toBe(['locale'])
        ->and($registry->axesForProperty(Category::class, 'name'))->toBe(['locale'])
        ->and($registry->axesForProperty(Category::class, 'description'))->toBe(['locale']);
});

it(
    'persists a Product with an attached ProductScopedOverrides companion and re-fetches both rows through ProductRepository::find',
    function (): void {
        DefaultScopeGuard::reset();
    
        $container = buildTier2Container();
        bootTier2($container);
    
        $connection = makeTier2ProductConnection();
        $metadataFactory = new EntityMetadataFactory();
        $metadataFactory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
        $hydrator = new EntityHydrator($metadataFactory);
        $productRepo = new ProductRepository($connection, $metadataFactory, $hydrator);
    
        $product = new Product();
        $product->sku = 'SKU-001';
        $product->name = 'Shirt';
    
        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('locale:de', 'name', 'Hemd');
        $product->attachCompanion($overrides);
    
        $productRepo->save($product);
    
        /** @var Product $found */
        $found = $productRepo->find($product->id);
    
        /** @var ProductScopedOverrides $foundOverrides */
        $foundOverrides = $found->companion(ProductScopedOverrides::class);
    
        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('Shirt')
            ->and($foundOverrides)->not->toBeNull()
            ->and($foundOverrides->override('locale:de', 'name'))->toBe('Hemd');
    }
);

it('returns the raw Product.name when the active locale context matches the axis default', function (): void {
    DefaultScopeGuard::reset();

    $container = buildTier2Container();
    bootTier2($container);

    $scopeResolver = $container->get(ScopeResolver::class);
    $scopeContext = $container->get(ScopeContext::class);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('locale:de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    $scopeContext->clearAll();
    $scopeContext->in('locale', 'default');

    $result = $scopeResolver->resolved($product, 'name');

    expect($result)->toBe('Shirt');
});

it('returns the German override Product.name when the active locale context is de', function (): void {
    DefaultScopeGuard::reset();

    $container = buildTier2Container();
    bootTier2($container);

    $scopeResolver = $container->get(ScopeResolver::class);
    $scopeContext = $container->get(ScopeContext::class);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('locale:de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    $scopeContext->clearAll();
    $scopeContext->in('locale', 'de');

    $result = $scopeResolver->resolved($product, 'name');

    expect($result)->toBe('Hemd');
});

it(
    'falls back to the raw Product.name when the active locale context is an axis-valid scope with no override (e.g., fr)',
    function (): void {
        DefaultScopeGuard::reset();
    
        $container = buildTier2Container();
        bootTier2($container);
    
        $scopeResolver = $container->get(ScopeResolver::class);
        $scopeContext = $container->get(ScopeContext::class);
    
        $product = new Product();
        $product->name = 'Shirt';
    
        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('locale:de', 'name', 'Hemd');
        $product->attachCompanion($overrides);
    
        $scopeContext->clearAll();
        $scopeContext->in('locale', 'fr');
    
        $result = $scopeResolver->resolved($product, 'name');
    
        expect($result)->toBe('Shirt');
    }
);

it(
    'propagates the same resolution semantics for Category.name through CategoryRepository + CategoryScopedOverrides',
    function (): void {
        DefaultScopeGuard::reset();
    
        $container = buildTier2Container();
        bootTier2($container);
    
        // Persistence round-trip via CategoryRepository
    $connection = makeTier2CategoryConnection();
        $metadataFactory = new EntityMetadataFactory();
        $metadataFactory->linkExtenders(Category::class, [CategoryScopedOverrides::class]);
        $hydrator = new EntityHydrator($metadataFactory);
        $categoryRepo = new CategoryRepository($connection, $metadataFactory, $hydrator);
    
        $category = new Category();
        $category->name = 'Clothing';
    
        $overrides = new CategoryScopedOverrides();
        $overrides->setOverride('locale:de', 'name', 'Kleidung');
        $category->attachCompanion($overrides);
    
        $categoryRepo->save($category);
    
        /** @var Category $found */
        $found = $categoryRepo->find($category->id);
    
        /** @var CategoryScopedOverrides $foundOverrides */
        $foundOverrides = $found->companion(CategoryScopedOverrides::class);
    
        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('Clothing')
            ->and($foundOverrides)->not->toBeNull()
            ->and($foundOverrides->override('locale:de', 'name'))->toBe('Kleidung');
    
        // Resolution via ScopeResolver
    $scopeResolver = $container->get(ScopeResolver::class);
        $scopeContext = $container->get(ScopeContext::class);
    
        // Default scope → raw value
    $scopeContext->clearAll();
        $scopeContext->in('locale', 'default');
        expect($scopeResolver->resolved($category, 'name'))->toBe('Clothing');
    
        // de scope → override
    $scopeContext->clearAll();
        $scopeContext->in('locale', 'de');
        expect($scopeResolver->resolved($category, 'name'))->toBe('Kleidung');
    
        // fr scope (no override) → raw value
    $scopeContext->clearAll();
        $scopeContext->in('locale', 'fr');
        expect($scopeResolver->resolved($category, 'name'))->toBe('Clothing');
    }
);
