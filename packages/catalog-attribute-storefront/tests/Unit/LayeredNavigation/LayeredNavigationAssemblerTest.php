<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityCollection;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\Facet\Facet;
use Markommerce\CatalogAttributeIndex\Facet\FacetValue;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FilterParamParser;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LabeledFacet;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;
use Markommerce\CatalogStorefront\Data\LayeredNavigationData;
use Markommerce\Criteria\Page\Page;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Fakes ───────────────────────────────────────────────────────────────────

function makeEmptyRegistry(): ScopeRegistryInterface
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
function makeProductPage(array $products = []): Page
{
    return new Page(
        items: new EntityCollection($products),
        size: count($products),
        nextPosition: null,
        previousPosition: null,
    );
}

function makeAssemblerPaginationOptions(): ResolvedPaginationOptions
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
 * Fake CategoryAssignmentService that returns the configured page.
 *
 * @param Page<Product> $page
 */
function makeFakeListing(Page $page): CategoryAssignmentService
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
 * Fake AttributeFacetQuery that returns configured facets.
 *
 * @param list<Facet> $facets
 */
function makeFakeFacetQuery(array $facets): AttributeFacetQuery
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
 * Build a real ScopedOptionLabelResolver backed by stub scope deps.
 *
 * ScopedOptionLabelResolver is readonly and cannot be extended; this constructs a real
 * instance with a no-op ScopeWalker (backed by an empty scope registry) so the resolver
 * falls back to the option label unchanged — matching the original fake intent.
 */
function makeFakeLabelResolver(): ScopedOptionLabelResolver
{
    $registry   = makeEmptyRegistry();
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker     = new ScopeWalker($enumerator);
    $defRepo    = new QueryableAttributeDefinitionRepository();

    return new ScopedOptionLabelResolver($defRepo, $walker);
}

/**
 * Build a LayeredNavigationAssembler with a fake option lookup that finds nothing.
 *
 * @param Page<Product>  $page
 * @param list<Facet>    $facets
 */
function makeAssembler(Page $page, array $facets, ?ScopedOptionLabelResolver $resolver = null): LayeredNavigationAssembler
{
    $registry = makeEmptyRegistry();
    $context  = new ScopeContext($registry);
    $defRepo  = new QueryableAttributeDefinitionRepository();

    return new LayeredNavigationAssembler(
        categoryAssignmentService: makeFakeListing($page),
        attributeFacetQuery: makeFakeFacetQuery($facets),
        scopedOptionLabelResolver: $resolver ?? makeFakeLabelResolver(),
        attributeDefinitionRepository: $defRepo,
        scopeContext: $context,
        filterParamParser: new FilterParamParser($defRepo),
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('assembles the filtered product page and facets for a category and selection', function (): void {
    $product = new Product();
    $product->id = 1;

    $page = makeProductPage([$product]);

    $facetValues = [new FacetValue(value: 'red', count: 3, selected: false)];
    $facets = [new Facet(code: 'color', type: 'select', values: $facetValues)];

    $assembler = makeAssembler($page, $facets);
    $options   = makeAssemblerPaginationOptions();
    $selection = new FilterSelection(['color' => ['red']]);

    $result = $assembler->forCategory(1, $options, $selection);

    expect($result)->toBeInstanceOf(LayeredNavigationData::class)
        ->and($result->page)->toBe($page)
        ->and($result->facets)->toHaveCount(1)
        ->and($result->facets[0])->toBeInstanceOf(LabeledFacet::class)
        ->and($result->facets[0]->code)->toBe('color');
});

it('resolves select-option display labels for the active scope', function (): void {
    // Build a scope registry with a 'locale' axis having 'en' and 'fr' scopes.
    $registry = new class implements ScopeRegistryInterface {
        public function hasAxis(string $name): bool {
return $name === 'locale';
 }
        public function getAxis(string $name): ScopeAxis
        {
            if ($name !== 'locale') {
                throw new RuntimeException('unknown axis');
            }

            return new ScopeAxis(name: 'locale', hierarchy: new ScopeHierarchy(['en', 'fr']), default: 'en');
        }

        /** @return list<string> */
        public function listAxes(): array {
return ['locale'];
 }
        public function getHierarchy(string $axisName): ScopeHierarchy {
return $this->getAxis($axisName)->hierarchy;
 }
    };

    $context = new ScopeContext($registry);
    $context->in('locale', 'fr');

    // Build a resolver with the real ScopeWalker + our registry.
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker     = new ScopeWalker($enumerator);
    $defRepo    = new QueryableAttributeDefinitionRepository();

    // Register the 'color' attribute definition with locale axis.
    $def = new AttributeDefinition();
    $def->id = 1;
    $def->code = 'color';
    $def->entityType = 'product';
    $def->type = 'select';
    $def->backing = 'Json';
    $def->facetable = true;
    $def->config = ['axes' => ['locale']];
    $defRepo->save($def);

    // Create an option with a base label and a French scoped override.
    $option = new AttributeOption();
    $option->id = 10;
    $option->attributeId = 1;
    $option->value = 'red';
    $option->label = 'Red';

    $scopedLabels = new AttributeOptionScopedLabels();
    $scopedLabels->setOverride('locale:fr', 'label', 'Rouge');
    $option->attachCompanion($scopedLabels);

    $defRepo->saveOption($option);

    $resolver = new ScopedOptionLabelResolver($defRepo, $walker);

    $page = makeProductPage();
    $facetValues = [new FacetValue(value: 'red', count: 5, selected: false)];
    $facets = [new Facet(code: 'color', type: 'select', values: $facetValues)];

    $assembler = new LayeredNavigationAssembler(
        categoryAssignmentService: makeFakeListing($page),
        attributeFacetQuery: makeFakeFacetQuery($facets),
        scopedOptionLabelResolver: $resolver,
        attributeDefinitionRepository: $defRepo,
        scopeContext: $context,
        filterParamParser: new FilterParamParser($defRepo),
    );

    $result = $assembler->forCategory(1, makeAssemblerPaginationOptions(), new FilterSelection());

    $labeledFacet = $result->facets[0];
    expect($labeledFacet->values[0]->value)->toBe('red')
        ->and($labeledFacet->values[0]->label)->toBe('Rouge');
});

it('returns the active filters alongside the facets', function (): void {
    $page = makeProductPage();

    $facetValues = [
        new FacetValue(value: 'red', count: 3, selected: true),
        new FacetValue(value: 'blue', count: 2, selected: false),
    ];
    $facets = [new Facet(code: 'color', type: 'select', values: $facetValues)];

    $assembler = makeAssembler($page, $facets);
    $options   = makeAssemblerPaginationOptions();
    $selection = new FilterSelection(['color' => ['red']]);

    $result = $assembler->forCategory(1, $options, $selection);

    expect($result->activeFilters)->toHaveCount(1)
        ->and($result->activeFilters[0]->code)->toBe('color')
        ->and($result->activeFilters[0]->values)->toBe(['red']);
});

it('parses a raw filter array into a selection via selectionFromQuery', function (): void {
    $registry = makeEmptyRegistry();
    $context  = new ScopeContext($registry);

    // The parser keeps only known facetable attribute codes — register 'color'.
    $defRepo = new QueryableAttributeDefinitionRepository();

    $colorDef = new AttributeDefinition();
    $colorDef->code = 'color';
    $colorDef->entityType = 'product';
    $colorDef->type = 'select';
    $colorDef->backing = 'Json';
    $colorDef->facetable = true;
    $defRepo->save($colorDef);

    $assembler = new LayeredNavigationAssembler(
        categoryAssignmentService: makeFakeListing(makeProductPage()),
        attributeFacetQuery: makeFakeFacetQuery([]),
        scopedOptionLabelResolver: makeFakeLabelResolver(),
        attributeDefinitionRepository: $defRepo,
        scopeContext: $context,
        filterParamParser: new FilterParamParser($defRepo),
    );

    // 'color' is known (kept), 'unknown' is dropped, scalar normalized to a list.
    $selection = $assembler->selectionFromQuery(['color' => 'red', 'unknown' => ['x']]);

    expect($selection)->toBeInstanceOf(FilterSelection::class)
        ->and($selection->keys())->toBe(['color'])
        ->and($selection->forKey('color'))->toBe(['red']);
});
