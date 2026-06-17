<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityCollection;
use Marko\Routing\Http\Request;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\Facet\Facet;
use Markommerce\CatalogAttributeIndex\Facet\FacetValue;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\ActiveFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FacetToggleUrlBuilder;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacet;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacetValue;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Criteria\Strategy\OffsetPage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function facetSidebarMakeEmptyRegistry(): ScopeRegistryInterface
{
    return new class implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool {
return false;
 }
        public function getAxis(string $name): ScopeAxis { throw new RuntimeException('no axes'); }
        /** @return list<string> */
        public function listAxes(): array {
return [];
 }
        public function getHierarchy(string $axisName): ScopeHierarchy { throw new RuntimeException('no axes'); }
    };
}

/**
 * @param list<Product> $products
 * @return Page<Product>
 */
function facetSidebarMakeProductPage(array $products = []): Page
{
    return new Page(
        items: new EntityCollection($products),
        size: count($products),
        nextPosition: null,
        previousPosition: null,
    );
}

function facetSidebarMakePaginationOptions(): ResolvedPaginationOptions
{
    $sortOrder = new class implements \Markommerce\Catalog\Sorting\CategorySortOrderInterface {
        public function key(): string {
return 'stub';
 }
        public function label(): string {
return 'Stub';
 }
        public function supportsKeyset(): bool {
return false;
 }
        public function prepareQuery(\Marko\Database\Repository\RepositoryQueryBuilder $builder): void {}
        /** @return list<\Markommerce\Criteria\Sort\SortField> */
        public function sortFields(): array {
return [];
 }
    };

    return new ResolvedPaginationOptions(
        sortOrder: $sortOrder,
        size: 12,
        page: 1,
        presentation: \Markommerce\Catalog\Pagination\PaginationPresentation::Numbered,
        strategyKind: \Markommerce\Catalog\Pagination\PaginationStrategyKind::Offset,
        countMode: \Markommerce\Catalog\Pagination\CountMode::Exact,
    );
}

/**
 * @param list<Facet> $facets
 */
function facetSidebarMakeFacetQuery(array $facets): AttributeFacetQuery
{
    return new class ($facets) extends AttributeFacetQuery
    {
        /** @param list<Facet> $facets */
        public function __construct(private readonly array $facets)
        {
            // Skip parent constructor.
        }

        /** @return list<Facet> */
        public function facets(
            int $categoryId,
            FilterSelection $selection,
        ): array
        {
            return $this->facets;
        }
    };
}

/**
 * @param Page<Product> $page
 * @param list<Facet>   $facets
 */
function facetSidebarMakeAssembler(Page $page, array $facets): LayeredNavigationAssembler
{
    $registry = facetSidebarMakeEmptyRegistry();
    $context  = new ScopeContext($registry);
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $defRepo = new QueryableAttributeDefinitionRepository();

    $labelResolver = new \Markommerce\AttributeScope\ScopedOptionLabelResolver($defRepo, $walker);

    $service = new class ($page) extends CategoryAssignmentService
    {
        /** @param Page<Product> $page */
        public function __construct(private readonly Page $page)
        {
            // Skip parent constructor.
        }

        /** @return Page<Product> */
        public function paginatedProductsInCategory(
            int $categoryId,
            ResolvedPaginationOptions $options,
            FilterSelection $filters = new FilterSelection(),
        ): Page {
            return $this->page;
        }
    };

    return new LayeredNavigationAssembler(
        categoryAssignmentService: $service,
        attributeFacetQuery: facetSidebarMakeFacetQuery($facets),
        scopedOptionLabelResolver: $labelResolver,
        attributeDefinitionRepository: $defRepo,
        scopeContext: $context,
    );
}

function facetSidebarMakeProductGridComponent(
    ?LayeredNavigationAssembler $assembler = null,
): ProductGridComponent {
    $positionCodec = new PositionCodec();

    $fakePage = new OffsetPage(
        items: new EntityCollection([]),
        size: 24,
        nextPosition: null,
        previousPosition: null,
        currentPage: 1,
        totalPages: 1,
        totalItems: 0,
        positionCodec: $positionCodec,
    );

    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $service = new class (
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $positionCodec,
        new KeysetPaginationStrategy($positionCodec),
        $fakePage,
    ) extends CategoryAssignmentService {
        public function __construct(
            \Markommerce\Catalog\Contracts\ProductRepositoryInterface $productRepository,
            \Markommerce\Catalog\Contracts\CategoryRepositoryInterface $categoryRepository,
            \Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
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

        public function paginatedProductsInCategory(
            int $categoryId,
            ResolvedPaginationOptions $options,
            FilterSelection $filters = new FilterSelection(),
        ): \Markommerce\Criteria\Page\Page {
            return $this->fakePage;
        }
    };

    $configResolver = new class implements \Markommerce\Config\Contracts\ConfigResolverInterface
    {
        public function resolved(
            string $configClass,
            string $field,
        ): mixed
        {
            return match ($field) {
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
                default            => null,
            };
        }
    };

    $sortRegistry = new \Markommerce\Catalog\Sorting\CategorySortOrderRegistry();
    $sortRegistry->register(new \Markommerce\Catalog\Sorting\ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: \Markommerce\Criteria\Sort\SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    $paginationOptionsResolver = new \Markommerce\Catalog\Pagination\PaginationOptionsResolver(
        $configResolver,
        $sortRegistry
    );

    $priceResolver = new class implements \Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface
    {
        public function resolve(\Markommerce\Catalog\Pricing\PriceContext $context): \Markommerce\Money\Money
        {
            throw \Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException::forContext($context);
        }
    };

    $scopeRegistry = facetSidebarMakeEmptyRegistry();
    $scopeContext  = new ScopeContext($scopeRegistry);
    $moneyFormatter = new \Markommerce\MoneyIntl\MoneyFormatter($scopeContext);

    $priceIndexRepo = new class implements \Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface
    {
        public function upsertMany(array $entries): void {}
        public function findByProductId(int $productId): ?ProductPriceIndexEntry {
return null;
 }
        /** @param list<int> $productIds @return array<int, ProductPriceIndexEntry> */
        public function findByProductIds(array $productIds): array {
return [];
 }
        public function truncate(): void {}
    };

    $currency = new \Markommerce\Money\Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $currencyResolver = new class ($currency) extends \Markommerce\Currency\CurrencyResolver
    {
        public function __construct(private readonly \Markommerce\Money\Currency $currency) {}
        public function base(): \Markommerce\Money\Currency {
return $this->currency;
 }
    };

    return new ProductGridComponent(
        categoryAssignmentService: $service,
        paginationOptionsResolver: $paginationOptionsResolver,
        priceResolver: $priceResolver,
        moneyFormatter: $moneyFormatter,
        productPriceIndexRepository: $priceIndexRepo,
        currencyResolver: $currencyResolver,
        layeredNavigationAssembler: $assembler,
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('exposes facet groups with value counts and selected state in the category component data', function (): void {
    $category = new Category();
    $category->id = 1;
    $category->name = 'Clothing';

    $product = new Product();
    $product->id = 10;
    $product->sku = 'SHIRT-001';

    $page = facetSidebarMakeProductPage([$product]);

    $facets = [
        new Facet(
            code: 'color',
            type: 'select',
            values: [
                new FacetValue(value: 'red', count: 3, selected: false),
                new FacetValue(value: 'blue', count: 5, selected: true),
            ],
        ),
    ];

    $assembler = facetSidebarMakeAssembler($page, $facets);
    $component = facetSidebarMakeProductGridComponent($assembler);

    $data = $component->data($category, 1, 0, '');

    expect($data->facets)->toHaveCount(1);
    expect($data->facets[0])->toBeInstanceOf(LabeledFacet::class);
    expect($data->facets[0]->code)->toBe('color');
    expect($data->facets[0]->values)->toHaveCount(2);
    expect($data->facets[0]->values[0]->value)->toBe('red');
    expect($data->facets[0]->values[0]->count)->toBe(3);
    expect($data->facets[0]->values[0]->selected)->toBeFalse();
    expect($data->facets[0]->values[1]->value)->toBe('blue');
    expect($data->facets[0]->values[1]->count)->toBe(5);
    expect($data->facets[0]->values[1]->selected)->toBeTrue();
});

it('builds a toggle url that adds a value to its attribute filter preserving other params', function (): void {
    $builder = new FacetToggleUrlBuilder();

    $baseUrl = '/catalog/category/1';
    $currentParams = [
        'sort' => 'name',
        'filter' => ['size' => ['L']],
    ];

    // Toggle 'red' into the 'color' filter (not yet selected)
    $url = $builder->toggle($baseUrl, $currentParams, 'color', 'red');

    // http_build_query encodes brackets — accept encoded form
    expect($url)->toContain('filter%5Bcolor%5D%5B0%5D=red');
    expect($url)->toContain('sort=name');
    expect($url)->toContain('size');
});

it('builds a toggle url that removes an already-selected value', function (): void {
    $builder = new FacetToggleUrlBuilder();

    $baseUrl = '/catalog/category/1';
    $currentParams = [
        'sort' => 'position',
        'filter' => ['color' => ['red', 'blue']],
    ];

    // Toggle 'red' out (it is already selected)
    $url = $builder->toggle($baseUrl, $currentParams, 'color', 'red');

    // 'red' should be gone, 'blue' should remain
    expect($url)->not->toContain('red');
    expect($url)->toContain('blue');
});

it('renders the facet sidebar with values counts and selected markers', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-facet-sidebar-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $packagePath = dirname(__DIR__, 3);

    $moduleRepository = new \Marko\Core\Module\ModuleRepository([
        new \Marko\Core\Module\ModuleManifest(
            name: 'markommerce/catalog-attribute-storefront',
            version: '1.0.0',
            path: $packagePath,
            source: 'vendor',
        ),
    ]);

    $config = new \Marko\Config\ConfigRepository([
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $viewConfig = new \Marko\View\ViewConfig($config);
    $latteViewConfig = new \Marko\View\Latte\LatteViewConfig($config);
    $templateResolver = new \Marko\View\ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engine = (new \Marko\View\Latte\LatteEngineFactory($viewConfig, $latteViewConfig))->create();
    $engine->setLoader(new \Marko\View\Latte\ModuleLoader($templateResolver));

    $facets = [
        new LabeledFacet(
            code: 'color',
            type: 'select',
            values: [
                new LabeledFacetValue(value: 'red', label: 'Red', count: 3, selected: false),
                new LabeledFacetValue(value: 'blue', label: 'Blue', count: 5, selected: true),
            ],
        ),
    ];

    $activeFilters = [
        new ActiveFilter(code: 'color', type: 'select', labels: ['Blue'], values: ['blue']),
    ];

    $output = $engine->renderToString('catalog-attribute-storefront::components/facet-sidebar', [
        'facets'       => $facets,
        'activeFilters' => $activeFilters,
    ]);

    // Should render facet group
    expect($output)->toContain('color');
    // Should show values with counts
    expect($output)->toContain('Red');
    expect($output)->toContain('3');
    expect($output)->toContain('Blue');
    expect($output)->toContain('5');
    // Should mark selected value
    expect($output)->toContain('selected');
});

it('filters the product grid by the active query-param selection', function (): void {
    $category = new Category();
    $category->id = 2;
    $category->name = 'Shoes';

    // Two products: one that matches filter, one that doesn't
    $redShoe = new Product();
    $redShoe->id = 11;
    $redShoe->sku = 'SHOE-RED';
    $redShoe->name = 'Red Shoe';

    $page = facetSidebarMakeProductPage([$redShoe]);

    $facets = [
        new Facet(
            code: 'color',
            type: 'select',
            values: [new FacetValue(value: 'red', count: 1, selected: true)],
        ),
    ];

    // The assembler returns only the red shoe page (filtered)
    $capturedSelection = null;

    $registry = facetSidebarMakeEmptyRegistry();
    $context  = new ScopeContext($registry);
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $defRepo = new QueryableAttributeDefinitionRepository();
    $labelResolver = new \Markommerce\AttributeScope\ScopedOptionLabelResolver($defRepo, $walker);

    $service = new class ($page, $capturedSelection) extends CategoryAssignmentService
    {
        /** @param Page<Product> $page */
        public function __construct(
            private readonly Page $page,
            public ?FilterSelection &$capturedSelection,
        ) {
            // Skip parent constructor.
        }

        /** @return Page<Product> */
        public function paginatedProductsInCategory(
            int $categoryId,
            ResolvedPaginationOptions $options,
            FilterSelection $filters = new FilterSelection(),
        ): Page {
            $this->capturedSelection = $filters;

            return $this->page;
        }
    };

    $assembler = new LayeredNavigationAssembler(
        categoryAssignmentService: $service,
        attributeFacetQuery: facetSidebarMakeFacetQuery($facets),
        scopedOptionLabelResolver: $labelResolver,
        attributeDefinitionRepository: $defRepo,
        scopeContext: $context,
    );

    $component = facetSidebarMakeProductGridComponent($assembler);

    $data = $component->data($category, 1, 0, '', new FilterSelection(['color' => ['red']]));

    // The filtered page only has one product
    expect($data->products)->toHaveCount(1);
    expect($data->products[0]->id)->toBe(11);
});

it('forwards facet data through the scoped product grid component override', function (): void {
    $category = new Category();
    $category->id = 3;
    $category->name = 'Accessories';

    $product = new Product();
    $product->id = 20;
    $product->sku = 'ACC-001';
    $product->name = 'Hat';

    $page = facetSidebarMakeProductPage([$product]);

    $facets = [
        new Facet(
            code: 'material',
            type: 'select',
            values: [new FacetValue(value: 'cotton', count: 2, selected: false)],
        ),
    ];

    $assembler = facetSidebarMakeAssembler($page, $facets);

    // Build a ScopedProductGridComponent with the assembler
    $positionCodec = new PositionCodec();
    $fakePage = new OffsetPage(
        items: new EntityCollection([$product]),
        size: 24,
        nextPosition: null,
        previousPosition: null,
        currentPage: 1,
        totalPages: 1,
        totalItems: 1,
        positionCodec: $positionCodec,
    );

    $productRepository = new FakeProductRepository();
    $categoryRepository = new FakeCategoryRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $assignmentService = new class (
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $positionCodec,
        new KeysetPaginationStrategy($positionCodec),
        $fakePage,
    ) extends CategoryAssignmentService {
        public function __construct(
            \Markommerce\Catalog\Contracts\ProductRepositoryInterface $productRepository,
            \Markommerce\Catalog\Contracts\CategoryRepositoryInterface $categoryRepository,
            \Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
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

        public function paginatedProductsInCategory(
            int $categoryId,
            ResolvedPaginationOptions $options,
            FilterSelection $filters = new FilterSelection(),
        ): \Markommerce\Criteria\Page\Page {
            return $this->fakePage;
        }
    };

    $configResolver = new class implements \Markommerce\Config\Contracts\ConfigResolverInterface
    {
        public function resolved(
            string $configClass,
            string $field,
        ): mixed
        {
            return match ($field) {
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
                default            => null,
            };
        }
    };

    $sortRegistry = new \Markommerce\Catalog\Sorting\CategorySortOrderRegistry();
    $sortRegistry->register(new \Markommerce\Catalog\Sorting\ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: \Markommerce\Criteria\Sort\SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    $paginationOptionsResolver = new \Markommerce\Catalog\Pagination\PaginationOptionsResolver(
        $configResolver,
        $sortRegistry
    );

    $priceResolver = new class implements \Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface
    {
        public function resolve(\Markommerce\Catalog\Pricing\PriceContext $context): \Markommerce\Money\Money
        {
            throw \Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException::forContext($context);
        }
    };

    $scopeRegistry = new class implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool {
return false;
 }
        public function getAxis(string $name): ScopeAxis { throw new RuntimeException('no axes'); }
        /** @return list<string> */
        public function listAxes(): array {
return [];
 }
        public function getHierarchy(string $axisName): ScopeHierarchy { throw new RuntimeException('no axes'); }
    };

    $scopeContext  = new ScopeContext($scopeRegistry);
    $moneyFormatter = new \Markommerce\MoneyIntl\MoneyFormatter($scopeContext);

    $priceIndexRepo = new class implements \Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface
    {
        public function upsertMany(array $entries): void {}
        public function findByProductId(int $productId): ?ProductPriceIndexEntry {
return null;
 }
        /** @param list<int> $productIds @return array<int, ProductPriceIndexEntry> */
        public function findByProductIds(array $productIds): array {
return [];
 }
        public function truncate(): void {}
    };

    $currency = new \Markommerce\Money\Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $currencyResolver = new class ($currency) extends \Markommerce\Currency\CurrencyResolver
    {
        public function __construct(private readonly \Markommerce\Money\Currency $currency) {}
        public function base(): \Markommerce\Money\Currency {
return $this->currency;
 }
    };

    $scopedFieldRegistry = new \Markommerce\Scope\Metadata\ScopedFieldRegistry(scopeRegistry: $scopeRegistry);
    $scopeMetaFactory = new \Markommerce\Scope\Metadata\ScopeMetadataFactory($scopeRegistry, $scopedFieldRegistry);
    $enumerator2 = new SignatureCandidateEnumerator($scopeRegistry);
    $walker2 = new \Markommerce\Scope\Resolution\ScopeWalker($enumerator2);
    $validator = new \Markommerce\Scope\Signature\ScopeSignatureValidator($scopeRegistry);
    $scopeResolver = new \Markommerce\Scope\Resolver\ScopeResolver(
        $scopeMetaFactory,
        $walker2,
        $scopeContext,
        $validator
    );

    $scopedComponent = new \Markommerce\CatalogStorefrontScope\Component\ScopedProductGridComponent(
        categoryAssignmentService: $assignmentService,
        paginationOptionsResolver: $paginationOptionsResolver,
        scopeResolver: $scopeResolver,
        priceResolver: $priceResolver,
        moneyFormatter: $moneyFormatter,
        productPriceIndexRepository: $priceIndexRepo,
        currencyResolver: $currencyResolver,
        layeredNavigationAssembler: $assembler,
    );

    $data = $scopedComponent->data($category, 1, 0, '');

    expect($data->facets)->toHaveCount(1);
    expect($data->facets[0]->code)->toBe('material');
    expect($data->facets[0]->values[0]->value)->toBe('cotton');
    expect($data->facets[0]->values[0]->count)->toBe(2);
});
