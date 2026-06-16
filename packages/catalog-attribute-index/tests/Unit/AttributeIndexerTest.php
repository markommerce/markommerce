<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\BoolType;
use Markommerce\Attribute\Type\DecimalType;
use Markommerce\Attribute\Type\IntType;
use Markommerce\Attribute\Type\MultiselectType;
use Markommerce\Attribute\Type\SelectType;
use Markommerce\Attribute\Type\TextType;
use Markommerce\Attribute\Validation\AttributeValueValidator;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttribute\Definition\StaticAttributeProvider;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeScope\Entity\ProductScopedAttributeValues;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Indexer\ServedScopes\ServedScopesProviderInterface;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class AttributeIndexerFakeQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<int> */
    private array $filteredIds = [];

    /**
     * @param array<int, Product> $products
     */
    public function __construct(
        private readonly array $products,
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
        $ids = $this->filteredIds;
        $filtered = array_values(array_filter(
            $this->products,
            fn (Product $p): bool => in_array($p->id, $ids, strict: true),
        ));

        return new EntityCollection($filtered);
    }
}

class AttributeIndexerFakeProductRepository implements ProductRepositoryInterface
{
    /** @param array<int, Product> $products */
    public function __construct(private readonly array $products) {}

    public function query(): AttributeIndexerFakeQueryBuilder
    {
        return new AttributeIndexerFakeQueryBuilder($this->products);
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

class AttributeIndexerFakeIndexRepository
{
    /** @var list<ProductAttributeIndexEntry> */
    public array $replacedRows = [];

    /** @var list<int> */
    public array $replacedIds = [];

    /**
     * @param list<int> $productIds
     * @param list<ProductAttributeIndexEntry> $rows
     */
    public function replaceForProducts(
        array $productIds,
        array $rows,
    ): void
    {
        $this->replacedIds = array_merge($this->replacedIds, $productIds);
        $this->replacedRows = array_merge($this->replacedRows, $rows);
    }
}

/**
 * Spy wrapper around ProductAttributeIndexRepository that only exposes what we need.
 */
class SpyProductAttributeIndexRepository extends ProductAttributeIndexRepository
{
    /** @var list<ProductAttributeIndexEntry> */
    public array $replacedRows = [];

    /** @var list<int> */
    public array $replacedIds = [];

    public function __construct()
    {
        // Skip parent constructor — we intercept the method directly.
    }

    /**
     * @param list<int> $productIds
     * @param list<ProductAttributeIndexEntry> $rows
     */
    public function replaceForProducts(
        array $productIds,
        array $rows,
    ): void
    {
        $this->replacedIds = array_merge($this->replacedIds, $productIds);
        $this->replacedRows = array_merge($this->replacedRows, $rows);
    }
}

/**
 * @param array<string, list<string>> $axesMap  axis => all paths (first is treated as default)
 * @param array<string, string>       $defaults  axis => default path (optional)
 */
function makeAttributeIndexerRegistry(
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

/**
 * Fake ServedScopesProvider that returns a fixed list of signatures for given axes.
 */
class FakeServedScopesProvider implements ServedScopesProviderInterface
{
    /**
     * @param array<string, list<ScopeSignature>> $signaturesMap  serialized axes key => signatures
     */
    public function __construct(private readonly array $signaturesMap = []) {}

    /**
     * @param list<string> $axes
     * @return list<ScopeSignature>
     */
    public function signatures(array $axes): array
    {
        sort($axes);
        $key = implode(',', $axes);

        return $this->signaturesMap[$key] ?? [];
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap
 * @param array<string, string>       $defaults
 */
function makeAttributeIndexerSetup(
    array $axesMap = ['store' => ['global', 'global.us']],
    array $defaults = ['store' => 'global'],
): array {
    $registry = makeAttributeIndexerRegistry($axesMap, $defaults);
    $context = new ScopeContext($registry);
    $scopedFieldRegistry = new ScopedFieldRegistry(scopeRegistry: $registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry, $scopedFieldRegistry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    $attrRegistry = new AttributeTypeRegistry();
    $attrRegistry->register(new TextType());
    $attrRegistry->register(new DecimalType());
    $attrRegistry->register(new SelectType());
    $attrRegistry->register(new MultiselectType());
    $attrRegistry->register(new BoolType());
    $attrRegistry->register(new IntType());
    $attrValidator = new AttributeValueValidator($attrRegistry);

    $defRepo = new QueryableAttributeDefinitionRepository();
    $staticProvider = new StaticAttributeProvider();
    $definitions = new ProductAttributeDefinitions($defRepo, $staticProvider);
    $globalAccessor = new ProductAttributeAccessor($definitions, $attrValidator, $defRepo, $staticProvider);

    $scopedAccessor = new ScopedProductAttributeAccessor(
        productAttributeDefinitions: $definitions,
        attributeValueValidator: $attrValidator,
        attributeDefinitionRepository: $defRepo,
        productAttributeAccessor: $globalAccessor,
        scopeWalker: $walker,
        scopeContext: $context,
        scopeResolver: $resolver,
    );

    $runner = new ScopePassRunner($context);

    return [$defRepo, $scopedAccessor, $runner, $context, $registry];
}

function makeJsonAttrDef(
    QueryableAttributeDefinitionRepository $repo,
    string $code,
    string $type = 'text',
    bool $filterable = false,
    bool $facetable = false,
    bool $scopable = false,
    array $axes = [],
): AttributeDefinition {
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = $type;
    $def->backing = 'Json';
    $def->filterable = $filterable;
    $def->facetable = $facetable;
    $def->scopable = $scopable;
    $def->config = ['axes' => $axes];
    $repo->save($def);

    return $def;
}

function makeProductWithValues(int $id, array $values): Product
{
    $p = new Product();
    $p->id = $id;
    $companion = new ProductAttributeValues();

    foreach ($values as $code => $value) {
        $companion->set($code, $value);
    }

    $p->attachCompanion($companion);

    return $p;
}

function makeProductWithScopedValues(int $id, array $baseValues, array $scopedOverrides): Product
{
    $p = makeProductWithValues($id, $baseValues);
    $companion = new ProductScopedAttributeValues();

    foreach ($scopedOverrides as $sig => $codeValues) {
        foreach ($codeValues as $code => $value) {
            $companion->setOverride($sig, $code, $value);
        }
    }

    $p->attachCompanion($companion);

    return $p;
}

function buildAttributeIndexer(
    AttributeIndexerFakeProductRepository $productRepo,
    QueryableAttributeDefinitionRepository $defRepo,
    ScopedProductAttributeAccessor $scopedAccessor,
    ScopePassRunner $runner,
    ServedScopesProviderInterface $servedScopes,
    SpyProductAttributeIndexRepository $indexRepo,
): AttributeIndexer {
    return new AttributeIndexer(
        productRepository: $productRepo,
        attributeDefinitionRepository: $defRepo,
        scopedProductAttributeAccessor: $scopedAccessor,
        scopePassRunner: $runner,
        servedScopesProvider: $servedScopes,
        productAttributeIndexRepository: $indexRepo,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('indexes only filterable or facetable attributes', function (): void {
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup();

    // filterable = true: should be indexed
    makeJsonAttrDef($defRepo, 'color', 'text', filterable: true);
    // facetable = true: should be indexed
    makeJsonAttrDef($defRepo, 'size', 'text', facetable: true);
    // neither filterable nor facetable: should NOT be indexed
    makeJsonAttrDef($defRepo, 'internal_code', 'text', filterable: false, facetable: false);

    $p1 = makeProductWithValues(1, ['color' => 'red', 'size' => 'M', 'internal_code' => 'XYZ']);
    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();
    $servedScopes = new FakeServedScopesProvider();

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);
    $indexer->reindex([1]);

    $codes = array_map(fn (ProductAttributeIndexEntry $e) => $e->attributeCode, $indexRepo->replacedRows);

    expect($codes)->toContain('color')
        ->and($codes)->toContain('size')
        ->and($codes)->not->toContain('internal_code');
});

it('writes a base-signature row for each indexed attribute value', function (): void {
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup();

    makeJsonAttrDef($defRepo, 'color', 'text', filterable: true);

    $p1 = makeProductWithValues(1, ['color' => 'red']);
    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();
    $servedScopes = new FakeServedScopesProvider();

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);
    $indexer->reindex([1]);

    expect($indexRepo->replacedRows)->toHaveCount(1);

    $row = $indexRepo->replacedRows[0];
    expect($row->productId)->toBe(1)
        ->and($row->attributeCode)->toBe('color')
        ->and($row->scopeSignature)->toBe('')
        ->and($row->valueText)->toBe('red')
        ->and($row->valueKind)->toBe('text');
});

it('writes a per-signature row with the scope-resolved value for a scopable attribute', function (): void {
    // Set up: store axis with two scopes: global (default) and global.us
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup(
        axesMap: ['store' => ['global', 'global.us']],
        defaults: ['store' => 'global'],
    );

    // A scopable color attribute covering the 'store' axis
    makeJsonAttrDef($defRepo, 'color', 'text', filterable: true, scopable: true, axes: ['store']);

    // Product with base value 'red' and scoped override 'blue' for store:global.us
    $p1 = makeProductWithScopedValues(
        1,
        baseValues: ['color' => 'red'],
        scopedOverrides: ['store:global.us' => ['color' => 'blue']],
    );

    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();

    // Provide the scoped signature for store axis
    $servedScopes = new FakeServedScopesProvider([
        'store' => [new ScopeSignature(['store' => 'global.us'])],
    ]);

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);
    $indexer->reindex([1]);

    // Should have: 1 base row (scope='') + 1 scoped row (scope='store:global.us')
    expect($indexRepo->replacedRows)->toHaveCount(2);

    $bySignature = [];

    foreach ($indexRepo->replacedRows as $row) {
        $bySignature[$row->scopeSignature] = $row;
    }

    expect($bySignature)->toHaveKey('')
        ->and($bySignature['']->valueText)->toBe('red');

    expect($bySignature)->toHaveKey('store:global.us')
        ->and($bySignature['store:global.us']->valueText)->toBe('blue')
        ->and($bySignature['store:global.us']->attributeCode)->toBe('color');
});

it('indexes a non-scopable attribute once under the base signature', function (): void {
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup(
        axesMap: ['store' => ['global', 'global.us']],
        defaults: ['store' => 'global'],
    );

    // Non-scopable filterable attribute
    makeJsonAttrDef($defRepo, 'material', 'text', filterable: true, scopable: false);

    $p1 = makeProductWithValues(1, ['material' => 'cotton']);

    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();

    // Even if servedScopes would return signatures for store, non-scopable gets none
    $servedScopes = new FakeServedScopesProvider([
        'store' => [new ScopeSignature(['store' => 'global.us'])],
    ]);

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);
    $indexer->reindex([1]);

    // Only one row: the base row with scope=''
    expect($indexRepo->replacedRows)->toHaveCount(1);
    expect($indexRepo->replacedRows[0]->scopeSignature)->toBe('');
    expect($indexRepo->replacedRows[0]->valueText)->toBe('cotton');
});

it('emits one row per member for a multiselect value', function (): void {
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup();

    makeJsonAttrDef($defRepo, 'tags', 'multiselect', filterable: true);

    // Multiselect value as an array of members
    $p1 = makeProductWithValues(1, ['tags' => ['sale', 'new', 'featured']]);

    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();
    $servedScopes = new FakeServedScopesProvider();

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);
    $indexer->reindex([1]);

    // One row per member
    expect($indexRepo->replacedRows)->toHaveCount(3);

    $values = array_map(fn (ProductAttributeIndexEntry $e) => $e->valueText, $indexRepo->replacedRows);
    expect($values)->toContain('sale')
        ->and($values)->toContain('new')
        ->and($values)->toContain('featured');

    // All rows share the same product_id, attribute_code, scope and kind
    foreach ($indexRepo->replacedRows as $row) {
        expect($row->productId)->toBe(1)
            ->and($row->attributeCode)->toBe('tags')
            ->and($row->scopeSignature)->toBe('')
            ->and($row->valueKind)->toBe('multiselect');
    }
});

it('omits a row when the resolved value is null', function (): void {
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup();

    // One attribute with a value, one without (null)
    makeJsonAttrDef($defRepo, 'color', 'text', filterable: true);
    makeJsonAttrDef($defRepo, 'material', 'text', filterable: true);

    // Product has color set but NOT material
    $p1 = makeProductWithValues(1, ['color' => 'red']);

    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();
    $servedScopes = new FakeServedScopesProvider();

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);
    $indexer->reindex([1]);

    // Only the 'color' row should exist; 'material' is null → omitted
    expect($indexRepo->replacedRows)->toHaveCount(1);
    expect($indexRepo->replacedRows[0]->attributeCode)->toBe('color');
});

it('replaces a product\'s existing index rows on reindex', function (): void {
    [$defRepo, $scopedAccessor, $runner] = makeAttributeIndexerSetup();

    makeJsonAttrDef($defRepo, 'color', 'text', filterable: true);

    $p1 = makeProductWithValues(1, ['color' => 'red']);

    $productRepo = new AttributeIndexerFakeProductRepository([1 => $p1]);
    $indexRepo = new SpyProductAttributeIndexRepository();
    $servedScopes = new FakeServedScopesProvider();

    $indexer = buildAttributeIndexer($productRepo, $defRepo, $scopedAccessor, $runner, $servedScopes, $indexRepo);

    // First reindex
    $indexer->reindex([1]);
    expect($indexRepo->replacedIds)->toContain(1);

    // Reset spy state and reindex again (simulating an update)
    $indexRepo->replacedIds = [];
    $indexRepo->replacedRows = [];

    $indexer->reindex([1]);

    // replaceForProducts should be called again with product id=1
    expect($indexRepo->replacedIds)->toContain(1);
    expect($indexRepo->replacedRows)->toHaveCount(1);
    expect($indexRepo->replacedRows[0]->valueText)->toBe('red');
});
