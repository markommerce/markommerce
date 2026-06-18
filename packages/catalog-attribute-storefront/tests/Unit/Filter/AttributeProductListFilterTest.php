<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeStorefront\Filter\AttributeProductListFilter;
use Markommerce\CatalogAttributeStorefront\Tests\Support\SpyRepositoryQueryBuilder;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap
 * @param array<string, string>       $defaults
 */
function makeFilterRegistry(
    array $axesMap = [],
    array $defaults = [],
): ScopeRegistryInterface {
    return new class ($axesMap, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap @param array<string, string> $defaults */
        public function __construct(
            array $axesMap,
            array $defaults = [],
        ) {
            $this->builtAxes = [];

            foreach ($axesMap as $name => $paths) {
                $default = $defaults[$name] ?? ($paths[0] ?? '__default');

                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }

                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

function makeFilterDef(
    QueryableAttributeDefinitionRepository $repo,
    string $code,
    bool $facetable = true,
    bool $filterable = true,
    bool $scopable = false,
    array $axes = [],
): AttributeDefinition {
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = 'select';
    $def->backing = 'Json';
    $def->facetable = $facetable;
    $def->filterable = $filterable;
    $def->scopable = $scopable;
    $def->config = $axes ? ['axes' => $axes] : [];
    $repo->save($def);

    return $def;
}

function makeFilter(
    QueryableAttributeDefinitionRepository $defRepo,
    ScopeRegistryInterface $registry,
    ScopeContext $context,
): AttributeProductListFilter {
    $enumerator = new SignatureCandidateEnumerator($registry);
    $existsClause = new AttributeExistsClause();

    return new AttributeProductListFilter(
        attributeDefinitionRepository: $defRepo,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
        attributeExistsClause: $existsClause,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('adds an EXISTS constraint for a selected attribute value', function (): void {
    $registry = makeFilterRegistry();
    $context = new ScopeContext($registry);
    $defRepo = new QueryableAttributeDefinitionRepository();

    makeFilterDef($defRepo, 'color', facetable: true, filterable: true);

    $filter = makeFilter($defRepo, $registry, $context);
    $spy = new SpyRepositoryQueryBuilder();

    $selection = new FilterSelection(['color' => ['red']]);
    $filter->apply($spy, $selection);

    expect($spy->whereRawCalls)->toHaveCount(1);

    $call = $spy->whereRawCalls[0];
    expect($call['sql'])->toContain('EXISTS')
        ->and($call['sql'])->toContain('catalog_product_attribute_index')
        ->and($call['sql'])->toContain('catalog_products.id')
        ->and($call['bindings'])->toContain('color')
        ->and($call['bindings'])->toContain('red');
});

it('ORs multiple selected values for the same attribute via IN', function (): void {
    $registry = makeFilterRegistry();
    $context = new ScopeContext($registry);
    $defRepo = new QueryableAttributeDefinitionRepository();

    makeFilterDef($defRepo, 'color', facetable: true, filterable: true);

    $filter = makeFilter($defRepo, $registry, $context);
    $spy = new SpyRepositoryQueryBuilder();

    $selection = new FilterSelection(['color' => ['red', 'blue', 'green']]);
    $filter->apply($spy, $selection);

    // Only one EXISTS clause (values OR-ed via IN)
    expect($spy->whereRawCalls)->toHaveCount(1);

    $call = $spy->whereRawCalls[0];
    expect($call['sql'])->toContain('IN (?, ?, ?)')
        ->and($call['bindings'])->toContain('red')
        ->and($call['bindings'])->toContain('blue')
        ->and($call['bindings'])->toContain('green');
});

it('ANDs constraints across different selected attributes', function (): void {
    $registry = makeFilterRegistry();
    $context = new ScopeContext($registry);
    $defRepo = new QueryableAttributeDefinitionRepository();

    makeFilterDef($defRepo, 'color', facetable: true, filterable: true);
    makeFilterDef($defRepo, 'size', facetable: true, filterable: true);

    $filter = makeFilter($defRepo, $registry, $context);
    $spy = new SpyRepositoryQueryBuilder();

    $selection = new FilterSelection(['color' => ['red'], 'size' => ['L']]);
    $filter->apply($spy, $selection);

    // Two separate EXISTS clauses (one per attribute = AND)
    expect($spy->whereRawCalls)->toHaveCount(2);

    $codes = array_map(fn (array $c) => $c['bindings'][0], $spy->whereRawCalls);
    expect($codes)->toContain('color')
        ->and($codes)->toContain('size');
});

it('resolves the scope signature for a scopable attribute from the context', function (): void {
    $registry = makeFilterRegistry(
        axesMap: ['store' => ['default', 'default.en', 'default.fr']],
        defaults: ['store' => 'default'],
    );
    $context = new ScopeContext($registry);
    $context->in('store', 'default.en');

    $defRepo = new QueryableAttributeDefinitionRepository();
    makeFilterDef($defRepo, 'color', facetable: true, filterable: true, scopable: true, axes: ['store']);

    $filter = makeFilter($defRepo, $registry, $context);
    $spy = new SpyRepositoryQueryBuilder();

    $selection = new FilterSelection(['color' => ['red']]);
    $filter->apply($spy, $selection);

    expect($spy->whereRawCalls)->toHaveCount(1);

    $call = $spy->whereRawCalls[0];
    // Signature binding is the second binding (after attribute_code)
    expect($call['bindings'][1])->toBe('store:default.en');
});

it('uses the base signature for a non-scopable attribute', function (): void {
    $registry = makeFilterRegistry(
        axesMap: ['store' => ['default', 'default.en']],
        defaults: ['store' => 'default'],
    );
    $context = new ScopeContext($registry);
    $context->in('store', 'default.en');

    $defRepo = new QueryableAttributeDefinitionRepository();
    // No axes — non-scopable attribute
    makeFilterDef($defRepo, 'brand', facetable: true, filterable: true, scopable: false, axes: []);

    $filter = makeFilter($defRepo, $registry, $context);
    $spy = new SpyRepositoryQueryBuilder();

    $selection = new FilterSelection(['brand' => ['Acme']]);
    $filter->apply($spy, $selection);

    expect($spy->whereRawCalls)->toHaveCount(1);

    $call = $spy->whereRawCalls[0];
    // Signature binding is second; base signature is empty string
    expect($call['bindings'][1])->toBe('');
});

it('is a no-op when the selection has no attribute keys', function (): void {
    $registry = makeFilterRegistry();
    $context = new ScopeContext($registry);
    $defRepo = new QueryableAttributeDefinitionRepository();

    makeFilterDef($defRepo, 'color', facetable: true, filterable: true);

    $filter = makeFilter($defRepo, $registry, $context);
    $spy = new SpyRepositoryQueryBuilder();

    // Selection has a key but it is NOT an attribute code in the repo
    $selection = new FilterSelection(['category_id' => ['5']]);
    $filter->apply($spy, $selection);

    expect($spy->whereRawCalls)->toHaveCount(0);
});
