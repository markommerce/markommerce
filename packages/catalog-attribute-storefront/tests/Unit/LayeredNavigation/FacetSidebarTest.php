<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityCollection;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\Facet\Facet;
use Markommerce\CatalogAttributeIndex\Facet\FacetValue;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeStorefront\Component\FacetSidebarComponent;
use Markommerce\CatalogAttributeStorefront\Data\FacetSidebarData;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\ActiveFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FacetToggleUrlBuilder;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FilterParamParser;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacet;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacetValue;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;
use Markommerce\Criteria\Page\Page;
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
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw new RuntimeException('no axes');
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw new RuntimeException('no axes');
        }
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
 * Fake CategoryAssignmentService that returns the configured page for any call.
 *
 * @param Page<Product> $page
 */
function facetSidebarMakeListing(Page $page): CategoryAssignmentService
{
    return new class ($page) extends CategoryAssignmentService
    {
        /** @param Page<Product> $page */
        public function __construct(private readonly Page $page)
        {
            // Skip parent constructor — no real deps needed.
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
}

/**
 * Build a LayeredNavigationAssembler over fake deps that returns the given facets.
 *
 * @param Page<Product> $page
 * @param list<Facet>   $facets
 */
function facetSidebarMakeAssembler(Page $page, array $facets): LayeredNavigationAssembler
{
    $registry      = facetSidebarMakeEmptyRegistry();
    $context       = new ScopeContext($registry);
    $enumerator    = new SignatureCandidateEnumerator($registry);
    $walker        = new ScopeWalker($enumerator);
    $defRepo       = new QueryableAttributeDefinitionRepository();
    $labelResolver = new \Markommerce\AttributeScope\ScopedOptionLabelResolver($defRepo, $walker);

    return new LayeredNavigationAssembler(
        categoryAssignmentService: facetSidebarMakeListing($page),
        attributeFacetQuery: facetSidebarMakeFacetQuery($facets),
        scopedOptionLabelResolver: $labelResolver,
        attributeDefinitionRepository: $defRepo,
        scopeContext: $context,
        filterParamParser: new FilterParamParser($defRepo),
    );
}

/**
 * Build a PaginationOptionsResolver backed by a stub config resolver and a single
 * registered "position" sort order, so FacetSidebarComponent can resolve options.
 */
function facetSidebarMakePaginationOptionsResolver(): PaginationOptionsResolver
{
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

    return new PaginationOptionsResolver($configResolver, $sortRegistry);
}

/**
 * @param Page<Product> $page
 * @param list<Facet>   $facets
 */
function facetSidebarMakeComponent(Page $page, array $facets): FacetSidebarComponent
{
    return new FacetSidebarComponent(
        layeredNavigationAssembler: facetSidebarMakeAssembler($page, $facets),
        paginationOptionsResolver: facetSidebarMakePaginationOptionsResolver(),
        facetToggleUrlBuilder: new FacetToggleUrlBuilder(),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('exposes facet groups with toggle urls and selected state', function (): void {
    $category = new Category();
    $category->id   = 1;
    $category->name = 'Clothing';

    $page = facetSidebarMakeProductPage();

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

    $component = facetSidebarMakeComponent($page, $facets);

    $data = $component->data($category, 1, 0, '');

    expect($data)->toBeInstanceOf(FacetSidebarData::class)
        ->and($data->facets)->toHaveCount(1)
        ->and($data->facets[0])->toBeInstanceOf(LabeledFacet::class)
        ->and($data->facets[0]->code)->toBe('color')
        ->and($data->facets[0]->values[0]->value)->toBe('red')
        ->and($data->facets[0]->values[0]->count)->toBe(3)
        ->and($data->facets[0]->values[0]->selected)->toBeFalse()
        ->and($data->facets[0]->values[1]->value)->toBe('blue')
        ->and($data->facets[0]->values[1]->selected)->toBeTrue();

    // Toggle URLs are keyed [facetCode][value] and point at the category base URL.
    expect($data->toggleUrls)->toHaveKey('color')
        ->and($data->toggleUrls['color'])->toHaveKey('red')
        ->and($data->toggleUrls['color'])->toHaveKey('blue')
        ->and($data->toggleUrls['color']['red'])->toStartWith('/catalog/category/1');
});

it('builds toggle urls that add/remove a value preserving sort', function (): void {
    $category = new Category();
    $category->id   = 7;
    $category->name = 'Shoes';

    $page = facetSidebarMakeProductPage();

    // 'red' is already selected (so its toggle removes it); 'blue' is not (so its toggle adds it).
    $facets = [
        new Facet(
            code: 'color',
            type: 'select',
            values: [
                new FacetValue(value: 'red', count: 2, selected: true),
                new FacetValue(value: 'blue', count: 4, selected: false),
            ],
        ),
    ];

    $component = facetSidebarMakeComponent($page, $facets);

    // Current state: sort=position, color=red selected.
    $data = $component->data($category, 1, 0, 'position', ['color' => ['red']]);

    $removeRedUrl = $data->toggleUrls['color']['red'];
    $addBlueUrl   = $data->toggleUrls['color']['blue'];

    // Sort is preserved on both URLs.
    expect($removeRedUrl)->toContain('sort=position')
        ->and($addBlueUrl)->toContain('sort=position');

    // Removing the already-selected 'red' drops it from the query entirely.
    expect($removeRedUrl)->not->toContain('red');

    // Adding 'blue' keeps the existing 'red' selection and appends 'blue'.
    expect($addBlueUrl)->toContain('red')
        ->and($addBlueUrl)->toContain('blue');

    // Both URLs share the category base path.
    expect($removeRedUrl)->toStartWith('/catalog/category/7')
        ->and($addBlueUrl)->toStartWith('/catalog/category/7');
});

it('returns empty data when the category id is null', function (): void {
    $category = new Category();
    $category->id = null;

    $page = facetSidebarMakeProductPage();

    $facets = [
        new Facet(
            code: 'color',
            type: 'select',
            values: [new FacetValue(value: 'red', count: 1, selected: false)],
        ),
    ];

    $component = facetSidebarMakeComponent($page, $facets);

    $data = $component->data($category, 1, 0, '');

    expect($data)->toBeInstanceOf(FacetSidebarData::class)
        ->and($data->facets)->toBe([])
        ->and($data->activeFilters)->toBe([])
        ->and($data->toggleUrls)->toBe([]);
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
