<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Database\Entity\EntityCollection;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\ModuleLoader;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Data\ProductCardData;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Criteria\Strategy\OffsetPage;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function productGridBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-grid-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 3);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $viewConfig = new ViewConfig($config);
    $latteViewConfig = new LatteViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engine = (new LatteEngineFactory($viewConfig, $latteViewConfig))->create();

    // Register the template resolver loader
    $engine->setLoader(new ModuleLoader($templateResolver));

    return $engine;
}

function makeGridScopeContextStub(?string $locale): ScopeContext
{
    $registry = new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw UnknownAxisException::forAxis($name);
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw UnknownAxisException::forAxis($axisName);
        }
    };

    return new class ($locale, $registry) extends ScopeContext
    {
        public function __construct(
            private readonly ?string $activeLocale,
            ScopeRegistryInterface $registry,
        ) {
            parent::__construct($registry);
        }

        public function get(string $axis): ?string
        {
            if ($axis === 'locale') {
                return $this->activeLocale;
            }

            return null;
        }
    };
}

function makeGridMoneyFormatter(?string $locale = null): MoneyFormatter
{
    return new MoneyFormatter(makeGridScopeContextStub($locale));
}

function makeGridNoPricePriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

function productGridMakeAssignmentService(
    FakeProductRepository $productRepository,
    FakeCategoryRepository $categoryRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
): CategoryAssignmentService {
    $positionCodec = new PositionCodec();

    return new CategoryAssignmentService(
        productRepository: $productRepository,
        categoryRepository: $categoryRepository,
        productCategoryAssignmentRepository: $assignmentRepository,
        positionCodec: $positionCodec,
        keysetPaginationStrategy: new KeysetPaginationStrategy($positionCodec),
    );
}

/**
 * @param array<string, mixed> $overrides
 */
function productGridMakeConfigResolver(array $overrides = []): ConfigResolverInterface
{
    $defaults = [
        'defaultPageSize'  => 24,
        'allowedPageSizes' => [12, 24, 48, 96],
        'maxPageSize'      => 96,
        'strategy'         => 'offset',
        'presentation'     => 'numbered',
        'countMode'        => 'exact',
        'maxPageDepth'     => 100,
        'defaultSort'      => 'position',
        'enabledSorts'     => [],
        'viewAllThreshold' => 0,
        'countCacheTtl'    => 0,
    ];

    $values = array_merge($defaults, $overrides);

    return new class ($values) implements ConfigResolverInterface
    {
        /** @param array<string, mixed> $values */
        public function __construct(private readonly array $values) {}

        public function resolved(string $configClass, string $field): mixed
        {
            return $this->values[$field] ?? null;
        }
    };
}

function productGridMakeSortRegistry(): CategorySortOrderRegistry
{
    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);
    $registry->register(new ColumnSortOrder(
        key: 'name',
        label: 'Name',
        column: 'catalog_product.name',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 10);

    return $registry;
}

/**
 * @param array<string, mixed> $overrides
 */
function productGridMakePaginationOptionsResolver(array $overrides = []): PaginationOptionsResolver
{
    return new PaginationOptionsResolver(productGridMakeConfigResolver($overrides), productGridMakeSortRegistry());
}

/**
 * Build a fake OffsetPage for unit tests.
 *
 * @param list<Product> $products
 */
function productGridMakeFakeOffsetPage(
    array $products,
    int $currentPage = 1,
    int $totalPages = 1,
    ?string $nextPosition = null,
    ?string $previousPosition = null,
): OffsetPage {
    return new OffsetPage(
        items: new EntityCollection($products),
        size: 24,
        nextPosition: $nextPosition,
        previousPosition: $previousPosition,
        currentPage: $currentPage,
        totalPages: $totalPages,
        totalItems: $totalPages * 24,
        positionCodec: new PositionCodec(),
    );
}

/**
 * Build a fake CategoryAssignmentService that returns a controlled OffsetPage.
 */
function productGridMakeFakeService(
    OffsetPage $page,
): CategoryAssignmentService {
    $positionCodec = new PositionCodec();
    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    return new class (
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $positionCodec,
        new KeysetPaginationStrategy($positionCodec),
        $page,
    ) extends CategoryAssignmentService
    {
        public function __construct(
            ProductRepositoryInterface $productRepository,
            CategoryRepositoryInterface $categoryRepository,
            ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
            PositionCodec $positionCodec,
            KeysetPaginationStrategy $keysetPaginationStrategy,
            private readonly OffsetPage $fakePage,
        ) {
            parent::__construct(
                $productRepository,
                $categoryRepository,
                $assignmentRepository,
                $positionCodec,
                $keysetPaginationStrategy,
            );
        }

        public function paginatedProductsInCategory(int $categoryId, ResolvedPaginationOptions $options): Page
        {
            return $this->fakePage;
        }
    };
}

class FakeProductPriceIndexRepository implements ProductPriceIndexRepositoryInterface
{
    /** @var array<int, ProductPriceIndexEntry> */
    public array $entries = [];

    public int $findByProductIdsCallCount = 0;

    /** @param list<ProductPriceIndexEntry> $entries */
    public function upsertMany(array $entries): void
    {
        foreach ($entries as $entry) {
            $this->entries[$entry->productId] = $entry;
        }
    }

    public function findByProductId(int $productId): ?ProductPriceIndexEntry
    {
        return $this->entries[$productId] ?? null;
    }

    /**
     * @param list<int> $productIds
     * @return array<int, ProductPriceIndexEntry> keyed by productId
     */
    public function findByProductIds(array $productIds): array
    {
        $this->findByProductIdsCallCount++;

        return array_filter(
            $this->entries,
            fn (ProductPriceIndexEntry $entry) => in_array($entry->productId, $productIds, true),
        );
    }

    public function truncate(): void
    {
        $this->entries = [];
    }
}

function makeGridCurrencyResolver(string $currencyCode = 'USD'): CurrencyResolver
{
    $currency = new Currency(code: $currencyCode, scale: 2, symbol: '$', name: 'US Dollar');

    return new class ($currency) extends CurrencyResolver
    {
        public function __construct(private readonly Currency $currency)
        {
            // Skip parent constructor — no deps needed in test
        }

        public function base(): Currency
        {
            return $this->currency;
        }
    };
}

function productGridBuildComponent(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    ?PriceResolverInterface $priceResolver = null,
    ?MoneyFormatter $moneyFormatter = null,
    ?OffsetPage $fakePage = null,
    ?ProductPriceIndexRepositoryInterface $priceIndexRepository = null,
    ?CurrencyResolver $currencyResolver = null,
): ProductGridComponent {
    if ($fakePage !== null) {
        $service = productGridMakeFakeService($fakePage);
    } else {
        // Build a fake page from data already in the repositories
        $products = array_values($productRepository->products);
        $page = productGridMakeFakeOffsetPage($products);
        $service = productGridMakeFakeService($page);
    }

    return new ProductGridComponent(
        $service,
        productGridMakePaginationOptionsResolver(),
        $priceResolver ?? makeGridNoPricePriceResolver(),
        $moneyFormatter ?? makeGridMoneyFormatter(),
        $priceIndexRepository ?? new FakeProductPriceIndexRepository(),
        $currencyResolver ?? makeGridCurrencyResolver(),
        productGridMakeSortRegistry(),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'takes only CategoryAssignmentService in its constructor (no ScopeResolver, no direct repository)',
    function (): void {
        $reflection = new ReflectionClass(ProductGridComponent::class);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $params = $constructor->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        $paramTypes = array_map(fn ($p) => $p->getType()?->getName(), $params);

        expect($paramNames)->toContain('categoryAssignmentService');
        expect($paramNames)->not->toContain('categoryRepository');
        expect($paramNames)->not->toContain('scopeResolver');

        expect($paramTypes)->toContain(CategoryAssignmentService::class);
        expect($paramTypes)->toContain(PriceResolverInterface::class);
        expect($paramTypes)->toContain(MoneyFormatter::class);
    },
);

it('has no Markommerce\\Scope imports in the ProductGridComponent class file', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Component/ProductGridComponent.php');

    expect($source)->not->toContain('Markommerce\\Scope');
});

it('returns a ProductGridData with the raw product name in resolvedNames keyed by product id', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'SHOE-001';
    $product->name = 'Running Shoes';
    $productRepository->save($product);

    $fakePage = productGridMakeFakeOffsetPage([$product]);

    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    expect($data->resolvedNames[$product->id])->toBe('Running Shoes');
});

it(
    'returns a ProductGridData with the raw product description in resolvedDescs keyed by product id',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();

        $category = new Category();
        $category->name = 'Shoes';
        $categoryRepository->save($category);

        $product = new Product();
        $product->sku = 'SHOE-001';
        $product->name = 'Running Shoes';
        $product->description = 'Great running shoes';
        $productRepository->save($product);

        $fakePage = productGridMakeFakeOffsetPage([$product]);

        $component = productGridBuildComponent(
            $categoryRepository,
            $productRepository,
            $assignmentRepository,
            fakePage: $fakePage,
        );
        $data = $component->data($category, 1, 0, '');

        expect($data->resolvedDescs[$product->id])->toBe('Great running shoes');
    },
);

it('returns an empty products list when the category has no id', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Unsaved Category';
    // intentionally NOT saving — category has no id

    $fakePage = productGridMakeFakeOffsetPage([]);
    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    expect($data->products)->toBeEmpty();
    expect($data->resolvedNames)->toBeEmpty();
    expect($data->resolvedDescs)->toBeEmpty();
});

it('skips products with null id when building the resolved maps', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Tech';
    $categoryRepository->save($category);

    $productWithId = new Product();
    $productWithId->sku = 'TECH-001';
    $productWithId->name = 'Gadget';
    $productRepository->save($productWithId);

    $nullIdProduct = new Product();
    $nullIdProduct->sku = 'NULL-001';
    $nullIdProduct->name = 'Ghost Product';
    // id is not set — remains null

    $fakePage = productGridMakeFakeOffsetPage([$productWithId, $nullIdProduct]);
    $positionCodec = new PositionCodec();

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );
    $data = $component->data($category, 1, 0, '');

    // Only the product with a real id appears in the maps
    expect($data->resolvedNames)->toHaveKey($productWithId->id);
    expect($data->resolvedNames)->toHaveCount(1);
    expect($data->resolvedDescs)->toHaveKey($productWithId->id);
    expect($data->resolvedDescs)->toHaveCount(1);
    expect($data->products)->toHaveCount(2); // both products are in the list, but only one in maps
});

it('returns a typed ProductGridData DTO from data() for an existing category', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'SHOE-001';
    $product->name = 'Running Shoes';
    $productRepository->save($product);

    $fakePage = productGridMakeFakeOffsetPage([$product]);

    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toHaveCount(1);
    expect($data->resolvedNames[$product->id])->toBe('Running Shoes');
});

it('returns an empty products list from data() when the category has no assigned products', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);
    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toBeEmpty();
    expect($data->resolvedNames)->toBeEmpty();
    expect($data->resolvedDescs)->toBeEmpty();
});

it('renders the category name as the page heading via mk-heading', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Featured Products';
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);
    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('<mk-heading');
    expect($output)->toContain('Featured Products');
});

it('renders products inside an mk-grid element', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'ELEC-001';
    $product->name = 'Laptop';
    $productRepository->save($product);

    $fakePage = productGridMakeFakeOffsetPage([$product]);
    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('<mk-grid');
});

it('renders a product grid container with a products slot placeholder when products exist', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Clothing';
    $categoryRepository->save($category);

    $product1 = new Product();
    $product1->sku = 'SHIRT-001';
    $product1->name = 'Blue T-Shirt';
    $productRepository->save($product1);

    $product2 = new Product();
    $product2->sku = 'PANTS-001';
    $product2->name = 'Black Jeans';
    $productRepository->save($product2);

    $fakePage = productGridMakeFakeOffsetPage([$product1, $product2]);
    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('<mk-grid');
    expect($output)->toContain('{slot products}{/slot}');
});

it('renders the product card template with a placeholder image for the product SKU', function (): void {
    $product = new Product();
    $product->id = 42;
    $product->sku = 'BOOT-001';
    $product->name = 'Hiking Boot';

    $data = new ProductCardData(
        product: $product,
        resolvedName: 'Hiking Boot',
        resolvedDesc: '',
        inStock: true,
        extensions: new ExtensionBag(),
    );

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-card', [
        'product' => $data->product,
        'resolvedName' => $data->resolvedName,
        'resolvedDesc' => $data->resolvedDesc,
        'inStock' => $data->inStock,
        'formattedPrice' => $data->formattedPrice,
        'extensions' => $data->extensions,
    ]);

    expect($output)->toContain('placehold.co');
    expect($output)->toContain('BOOT-001');
});

it('renders a muted empty state when the category has no products', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);
    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );
    $data = $component->data($category, 1, 0, '');

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('No products found');
    expect($output)->toContain('muted');
});

it('exposes formatted prices keyed by product id from the product grid', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $pricedProduct = new Product();
    $pricedProduct->sku = 'SHOE-001';
    $pricedProduct->name = 'Running Shoes';
    $pricedProduct->priceAmount = '49.99';
    $productRepository->save($pricedProduct);

    $unpricedProduct = new Product();
    $unpricedProduct->sku = 'GHOST-001';
    $unpricedProduct->name = 'No Price Item';
    $productRepository->save($unpricedProduct);

    $usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $pricedMoney = Money::of('49.99', $usd);

    $priceResolver = new class ($pricedProduct->id, $pricedMoney) implements PriceResolverInterface
    {
        public function __construct(
            private readonly int $pricedProductId,
            private readonly Money $money,
        ) {}

        public function resolve(PriceContext $context): Money
        {
            if ($context->product->id === $this->pricedProductId) {
                return $this->money;
            }

            throw PriceUnavailableException::forContext($context);
        }
    };

    $fakePage = productGridMakeFakeOffsetPage([$pricedProduct, $unpricedProduct]);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        $priceResolver,
        makeGridMoneyFormatter('en_US'),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );
    $data = $component->data($category, 1, 0, '');

    expect($data->formattedPrices)->toHaveKey($pricedProduct->id)
        ->and($data->formattedPrices[$pricedProduct->id])->toContain('$')
        ->and($data->formattedPrices[$pricedProduct->id])->toContain('49.99')
        ->and($data->formattedPrices)->toHaveKey($unpricedProduct->id)
        ->and($data->formattedPrices[$unpricedProduct->id])->toBeNull();
});

it('renders the price for each card in the product grid', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'ELEC-001';
    $product->name = 'Laptop';
    $product->priceAmount = '999.99';
    $productRepository->save($product);

    $usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $money = Money::of('999.99', $usd);

    $priceResolver = new class ($money) implements PriceResolverInterface
    {
        public function __construct(private readonly Money $money) {}

        public function resolve(PriceContext $context): Money
        {
            return $this->money;
        }
    };

    $fakePage = productGridMakeFakeOffsetPage([$product]);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        $priceResolver,
        makeGridMoneyFormatter('en_US'),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );
    $data = $component->data($category, 1, 0, '');

    expect($data->formattedPrices)->toHaveKey($product->id)
        ->and($data->formattedPrices[$product->id])->toContain('$')
        ->and($data->formattedPrices[$product->id])->toContain('999.99');
});

// ─── New pagination requirements ──────────────────────────────────────────────

it('reads the requested page from the query string defaulting to page 1', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Test';

    $product = new Product();
    $product->id = 1;
    $product->sku = 'P-001';
    $product->name = 'Product One';

    $fakePage = productGridMakeFakeOffsetPage([$product], currentPage: 1, totalPages: 3);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    // Called with page=1 (the layout default) — currentPage should be 1
    $data = $component->data($category, 1, 0, '');

    expect($data->currentPage)->toBe(1);
});

it('fetches only the current page of products', function (): void {
    $category = new Category();
    $category->id = 5;
    $category->name = 'Electronics';

    // Only 2 products on page 2 of 3 total pages
    $product1 = new Product();
    $product1->id = 10;
    $product1->sku = 'P-010';
    $product1->name = 'Laptop';

    $product2 = new Product();
    $product2->id = 11;
    $product2->sku = 'P-011';
    $product2->name = 'Keyboard';

    $fakePage = productGridMakeFakeOffsetPage([$product1, $product2], currentPage: 2, totalPages: 3);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    $data = $component->data($category, 2, 0, '');

    // Only the 2 products on the current page are in the grid data
    expect($data->products)->toHaveCount(2);
    expect($data->currentPage)->toBe(2);
    expect($data->totalPages)->toBe(3);
});

it('exposes the resolved presentation mode on the grid data', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Test';

    $fakePage = productGridMakeFakeOffsetPage([], currentPage: 1, totalPages: 1);

    // Load-more presentation config
    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(['presentation' => 'load_more']),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    $data = $component->data($category, 1, 0, '');

    expect($data->presentation)->toBe(PaginationPresentation::LoadMore);
});

it('exposes crawlable page-link urls preserving size and sort params', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Test';

    $fakePage = productGridMakeFakeOffsetPage([], currentPage: 2, totalPages: 4);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(['allowedPageSizes' => [12, 24, 48, 96]]),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    // size=12 (non-default) and sort='name' should be preserved
    $data = $component->data($category, 2, 12, 'name');

    expect($data->pageLinkUrls)->toHaveCount(4);
    expect($data->pageLinkUrls[0])->toBe('?page=1&size=12&sort=name');
    expect($data->pageLinkUrls[1])->toBe('?page=2&size=12&sort=name');
    expect($data->pageLinkUrls[2])->toBe('?page=3&size=12&sort=name');
    expect($data->pageLinkUrls[3])->toBe('?page=4&size=12&sort=name');
});

it('exposes a next-page url when more products exist', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Test';

    // OffsetPage with a next position (hasNext = true)
    $nextToken = (new PositionCodec())->encode(new OffsetPosition(page: 2));
    $fakePage = productGridMakeFakeOffsetPage([], currentPage: 1, totalPages: 2, nextPosition: $nextToken);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    $data = $component->data($category, 1, 0, '');

    expect($data->hasNext)->toBeTrue();
});

it('exposes current and total pages for the random-access offset strategy', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Test';

    $fakePage = productGridMakeFakeOffsetPage([], currentPage: 3, totalPages: 7);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    $data = $component->data($category, 3, 0, '');

    expect($data->currentPage)->toBe(3);
    expect($data->totalPages)->toBe(7);
});

it('resolves prices and names only for the products on the current page', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Test';

    // Only 2 products on page 2 (not all 50 products in the category)
    $product1 = new Product();
    $product1->id = 25;
    $product1->sku = 'P-025';
    $product1->name = 'Page Two First';

    $product2 = new Product();
    $product2->id = 26;
    $product2->sku = 'P-026';
    $product2->name = 'Page Two Second';

    // The fake page only contains these 2 products (not all 50)
    $fakePage = productGridMakeFakeOffsetPage([$product1, $product2], currentPage: 2, totalPages: 3);

    $priceCallCount = 0;
    $priceResolver = new class ($priceCallCount) implements PriceResolverInterface
    {
        public function __construct(public int &$callCount) {}

        public function resolve(PriceContext $context): Money
        {
            $this->callCount++;

            throw PriceUnavailableException::forContext($context);
        }
    };

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        $priceResolver,
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
    );

    $data = $component->data($category, 2, 0, '');

    // Only 2 price resolution calls — one per product on the current page
    expect($priceResolver->callCount)->toBe(2);
    expect($data->resolvedNames)->toHaveKey(25);
    expect($data->resolvedNames)->toHaveKey(26);
    expect($data->resolvedNames)->toHaveCount(2);
});

// ─── previousPageUrl / canonicalPageUrl (backward + scroll-spy URLs) ─────────────

function productGridDataForPage(int $currentPage, int $totalPages, int $size = 0, string $sort = ''): ProductGridData
{
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'SHOE-001';
    $product->name = 'Running Shoes';
    $productRepository->save($product);

    $fakePage = productGridMakeFakeOffsetPage([$product], currentPage: $currentPage, totalPages: $totalPages);

    $component = productGridBuildComponent(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        fakePage: $fakePage,
    );

    return $component->data($category, $currentPage, $size, $sort);
}

it('leaves previousPageUrl null on the first page', function (): void {
    expect(productGridDataForPage(1, 10)->previousPageUrl)->toBeNull();
});

it('sets previousPageUrl to the previous page fragment url when currentPage is greater than one', function (): void {
    expect(productGridDataForPage(3, 10)->previousPageUrl)->toBe('/catalog/category/1/page?page=2');
});

it('preserves non-default size and sort in previousPageUrl', function (): void {
    expect(productGridDataForPage(3, 10, 12, 'name')->previousPageUrl)
        ->toBe('/catalog/category/1/page?page=2&size=12&sort=name');
});

it('canonicalizes the first page to the bare category url (no page=1)', function (): void {
    expect(productGridDataForPage(1, 10)->canonicalPageUrl)->toBe('/catalog/category/1');
});

it('sets canonicalPageUrl to the full page url for pages after the first', function (): void {
    expect(productGridDataForPage(3, 10)->canonicalPageUrl)->toBe('/catalog/category/1?page=3');
});

// ─── Price index batch lookup ─────────────────────────────────────────────────

it('it loads all page prices in a single index query', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $product1 = new Product();
    $product1->sku = 'SHOE-001';
    $product1->name = 'Running Shoes';

    $product2 = new Product();
    $product2->sku = 'SHOE-002';
    $product2->name = 'Trail Shoes';

    $productRepository = new FakeProductRepository();
    $productRepository->save($product1);
    $productRepository->save($product2);

    $usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');

    $entry1 = new ProductPriceIndexEntry();
    $entry1->productId = $product1->id;
    $entry1->amount = '49.99';
    $entry1->currencyCode = 'USD';

    $entry2 = new ProductPriceIndexEntry();
    $entry2->productId = $product2->id;
    $entry2->amount = '59.99';
    $entry2->currencyCode = 'USD';

    $priceIndexRepo = new FakeProductPriceIndexRepository();
    $priceIndexRepo->entries[$product1->id] = $entry1;
    $priceIndexRepo->entries[$product2->id] = $entry2;

    $fakePage = productGridMakeFakeOffsetPage([$product1, $product2]);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter('en_US'),
        $priceIndexRepo,
        makeGridCurrencyResolver('USD'),
    );

    $data = $component->data($category, 1, 0, '');

    // Exactly one batch call — not two per-product calls
    expect($priceIndexRepo->findByProductIdsCallCount)->toBe(1);

    // Prices should be populated from the index
    expect($data->formattedPrices)->toHaveKey($product1->id)
        ->and($data->formattedPrices[$product1->id])->toContain('49.99')
        ->and($data->formattedPrices)->toHaveKey($product2->id)
        ->and($data->formattedPrices[$product2->id])->toContain('59.99');
});

it('it falls back to PriceResolver for products absent from the index', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $indexedProduct = new Product();
    $indexedProduct->sku = 'SHOE-001';
    $indexedProduct->name = 'Indexed Shoe';

    $unindexedProduct = new Product();
    $unindexedProduct->sku = 'SHOE-002';
    $unindexedProduct->name = 'Unindexed Shoe';

    $productRepository = new FakeProductRepository();
    $productRepository->save($indexedProduct);
    $productRepository->save($unindexedProduct);

    $usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');

    // Only the first product is in the index
    $entry = new ProductPriceIndexEntry();
    $entry->productId = $indexedProduct->id;
    $entry->amount = '29.99';
    $entry->currencyCode = 'USD';

    $priceIndexRepo = new FakeProductPriceIndexRepository();
    $priceIndexRepo->entries[$indexedProduct->id] = $entry;

    // Fallback resolver returns a price for the unindexed product only
    $fallbackMoney = Money::of('15.00', $usd);
    $priceResolver = new class ($unindexedProduct->id, $fallbackMoney) implements PriceResolverInterface
    {
        public function __construct(
            private readonly int $targetId,
            private readonly Money $money,
        ) {}

        public function resolve(PriceContext $context): Money
        {
            if ($context->product->id === $this->targetId) {
                return $this->money;
            }

            throw PriceUnavailableException::forContext($context);
        }
    };

    $fakePage = productGridMakeFakeOffsetPage([$indexedProduct, $unindexedProduct]);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        $priceResolver,
        makeGridMoneyFormatter('en_US'),
        $priceIndexRepo,
        makeGridCurrencyResolver('USD'),
    );

    $data = $component->data($category, 1, 0, '');

    // Indexed product price comes from the index
    expect($data->formattedPrices[$indexedProduct->id])->toContain('29.99');

    // Unindexed product price comes from the fallback resolver
    expect($data->formattedPrices[$unindexedProduct->id])->toContain('15.00');
});

it('it returns no formatted price when neither the index nor PriceResolver can resolve', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'SHOE-NO-PRICE';
    $product->name = 'No Price Shoe';

    $productRepository = new FakeProductRepository();
    $productRepository->save($product);

    // Empty index — product not indexed
    $priceIndexRepo = new FakeProductPriceIndexRepository();

    // Resolver also cannot provide a price
    $priceResolver = makeGridNoPricePriceResolver();

    $fakePage = productGridMakeFakeOffsetPage([$product]);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        productGridMakePaginationOptionsResolver(),
        $priceResolver,
        makeGridMoneyFormatter('en_US'),
        $priceIndexRepo,
        makeGridCurrencyResolver('USD'),
    );

    $data = $component->data($category, 1, 0, '');

    // No price available — should be null, not throw
    expect($data->formattedPrices)->toHaveKey($product->id)
        ->and($data->formattedPrices[$product->id])->toBeNull();
});

// ─── Sort dropdown requirements ───────────────────────────────────────────────

it('exposes the registered sort orders as dropdown options', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);

    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);
    $registry->register(new ColumnSortOrder(
        key: 'name',
        label: 'Name',
        column: 'catalog_product.name',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 10);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        new PaginationOptionsResolver(productGridMakeConfigResolver(), $registry),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
        $registry,
    );

    $data = $component->data($category, 1, 0, '');

    expect($data->sortOptions)->toHaveCount(2);
    expect($data->sortOptions[0])->toBe(['key' => 'position', 'label' => 'Position']);
    expect($data->sortOptions[1])->toBe(['key' => 'name', 'label' => 'Name']);
});

it('marks the active sort order as selected', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);

    $registry = productGridMakeSortRegistry(); // has position and name

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        new PaginationOptionsResolver(productGridMakeConfigResolver(), $registry),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
        $registry,
    );

    // Request sort=name explicitly
    $data = $component->data($category, 1, 0, 'name');

    expect($data->activeSort)->toBe('name');
});

it('defaults the active sort to position when no sort is requested', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);

    $registry = productGridMakeSortRegistry();

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        new PaginationOptionsResolver(productGridMakeConfigResolver(), $registry),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
        $registry,
    );

    // No sort requested — should default to position
    $data = $component->data($category, 1, 0, '');

    expect($data->activeSort)->toBe('position');
});

it('includes the price orders when the price index package is registered', function (): void {
    $category = new Category();
    $category->name = 'Shoes';

    $categoryRepository = new FakeCategoryRepository();
    $categoryRepository->save($category);

    $fakePage = productGridMakeFakeOffsetPage([]);

    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);
    $registry->register(new ColumnSortOrder(
        key: 'price_asc',
        label: 'Price: Low to High',
        column: 'price_index.amount',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 20);
    $registry->register(new ColumnSortOrder(
        key: 'price_desc',
        label: 'Price: High to Low',
        column: 'price_index.amount',
        direction: SortDirection::Descending,
        supportsKeyset: false,
    ), 30);

    $component = new ProductGridComponent(
        productGridMakeFakeService($fakePage),
        new PaginationOptionsResolver(productGridMakeConfigResolver(), $registry),
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter(),
        new FakeProductPriceIndexRepository(),
        makeGridCurrencyResolver(),
        $registry,
    );

    $data = $component->data($category, 1, 0, '');

    $keys = array_map(fn ($o) => $o['key'], $data->sortOptions);
    expect($keys)->toContain('price_asc');
    expect($keys)->toContain('price_desc');
    expect($keys)->toContain('position');
    expect($data->sortOptions)->toHaveCount(3);
});

it('renders a sort select that submits the sort query parameter', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Shoes';

    $registry = productGridMakeSortRegistry();

    $engine = productGridBuildLatte();

    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'category' => $category,
        'products' => [],
        'resolvedNames' => [],
        'resolvedDescs' => [],
        'sortOptions' => [
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'name', 'label' => 'Name'],
        ],
        'activeSort' => 'position',
    ]);

    expect($output)->toContain('<select');
    expect($output)->toContain('name="sort"');
    expect($output)->toContain('value="position"');
    expect($output)->toContain('value="name"');
});

it('resets to the first page when the sort changes', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Shoes';

    $engine = productGridBuildLatte();

    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'category' => $category,
        'products' => [],
        'resolvedNames' => [],
        'resolvedDescs' => [],
        'sortOptions' => [
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'name', 'label' => 'Name'],
        ],
        'activeSort' => 'position',
    ]);

    // The form must NOT include a hidden page param (so it resets to page 1)
    // and must use GET method to submit sort= as a query param
    expect($output)->toContain('method="get"');
    // Ensure no hidden page input is included that would preserve pagination
    expect($output)->not->toContain('<input type="hidden" name="page"');
});
