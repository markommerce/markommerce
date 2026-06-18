<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;
use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeIarRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(
            array $axes,
            array $defaults = [],
        ) {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? '__test_default';
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
            return $this->builtAxes[$name];
        }

        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->builtAxes[$axisName]->hierarchy;
        }
    };
}

function makeIarDef(string $code, string $type, array $axes = ['store']): AttributeDefinition
{
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = $type;
    $def->backing = 'Json';
    $def->scopable = true;
    $def->config = ['axes' => $axes];

    return $def;
}

function makeIarEntry(
    int $productId,
    string $code,
    string $signature,
    string $kind,
    ?string $valueText = null,
    ?string $valueNumber = null,
    ?bool $valueBool = null,
): ProductAttributeIndexEntry {
    $entry = new ProductAttributeIndexEntry();
    $entry->productId = $productId;
    $entry->attributeCode = $code;
    $entry->scopeSignature = $signature;
    $entry->valueKind = $kind;
    $entry->valueText = $valueText;
    $entry->valueNumber = $valueNumber;
    $entry->valueBool = $valueBool;

    return $entry;
}

/**
 * @param array<string, list<ProductAttributeIndexEntry>> $indexData key = "productId|code|signature"
 */
function makeFakeIndexRepo(array $indexData = []): ProductAttributeIndexRepository
{
    return new class ($indexData) extends ProductAttributeIndexRepository
    {
        /** @param array<string, list<ProductAttributeIndexEntry>> $indexData */
        public function __construct(private array $indexData) {}

        public function findValues(
            int $productId,
            string $code,
            string $signature,
        ): array {
            $key = "$productId|$code|$signature";

            return $this->indexData[$key] ?? [];
        }
    };
}

function makeFakeScopedAccessor(mixed $returnValue): ScopedProductAttributeAccessor
{
    return new class ($returnValue) extends ScopedProductAttributeAccessor
    {
        public function __construct(private mixed $returnValue) {}

        public function resolve(
            Product $product,
            string $code,
        ): mixed {
            return $this->returnValue;
        }
    };
}

function makeFakeDefinitions(?AttributeDefinition $definition): ProductAttributeDefinitions
{
    return new class ($definition) extends ProductAttributeDefinitions
    {
        public function __construct(private ?AttributeDefinition $definition) {}

        public function findByCode(string $code): ?AttributeDefinition
        {
            return $this->definition;
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the indexed value when an index row exists for a candidate signature', function (): void {
    $registry = makeIarRegistry(['store' => ['global', 'global.us']], ['store' => 'global']);
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us');

    $def = makeIarDef('color', 'text', ['store']);
    $definitions = makeFakeDefinitions($def);

    $entry = makeIarEntry(42, 'color', 'store:global.us', 'text', 'red');
    $indexRepo = makeFakeIndexRepo(['42|color|store:global.us' => [$entry]]);

    $liveAccessor = makeFakeScopedAccessor('live-fallback');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: $definitions,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );

    $product = new Product();
    $product->id = 42;

    $result = $reader->resolve($product, 'color');

    expect($result)->toBe('red');
});

it('walks candidate signatures in resolution order and returns the first matching row', function (): void {
    // Hierarchy: global (default) -> global.us -> global.us.ny
    // Context is at global.us.ny, so candidates order is:
    // store:global.us.ny, store:global.us (deepest first)
    $registry = makeIarRegistry(
        ['store' => ['global', 'global.us', 'global.us.ny']],
        ['store' => 'global'],
    );
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us.ny');

    $def = makeIarDef('color', 'text', ['store']);
    $definitions = makeFakeDefinitions($def);

    // Only global.us has a row — global.us.ny does NOT
    $entry = makeIarEntry(10, 'color', 'store:global.us', 'text', 'blue');
    $indexRepo = makeFakeIndexRepo(['10|color|store:global.us' => [$entry]]);

    $liveAccessor = makeFakeScopedAccessor('live-fallback');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: $definitions,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );

    $product = new Product();
    $product->id = 10;

    $result = $reader->resolve($product, 'color');

    // Should return global.us row (first candidate with rows), not live fallback
    expect($result)->toBe('blue');
});

it('falls back to the base empty signature row when no scoped candidate matches', function (): void {
    $registry = makeIarRegistry(['store' => ['global', 'global.us']], ['store' => 'global']);
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us');

    $def = makeIarDef('color', 'text', ['store']);
    $definitions = makeFakeDefinitions($def);

    // No scoped rows, but base (empty signature) has a row
    $baseEntry = makeIarEntry(5, 'color', '', 'text', 'green');
    $indexRepo = makeFakeIndexRepo(['5|color|' => [$baseEntry]]);

    $liveAccessor = makeFakeScopedAccessor('live-fallback');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: $definitions,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );

    $product = new Product();
    $product->id = 5;

    $result = $reader->resolve($product, 'color');

    expect($result)->toBe('green');
});

it('reconstructs the typed value from value_kind and the typed columns', function (): void {
    $registry = makeIarRegistry(['store' => ['global', 'global.us']], ['store' => 'global']);
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $liveAccessor = makeFakeScopedAccessor('live-fallback');

    // Test decimal → returns value_number
    $decimalDef = makeIarDef('weight', 'decimal', ['store']);
    $decimalEntry = makeIarEntry(1, 'weight', 'store:global.us', 'decimal', null, '12.5000');
    $indexRepo = makeFakeIndexRepo(['1|weight|store:global.us' => [$decimalEntry]]);
    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: makeFakeDefinitions($decimalDef),
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );
    $product = new Product();
    $product->id = 1;
    expect($reader->resolve($product, 'weight'))->toBe('12.5000');

    // Test bool → returns value_bool
    $boolDef = makeIarDef('in_stock', 'bool', ['store']);
    $boolEntry = makeIarEntry(2, 'in_stock', 'store:global.us', 'bool', null, null, true);
    $indexRepo2 = makeFakeIndexRepo(['2|in_stock|store:global.us' => [$boolEntry]]);
    $reader2 = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo2,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: makeFakeDefinitions($boolDef),
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );
    $product2 = new Product();
    $product2->id = 2;
    expect($reader2->resolve($product2, 'in_stock'))->toBeTrue();

    // Test text → returns value_text
    $textDef = makeIarDef('description', 'text', ['store']);
    $textEntry = makeIarEntry(3, 'description', 'store:global.us', 'text', 'A fine product');
    $indexRepo3 = makeFakeIndexRepo(['3|description|store:global.us' => [$textEntry]]);
    $reader3 = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo3,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: makeFakeDefinitions($textDef),
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );
    $product3 = new Product();
    $product3->id = 3;
    expect($reader3->resolve($product3, 'description'))->toBe('A fine product');
});

it('collects multiselect member rows into an array value', function (): void {
    $registry = makeIarRegistry(['store' => ['global', 'global.us']], ['store' => 'global']);
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us');

    $def = makeIarDef('tags', 'multiselect', ['store']);
    $definitions = makeFakeDefinitions($def);

    $row1 = makeIarEntry(7, 'tags', 'store:global.us', 'multiselect', 'electronics');
    $row2 = makeIarEntry(7, 'tags', 'store:global.us', 'multiselect', 'sale');
    $row3 = makeIarEntry(7, 'tags', 'store:global.us', 'multiselect', 'featured');
    $indexRepo = makeFakeIndexRepo(['7|tags|store:global.us' => [$row1, $row2, $row3]]);

    $liveAccessor = makeFakeScopedAccessor('live-fallback');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: $definitions,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );

    $product = new Product();
    $product->id = 7;

    $result = $reader->resolve($product, 'tags');

    expect($result)->toBe(['electronics', 'sale', 'featured']);
});

it('falls back to the live scoped accessor when no index row exists for any candidate', function (): void {
    $registry = makeIarRegistry(['store' => ['global', 'global.us']], ['store' => 'global']);
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us');

    $def = makeIarDef('color', 'text', ['store']);
    $definitions = makeFakeDefinitions($def);

    // No index rows for any signature — empty index
    $indexRepo = makeFakeIndexRepo([]);

    $liveAccessor = makeFakeScopedAccessor('live-value');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: $definitions,
        signatureCandidateEnumerator: $enumerator,
        scopeContext: $context,
    );

    $product = new Product();
    $product->id = 99;

    $result = $reader->resolve($product, 'color');

    expect($result)->toBe('live-value');
});

it('enumerates candidate signatures via SignatureCandidateEnumerator from the scope context', function (): void {
    // Use a spy enumerator to verify it is called with the correct axes and context
    $registry = makeIarRegistry(
        ['store' => ['global', 'global.us'], 'market' => ['eu', 'eu.de']],
        ['store' => 'global', 'market' => 'eu'],
    );
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us')->in('market', 'eu.de');

    // Attribute has two axes
    $def = makeIarDef('price', 'decimal', ['store', 'market']);
    $definitions = makeFakeDefinitions($def);

    $capturedAxes = null;
    $capturedContext = null;

    $spyEnumerator = new class ($registry, $capturedAxes, $capturedContext) extends SignatureCandidateEnumerator
    {
        public function __construct(
            ScopeRegistryInterface $registry,
            public ?array &$capturedAxes,
            public ?ScopeContext &$capturedContext,
        ) {
            parent::__construct($registry);
        }

        public function enumerate(
            array $attributeAxes,
            ScopeContext $context,
        ): array {
            $this->capturedAxes = $attributeAxes;
            $this->capturedContext = $context;

            return parent::enumerate($attributeAxes, $context);
        }
    };

    // No index rows to force live fallback (not relevant to this test's assertion)
    $indexRepo = makeFakeIndexRepo([]);
    $liveAccessor = makeFakeScopedAccessor('live-value');

    $reader = new IndexedAttributeReader(
        productAttributeIndexRepository: $indexRepo,
        scopedProductAttributeAccessor: $liveAccessor,
        productAttributeDefinitions: $definitions,
        signatureCandidateEnumerator: $spyEnumerator,
        scopeContext: $context,
    );

    $product = new Product();
    $product->id = 1;

    $reader->resolve($product, 'price');

    expect($capturedAxes)->toBe(['store', 'market'])
        ->and($capturedContext)->toBe($context);
});
