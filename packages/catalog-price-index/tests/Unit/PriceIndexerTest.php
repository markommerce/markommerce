<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogPriceIndex\PriceIndexer;
use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class IndexerFakeQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<int> */
    private array $filteredIds = [];

    /**
     * @param array<int, Product> $products
     */
    public function __construct(
        private readonly array $products,
        private readonly CountingProductRepository $owner,
    ) {
        // Skipping parent constructor — no real DB needed in unit tests.
    }

    public function selectRaw(
        string $expression,
        array $bindings = [],
    ): static {
        return $this;
    }

    public function whereIn(
        string $column,
        array $values,
    ): static {
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
        $this->owner->loadCallCount++;
        $ids      = $this->filteredIds;
        $filtered = array_values(array_filter(
            $this->products,
            fn (Product $p): bool => in_array($p->id, $ids, strict: true),
        ));

        return new EntityCollection($filtered);
    }
}

class CountingProductRepository implements ProductRepositoryInterface
{
    public int $loadCallCount = 0;

    /** @param array<int, Product> $products */
    public function __construct(private readonly array $products) {}

    public function query(): IndexerFakeQueryBuilder
    {
        return new IndexerFakeQueryBuilder($this->products, $this);
    }

    public function find(int|string $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findOrFail(int|string $id): Product
    {
        return $this->products[$id] ?? throw RepositoryException::entityNotFound(Product::class, $id);
    }

    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->products));
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

class CountingIndexRepository implements ProductPriceIndexRepositoryInterface
{
    public int $upsertCallCount  = 0;

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

    /**
     * @param list<int> $productIds
     * @return array<int, ProductPriceIndexEntry> keyed by productId
     */
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

class StubMarketsProvider implements IndexedMarketsProviderInterface
{
    /** @param list<string> $markets */
    public function __construct(private readonly array $markets) {}

    public function markets(): array
    {
        return $this->markets;
    }
}

class StubBatchPriceIndexResolver implements BatchPriceResolverInterface
{
    public int $resolveCallCount = 0;

    /**
     * @param array<int, string>               $baseAmounts    productId => amount
     * @param array<string, array<int, string>> $marketAmounts  market => (productId => amount)
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
        $this->resolveCallCount++;
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

function indexerMakeProduct(int $id, ?string $priceAmount = '10.00'): Product
{
    $p              = new Product();
    $p->id          = $id;
    $p->priceAmount = $priceAmount;

    return $p;
}

function indexerBuildIndexer(
    CountingProductRepository $productRepo,
    BatchPriceResolverInterface $batchResolver,
    CountingIndexRepository $indexRepo,
    IndexedMarketsProviderInterface $marketsProvider,
    ScopeContext $scopeContext,
): PriceIndexer {
    $runner = new ScopePassRunner($scopeContext);

    return new PriceIndexer($productRepo, $batchResolver, $indexRepo, $marketsProvider, $runner);
}

function indexerMakeScopeContext(): ScopeContext
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

// ─── Tests ────────────────────────────────────────────────────────────────────

it('writes one index row per product with the base amount and currency', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $p1           = indexerMakeProduct(1, '10.00');
    $p2           = indexerMakeProduct(2, '20.00');
    $productRepo  = new CountingProductRepository([1 => $p1, 2 => $p2]);
    $indexRepo    = new CountingIndexRepository();
    $resolver     = new StubBatchPriceIndexResolver($scopeContext, [1 => '10.00', 2 => '20.00']);
    $indexer      = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $count = $indexer->reindexProducts([1, 2]);

    expect($count)->toBe(2);
    expect($indexRepo->lastUpsertEntries)->toHaveCount(2);

    $byId = [];
    foreach ($indexRepo->lastUpsertEntries as $e) {
        $byId[$e->productId] = $e;
    }

    expect($byId[1]->amount)->toBe('10.00');
    expect($byId[1]->currencyCode)->toBe('USD');
    expect($byId[2]->amount)->toBe('20.00');
});

it('skips products that have no resolvable price', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $p1           = indexerMakeProduct(1, '10.00');
    $p2           = indexerMakeProduct(2, null);
    $productRepo  = new CountingProductRepository([1 => $p1, 2 => $p2]);
    $indexRepo    = new CountingIndexRepository();
    // Resolver only returns money for product 1
    $resolver = new StubBatchPriceIndexResolver($scopeContext, [1 => '10.00']);
    $indexer  = indexerBuildIndexer($productRepo, $resolver, $indexRepo, new StubMarketsProvider([]), $scopeContext);

    $count = $indexer->reindexProducts([1, 2]);

    expect($count)->toBe(1);
    expect($indexRepo->lastUpsertEntries)->toHaveCount(1);
    expect($indexRepo->lastUpsertEntries[0]->productId)->toBe(1);
});

it('writes per market amounts into the scopes json for each indexed market', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $p1           = indexerMakeProduct(1, '10.00');
    $productRepo  = new CountingProductRepository([1 => $p1]);
    $indexRepo    = new CountingIndexRepository();
    $resolver     = new StubBatchPriceIndexResolver(
        $scopeContext,
        baseAmounts: [1 => '10.00'],
        marketAmounts: ['us' => [1 => '9.00'], 'eu' => [1 => '8.50']],
    );
    $indexer = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider(['us', 'eu']),
        $scopeContext,
    );

    $indexer->reindexProducts([1]);

    $entry = $indexRepo->lastUpsertEntries[0];
    expect($entry->override('market:us', 'amount'))->toBe('9.00');
    expect($entry->override('market:eu', 'amount'))->toBe('8.50');
});

it('writes only the base amount when no markets are indexed', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $p1           = indexerMakeProduct(1, '10.00');
    $productRepo  = new CountingProductRepository([1 => $p1]);
    $indexRepo    = new CountingIndexRepository();
    $resolver     = new StubBatchPriceIndexResolver($scopeContext, [1 => '10.00']);
    $indexer      = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $indexer->reindexProducts([1]);

    $entry = $indexRepo->lastUpsertEntries[0];
    expect($entry->amount)->toBe('10.00');
    expect($entry->scopes)->toBeNull();
});

it('reindexes a single product by id', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $p42          = indexerMakeProduct(42, '5.00');
    $productRepo  = new CountingProductRepository([42 => $p42]);
    $indexRepo    = new CountingIndexRepository();
    $resolver     = new StubBatchPriceIndexResolver($scopeContext, [42 => '5.00']);
    $indexer      = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $count = $indexer->reindexProduct(42);

    expect($count)->toBe(1);
    expect($productRepo->loadCallCount)->toBe(1);
    expect($indexRepo->lastUpsertEntries[0]->productId)->toBe(42);
});

it('rebuilds the whole index in chunks after truncating', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $products     = [];
    $amounts      = [];
    for ($i = 1; $i <= 5; $i++) {
        $products[$i] = indexerMakeProduct($i, (string) ($i * 10) . '.00');
        $amounts[$i]  = (string) ($i * 10) . '.00';
    }
    $productRepo = new CountingProductRepository($products);
    $indexRepo   = new CountingIndexRepository();
    $resolver    = new StubBatchPriceIndexResolver($scopeContext, $amounts);
    $indexer     = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $indexer->rebuildAll(chunkSize: 2);

    // 5 products / chunk of 2 = 3 chunks
    expect($indexRepo->truncateCallCount)->toBe(1);
    // Each chunk loads products once → 3 chunks = 3 load calls
    expect($productRepo->loadCallCount)->toBe(3);
});

it('returns the count of index rows written from rebuildAll', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $products     = [];
    $amounts      = [];
    for ($i = 1; $i <= 4; $i++) {
        $products[$i] = indexerMakeProduct($i, '1.00');
        $amounts[$i]  = '1.00';
    }
    $productRepo = new CountingProductRepository($products);
    $indexRepo   = new CountingIndexRepository();
    $resolver    = new StubBatchPriceIndexResolver($scopeContext, $amounts);
    $indexer     = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $total = $indexer->rebuildAll(chunkSize: 2);

    expect($total)->toBe(4);
});

it('loads each chunk of products with a single query regardless of chunk size', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $products     = [];
    $amounts      = [];
    for ($i = 1; $i <= 100; $i++) {
        $products[$i] = indexerMakeProduct($i, '1.00');
        $amounts[$i]  = '1.00';
    }
    $productRepo = new CountingProductRepository($products);
    $indexRepo   = new CountingIndexRepository();
    $resolver    = new StubBatchPriceIndexResolver($scopeContext, $amounts);
    $indexer     = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $productRepo->loadCallCount = 0;
    $indexer->reindexProducts(range(1, 100));
    expect($productRepo->loadCallCount)->toBe(1);

    $productRepo->loadCallCount = 0;
    $indexer->reindexProducts(range(1, 10));
    expect($productRepo->loadCallCount)->toBe(1);

    $productRepo->loadCallCount = 0;
    $indexer->reindexProducts([1]);
    expect($productRepo->loadCallCount)->toBe(1);
});

it('runs the pricing pipeline once per market pass not once per product', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $products     = [];
    $amounts      = [];
    for ($i = 1; $i <= 5; $i++) {
        $products[$i] = indexerMakeProduct($i, '1.00');
        $amounts[$i]  = '1.00';
    }
    $productRepo = new CountingProductRepository($products);
    $indexRepo   = new CountingIndexRepository();
    $resolver    = new StubBatchPriceIndexResolver($scopeContext, $amounts, ['us' => $amounts, 'eu' => $amounts]);
    $indexer     = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider(['us', 'eu']),
        $scopeContext,
    );

    $indexer->reindexProducts(range(1, 5));

    // 1 base pass + 2 market passes = 3 total, NOT 5 × 3 = 15
    expect($resolver->resolveCallCount)->toBe(3);
});

it('restores the ambient market scope after indexing', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $scopeContext->in('market', 'us');

    $p1          = indexerMakeProduct(1, '10.00');
    $productRepo = new CountingProductRepository([1 => $p1]);
    $indexRepo   = new CountingIndexRepository();
    $resolver    = new StubBatchPriceIndexResolver($scopeContext, [1 => '10.00'], ['us' => [1 => '9.00']]);
    $indexer     = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider(['us']),
        $scopeContext,
    );

    $indexer->reindexProducts([1]);

    expect($scopeContext->get('market'))->toBe('us');
});

it('upserts each chunk in a single bulk statement', function (): void {
    $scopeContext = indexerMakeScopeContext();
    $products     = [];
    $amounts      = [];
    for ($i = 1; $i <= 3; $i++) {
        $products[$i] = indexerMakeProduct($i, '1.00');
        $amounts[$i]  = '1.00';
    }
    $productRepo = new CountingProductRepository($products);
    $indexRepo   = new CountingIndexRepository();
    $resolver    = new StubBatchPriceIndexResolver($scopeContext, $amounts);
    $indexer     = indexerBuildIndexer(
        $productRepo,
        $resolver,
        $indexRepo,
        new StubMarketsProvider([]),
        $scopeContext,
    );

    $indexer->reindexProducts([1, 2, 3]);

    expect($indexRepo->upsertCallCount)->toBe(1);
});
