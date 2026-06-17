<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;
use Markommerce\CatalogAttributeIndex\Tests\Support\FakeConnection;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
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
function makeFacetQueryRegistry(
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
        )
        {
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

function makeFacetQueryDef(
    QueryableAttributeDefinitionRepository $repo,
    string $code,
    bool $facetable,
    bool $scopable = false,
    array $axes = [],
): AttributeDefinition {
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = 'select';
    $def->backing = 'Json';
    $def->facetable = $facetable;
    $def->filterable = false;
    $def->scopable = $scopable;
    $def->config = $axes ? ['axes' => $axes] : [];
    $repo->save($def);

    return $def;
}

/**
 * @param array<int, array<string, mixed>> $rows  per-query result set (consumed in order)
 */
function makeAttributeFacetQuery(
    FakeConnection $connection,
    QueryableAttributeDefinitionRepository $defRepo,
    ScopeRegistryInterface $registry,
    ScopeContext $context,
): AttributeFacetQuery {
    $enumerator = new SignatureCandidateEnumerator($registry);
    $existsClause = new AttributeExistsClause();

    return new AttributeFacetQuery(
        connection: $connection,
        attributeDefinitionRepository: $defRepo,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
        attributeExistsClause: $existsClause,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('only returns facetable attributes', function (): void {
    $registry = makeFacetQueryRegistry();
    $context = new ScopeContext($registry);
    $defRepo = new QueryableAttributeDefinitionRepository();

    makeFacetQueryDef($defRepo, 'color', facetable: true);
    makeFacetQueryDef($defRepo, 'internal_ref', facetable: false);

    $connection = new FakeConnection();
    // For each facetable attribute, the query returns empty rows
    $connection->queryResults = [
        [], // color facet query returns no rows
    ];

    $query = makeAttributeFacetQuery($connection, $defRepo, $registry, $context);

    $facets = $query->facets(1, new FilterSelection());

    // Only 'color' (facetable=true) should be queried and returned
    expect($facets)->toHaveCount(1);
    expect($facets[0]->code)->toBe('color');
});
