<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;
use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogPriceIndex\PriceIndexer;
use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\Registry\IndexerRegistry;
use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class RefactorFakeQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<int> */
    private array $filteredIds = [];

    /**
     * @param array<int, Product> $products
     */
    public function __construct(
        private readonly array $products,
        private readonly RefactorCountingProductRepo $owner,
    ) {}

    public function selectRaw(
        string $expression,
        array $bindings = [],
    ): static
    {
        return $this;
    }

    public function whereIn(
        string $column,
        array $values,
    ): static
    {
        $this->filteredIds = $values;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return array_map(fn (int $id): array => ['id' => (string) $id], array_keys($this->products));
    }

    public function getEntities(): EntityCollection
    {
        $ids      = $this->filteredIds;
        $filtered = array_values(array_filter(
            $this->products,
            fn (Product $p): bool => in_array($p->id, $ids, strict: true),
        ));

        return new EntityCollection($filtered);
    }
}

class RefactorCountingProductRepo implements ProductRepositoryInterface
{
    /** @param array<int, Product> $products */
    public function __construct(private readonly array $products) {}

    public function query(): RefactorFakeQueryBuilder
    {
        return new RefactorFakeQueryBuilder($this->products, $this);
    }

    public function find(int|string $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findOrFail(int|string $id): Product
    {
        return $this->products[$id] ?? throw new RuntimeException();
    }

    public function findAll(): EntityCollection
    {
        return new EntityCollection([]);
    }

    /** @param array<string, mixed> $criteria */
    public function findBy(array $criteria): EntityCollection
    {
        return new EntityCollection([]);
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?Product
    {
        return null;
    }

    /** @param array<string, mixed> $criteria */
    public function existsBy(array $criteria): bool
    {
        return false;
    }

    public function save(Entity $entity): void {}

    public function delete(Entity $entity): void {}

    /** @param array<Entity> $entities */
    public function insertBatch(array $entities): void {}

    public function findBySku(string $sku): ?Product
    {
        return null;
    }
}

class RefactorCountingIndexRepo implements ProductPriceIndexRepositoryInterface
{
    public int $upsertCallCount = 0;

    public int $truncateCallCount = 0;

    /** @var list<ProductPriceIndexEntry> */
    public array $lastUpsertEntries = [];

    /** @var list<ProductPriceIndexEntry> */
    private array $stored = [];

    public function upsertMany(array $entries): void
    {
        $this->upsertCallCount++;
        $this->lastUpsertEntries = $entries;
        foreach ($entries as $entry) {
            $this->stored[] = $entry;
        }
    }

    public function findByProductId(int $productId): ?ProductPriceIndexEntry
    {
        return array_find($this->stored, fn (ProductPriceIndexEntry $e): bool => $e->productId === $productId);
    }

    /** @param list<int> $productIds */
    public function findByProductIds(array $productIds): array
    {
        $result = [];
        foreach ($this->stored as $entry) {
            if (in_array($entry->productId, $productIds, true)) {
                $result[$entry->productId] = $entry;
            }
        }

        return $result;
    }

    public function truncate(): void
    {
        $this->truncateCallCount++;
        $this->stored = [];
    }
}

class RefactorStubMarketsProvider implements IndexedMarketsProviderInterface
{
    /** @param list<string> $markets */
    public function __construct(private readonly array $markets) {}

    public function markets(): array
    {
        return $this->markets;
    }
}

class RefactorStubBatchPriceResolver implements BatchPriceResolverInterface
{
    /**
     * @param array<int, string>                $baseAmounts
     * @param array<string, array<int, string>> $marketAmounts
     */
    public function __construct(
        private readonly ScopeContext $scopeContext,
        private readonly array $baseAmounts,
        private readonly array $marketAmounts = [],
    ) {}

    /**
     * @param array<array-key, Product> $products
     * @return array<array-key, Money>
     */
    public function resolve(array $products): array
    {
        $market   = $this->scopeContext->get('market');
        $currency = new Currency('USD', 2, '$', 'US Dollar');
        $result   = [];
        foreach ($products as $key => $product) {
            $pid = $product->id ?? 0;
            if ($market !== null && isset($this->marketAmounts[$market][$pid])) {
                $result[$key] = Money::of($this->marketAmounts[$market][$pid], $currency);
            } elseif (isset($this->baseAmounts[$pid])) {
                $result[$key] = Money::of($this->baseAmounts[$pid], $currency);
            }
        }

        return $result;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function refactorMakeProduct(int $id): Product
{
    $p     = new Product();
    $p->id = $id;

    return $p;
}

function refactorBuildScopeContext(): ScopeContext
{
    DefaultScopeGuard::reset();
    $config = new ConfigRepository(
        ['scope' => ['axes' => ['market' => ['default' => 'default', 'scopes' => ['default' => [], 'us' => [], 'eu' => []]]]]],
    );
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
    $container->call($scopeModule['boot']);

    return $container->get(ScopeContext::class);
}

function refactorBuildIndexer(
    RefactorCountingProductRepo $productRepo,
    BatchPriceResolverInterface $batchResolver,
    RefactorCountingIndexRepo $indexRepo,
    IndexedMarketsProviderInterface $marketsProvider,
    ScopeContext $scopeContext,
): PriceIndexer {
    $runner = new ScopePassRunner($scopeContext);

    return new PriceIndexer($productRepo, $batchResolver, $indexRepo, $marketsProvider, $runner);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it still satisfies PriceIndexerInterface with reindexProducts reindexProduct and rebuildAll', function (): void {
    expect(PriceIndexer::class)->toImplement(PriceIndexerInterface::class);
    expect(PriceIndexer::class)->toImplement(IndexerInterface::class);
});

it('it produces the same base amount and per-market override entries after refactor', function (): void {
    $scopeContext = refactorBuildScopeContext();
    $p1           = refactorMakeProduct(1);
    $productRepo  = new RefactorCountingProductRepo([1 => $p1]);
    $indexRepo    = new RefactorCountingIndexRepo();
    $resolver     = new RefactorStubBatchPriceResolver(
        $scopeContext,
        baseAmounts: [1 => '10.00'],
        marketAmounts: ['us' => [1 => '9.00'], 'eu' => [1 => '8.50']],
    );
    $indexer = refactorBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new RefactorStubMarketsProvider(['us', 'eu']),
        $scopeContext,
    );

    $indexer->reindexProducts([1]);

    expect($indexRepo->lastUpsertEntries)->toHaveCount(1);
    $entry = $indexRepo->lastUpsertEntries[0];
    expect($entry->amount)->toBe('10.00');
    expect($entry->currencyCode)->toBe('USD');
    expect($entry->override('market:us', 'amount'))->toBe('9.00');
    expect($entry->override('market:eu', 'amount'))->toBe('8.50');
});

it('it still performs a single bulk write per chunk', function (): void {
    $scopeContext = refactorBuildScopeContext();
    $products     = [];
    $amounts      = [];
    for ($i = 1; $i <= 3; $i++) {
        $products[$i] = refactorMakeProduct($i);
        $amounts[$i]  = '1.00';
    }
    $productRepo = new RefactorCountingProductRepo($products);
    $indexRepo   = new RefactorCountingIndexRepo();
    $resolver    = new RefactorStubBatchPriceResolver($scopeContext, $amounts);
    $indexer     = refactorBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new RefactorStubMarketsProvider([]),
        $scopeContext,
    );

    $indexer->reindexProducts([1, 2, 3]);

    expect($indexRepo->upsertCallCount)->toBe(1);
});

it('it registers the price indexer under the name price in the indexer registry', function (): void {
    // The registration happens lazily via the PriceIndexerInterface factory binding.
    // Verify: resolving PriceIndexerInterface from the container registers it as 'price'.
    $module   = require dirname(__DIR__, 2) . '/module.php';
    $registry = new IndexerRegistry();

    $scopeContext = refactorBuildScopeContext();
    $runner       = new ScopePassRunner($scopeContext);

    // Build a minimal container with the module bindings and all fakes.
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(IndexerRegistry::class, $registry);
    $container->instance(ProductPriceIndexRepositoryInterface::class, new RefactorCountingIndexRepo());
    $container->instance(ProductRepositoryInterface::class, new RefactorCountingProductRepo([]));
    $container->instance(BatchPriceResolverInterface::class, new RefactorStubBatchPriceResolver($scopeContext, []));
    $container->instance(IndexedMarketsProviderInterface::class, new RefactorStubMarketsProvider([]));
    $container->instance(ScopeContext::class, $scopeContext);
    $container->instance(ScopePassRunner::class, $runner);

    // Register the factory binding from module.php
    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Resolving PriceIndexerInterface triggers the factory which registers with IndexerRegistry.
    $container->get(PriceIndexerInterface::class);

    expect($registry->names())->toContain('price');
    expect($registry->get('price'))->toBeInstanceOf(IndexerInterface::class);
});

it('it still restores the ambient market scope after reindex', function (): void {
    $scopeContext = refactorBuildScopeContext();
    $scopeContext->in('market', 'us');

    $p1          = refactorMakeProduct(1);
    $productRepo = new RefactorCountingProductRepo([1 => $p1]);
    $indexRepo   = new RefactorCountingIndexRepo();
    $resolver    = new RefactorStubBatchPriceResolver(
        $scopeContext,
        baseAmounts: [1 => '10.00'],
        marketAmounts: ['us' => [1 => '9.00']],
    );
    $indexer = refactorBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new RefactorStubMarketsProvider(['us']),
        $scopeContext,
    );

    $indexer->reindexProducts([1]);

    expect($scopeContext->get('market'))->toBe('us');
});
