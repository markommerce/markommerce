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

// ─── Render Engine Helper ─────────────────────────────────────────────────────

function facetSidebarMakeLatteEngine(): \Latte\Engine
{
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

    return $engine;
}

/**
 * @param list<LabeledFacet> $facets
 * @param list<ActiveFilter>  $activeFilters
 * @param array<string, array<string, string>> $toggleUrls
 */
function facetSidebarRender(
    array $facets = [],
    array $activeFilters = [],
    array $toggleUrls = [],
    ?string $clearAllUrl = null,
): string {
    return facetSidebarMakeLatteEngine()->renderToString(
        'catalog-attribute-storefront::components/facet-sidebar',
        array_filter([
            'facets'        => $facets,
            'activeFilters' => $activeFilters,
            'toggleUrls'    => $toggleUrls ?: null,
            'clearAllUrl'   => $clearAllUrl,
        ], fn (mixed $v): bool => $v !== null),
    );
}

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

/**
 * Build a LayeredNavigationAssembler with a pre-seeded 'color' attribute definition
 * so the FilterParamParser recognises 'color' as a known facetable code.
 *
 * @param Page<Product> $page
 * @param list<Facet>   $facets
 */
function facetSidebarMakeAssemblerWithColorAttr(Page $page, array $facets): LayeredNavigationAssembler
{
    $registry      = facetSidebarMakeEmptyRegistry();
    $context       = new ScopeContext($registry);
    $enumerator    = new SignatureCandidateEnumerator($registry);
    $walker        = new ScopeWalker($enumerator);
    $defRepo       = new QueryableAttributeDefinitionRepository();
    $labelResolver = new \Markommerce\AttributeScope\ScopedOptionLabelResolver($defRepo, $walker);

    // Register 'color' as a facetable product attribute so FilterParamParser keeps it.
    $colorDef             = new \Markommerce\Attribute\Entity\AttributeDefinition();
    $colorDef->code       = 'color';
    $colorDef->entityType = 'product';
    $colorDef->type       = 'select';
    $colorDef->label      = 'Color';
    $colorDef->facetable  = true;
    $defRepo->save($colorDef);

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
 * @param Page<Product> $page
 * @param list<Facet>   $facets
 */
function facetSidebarMakeComponentWithColorAttr(Page $page, array $facets): FacetSidebarComponent
{
    return new FacetSidebarComponent(
        layeredNavigationAssembler: facetSidebarMakeAssemblerWithColorAttr($page, $facets),
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

// ─── Task 003: Restyle tests ──────────────────────────────────────────────────

it('renders each facet group with a titled group container', function (): void {
    $facets = [
        new LabeledFacet(
            code: 'color',
            type: 'select',
            values: [
                new LabeledFacetValue(value: 'red', label: 'Red', count: 3, selected: false),
            ],
        ),
        new LabeledFacet(
            code: 'size',
            type: 'select',
            values: [
                new LabeledFacetValue(value: 'L', label: 'Large', count: 2, selected: false),
            ],
        ),
    ];

    $output = facetSidebarRender(facets: $facets);

    // Each group must have a container with data-facet-code
    expect($output)
        ->toContain('catalog-facet-sidebar__group')
        ->toContain('data-facet-code="color"')
        ->toContain('data-facet-code="size"')
        // Each group must have a titled header (group-title class)
        ->toContain('catalog-facet-sidebar__group-title')
        ->toContain('color')
        ->toContain('size');
});

it('renders facet values as checkbox-style rows with label and count', function (): void {
    $facets = [
        new LabeledFacet(
            code: 'color',
            type: 'select',
            values: [
                new LabeledFacetValue(value: 'red', label: 'Red', count: 3, selected: false),
                new LabeledFacetValue(value: 'blue', label: 'Blue', count: 5, selected: false),
            ],
        ),
    ];

    $output = facetSidebarRender(facets: $facets);

    // Each value row has the base value class
    expect($output)->toContain('catalog-facet-sidebar__value');
    // Checkbox glyph element
    expect($output)->toContain('catalog-facet-sidebar__checkbox');
    // Label is present
    expect($output)->toContain('catalog-facet-sidebar__value-label');
    expect($output)->toContain('Red');
    expect($output)->toContain('Blue');
    // Count is present
    expect($output)->toContain('catalog-facet-sidebar__count');
    expect($output)->toContain('3');
    expect($output)->toContain('5');
});

it('marks selected facet values with a selected state and aria attribute', function (): void {
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

    $output = facetSidebarRender(facets: $facets);

    // Selected value gets the --selected modifier class
    expect($output)->toContain('catalog-facet-sidebar__value--selected');
    // Selected value has an aria attribute indicating selection
    expect($output)->toMatch('/aria-(pressed|current)="true"/');
    // Non-selected value does NOT get the --selected modifier
    expect($output)->not->toContain('catalog-facet-sidebar__value--selected" data-facet-code');
});

it('keeps facet toggles as no-js anchor links', function (): void {
    $facets = [
        new LabeledFacet(
            code: 'color',
            type: 'select',
            values: [
                new LabeledFacetValue(value: 'red', label: 'Red', count: 3, selected: false),
            ],
        ),
    ];

    $toggleUrls = [
        'color' => ['red' => '/catalog/category/1?filter%5Bcolor%5D%5B0%5D=red'],
    ];

    $output = facetSidebarRender(facets: $facets, toggleUrls: $toggleUrls);

    // Toggle is an anchor link, not a button or form submit
    expect($output)->toContain('<a ');
    expect($output)->toContain('href=');
    expect($output)->toContain('catalog-facet-sidebar__toggle');
    // No onclick, no JS event handlers
    expect($output)->not->toContain('onclick');
    expect($output)->not->toContain('javascript:');
});

it('renders an empty sidebar gracefully when there are no facets', function (): void {
    $output = facetSidebarRender(facets: []);

    // Must not emit group chrome
    expect($output)->not->toContain('catalog-facet-sidebar__group');
    expect($output)->not->toContain('catalog-facet-sidebar__value');
    // The outer container should still be present (for CSS anchoring) or render nothing heavy
    // Either way, no active-filter chrome should appear when there are no active filters
    expect($output)->not->toContain('catalog-facet-sidebar__active-filters');
});

it('defines facet sidebar styles in the components layer using mk design tokens', function (): void {
    $cssPath = dirname(__DIR__, 3) . '/resources/css/components/facet-sidebar.css';

    expect(file_exists($cssPath))->toBeTrue();

    $css = file_get_contents($cssPath);

    // Must use @layer components
    expect($css)->toContain('@layer components');
    // Must define the sidebar class
    expect($css)->toContain('.catalog-facet-sidebar');
    // Must define the group and value classes
    expect($css)->toContain('.catalog-facet-sidebar__group');
    expect($css)->toContain('.catalog-facet-sidebar__value');
    // Checkbox glyph
    expect($css)->toContain('.catalog-facet-sidebar__checkbox');
    // Count class
    expect($css)->toContain('.catalog-facet-sidebar__count');
    // Selected modifier
    expect($css)->toContain('.catalog-facet-sidebar__value--selected');
    // Must use --mk-* design tokens
    expect($css)->toMatch('/--mk-[a-z][-a-z0-9]*/');
});

// ─── Task 004: Active-filter chips tests ─────────────────────────────────────

it('renders an active filter chip per selected value', function (): void {
    $activeFilters = [
        new ActiveFilter(
            code: 'color',
            type: 'select',
            labels: ['Red', 'Blue'],
            values: ['red', 'blue'],
        ),
    ];

    $toggleUrls = [
        'color' => [
            'red'  => '/catalog/category/1?filter%5Bcolor%5D%5B0%5D=blue',
            'blue' => '/catalog/category/1?filter%5Bcolor%5D%5B0%5D=red',
        ],
    ];

    $output = facetSidebarRender(activeFilters: $activeFilters, toggleUrls: $toggleUrls);

    // One chip per value
    expect($output)->toContain('catalog-facet-active__chip');
    // Both value labels appear in chips
    expect($output)->toContain('Red');
    expect($output)->toContain('Blue');
    // Active filters container rendered
    expect($output)->toContain('catalog-facet-active');
});

it('links each chip remove control to the url that deselects that value', function (): void {
    $activeFilters = [
        new ActiveFilter(
            code: 'color',
            type: 'select',
            labels: ['Red'],
            values: ['red'],
        ),
    ];

    $toggleUrls = [
        'color' => [
            'red' => '/catalog/category/1',
        ],
    ];

    $output = facetSidebarRender(activeFilters: $activeFilters, toggleUrls: $toggleUrls);

    // The chip's remove link points to the toggle URL for that value
    expect($output)->toContain('href="/catalog/category/1"');
    // Remove affordance is present
    expect($output)->toContain('catalog-facet-active__remove');
});

it('renders a clear-all link that removes all attribute filters preserving sort', function (): void {
    $category = new Category();
    $category->id   = 5;
    $category->name = 'Shoes';

    $page = facetSidebarMakeProductPage();

    $facets = [
        new Facet(
            code: 'color',
            type: 'select',
            values: [
                new FacetValue(value: 'red', count: 2, selected: true),
            ],
        ),
    ];

    // Use the helper that seeds 'color' as a facetable attribute so the filter is recognised.
    $component = facetSidebarMakeComponentWithColorAttr($page, $facets);

    // color=red is active, sort=position is set
    $data = $component->data($category, 1, 0, 'position', ['color' => ['red']]);

    // clearAllUrl must be set because a filter is active
    expect($data->clearAllUrl)->not->toBeNull();
    // It preserves sort but omits filter
    expect($data->clearAllUrl)->toContain('sort=position');
    expect($data->clearAllUrl)->not->toContain('filter');
    expect($data->clearAllUrl)->toStartWith('/catalog/category/5');

    // Render it — the clear-all link must appear in the template
    $output = facetSidebarRender(
        activeFilters: $data->activeFilters,
        toggleUrls: $data->toggleUrls,
        clearAllUrl: $data->clearAllUrl,
    );

    expect($output)->toContain('catalog-facet-active__clear');
    expect($output)->toContain('href="' . $data->clearAllUrl . '"');
});

it('hides the active filters block when no filters are selected', function (): void {
    $output = facetSidebarRender(activeFilters: []);

    expect($output)->not->toContain('catalog-facet-active');
    expect($output)->not->toContain('catalog-facet-active__chip');
});

it('gives each remove control an accessible label', function (): void {
    $activeFilters = [
        new ActiveFilter(
            code: 'color',
            type: 'select',
            labels: ['Red'],
            values: ['red'],
        ),
    ];

    $toggleUrls = [
        'color' => [
            'red' => '/catalog/category/1',
        ],
    ];

    $output = facetSidebarRender(activeFilters: $activeFilters, toggleUrls: $toggleUrls);

    // The remove link carries an aria-label naming what is being removed
    expect($output)->toContain('aria-label="Remove Red"');
});

it('still renders a chip whose value has no toggle url without erroring', function (): void {
    $activeFilters = [
        new ActiveFilter(
            code: 'color',
            type: 'select',
            labels: ['Red', 'Green'],
            values: ['red', 'green'],
        ),
    ];

    // Only 'red' has a toggle URL; 'green' is absent from $toggleUrls
    $toggleUrls = [
        'color' => [
            'red' => '/catalog/category/1',
        ],
    ];

    // Must not throw; must still render both chip labels
    $output = facetSidebarRender(activeFilters: $activeFilters, toggleUrls: $toggleUrls);

    expect($output)->toContain('Red');
    expect($output)->toContain('Green');
    // 'green' chip still appears but without a remove link
    expect($output)->toContain('catalog-facet-active__chip');
});
