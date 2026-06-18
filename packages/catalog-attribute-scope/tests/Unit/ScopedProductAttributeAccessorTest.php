<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\DecimalType;
use Markommerce\Attribute\Type\MultiselectType;
use Markommerce\Attribute\Type\SelectType;
use Markommerce\Attribute\Type\TextType;
use Markommerce\Attribute\Validation\AttributeValueValidator;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttribute\Definition\StaticAttributeProvider;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttribute\Tests\Support\FakeAttributeDefinitionRepository;
use Markommerce\CatalogAttributeScope\Entity\ProductScopedAttributeValues;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeSasRegistry(array $axes = ['store' => ['global', 'global.us']], array $defaults = []): ScopeRegistryInterface
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

function makeSasSetup(): array
{
    $registry = makeSasRegistry();
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
    $attrValidator = new AttributeValueValidator($attrRegistry);

    $repo = new FakeAttributeDefinitionRepository();
    $staticProvider = new StaticAttributeProvider();
    $definitions = new ProductAttributeDefinitions($repo, $staticProvider);
    $globalAccessor = new ProductAttributeAccessor($definitions, $attrValidator, $repo, $staticProvider);

    $accessor = new ScopedProductAttributeAccessor(
        productAttributeDefinitions: $definitions,
        attributeValueValidator: $attrValidator,
        attributeDefinitionRepository: $repo,
        productAttributeAccessor: $globalAccessor,
        scopeWalker: $walker,
        scopeContext: $context,
        scopeResolver: $resolver,
    );

    return [$accessor, $repo, $context, $scopedFieldRegistry, $registry];
}

function makeJsonDef(FakeAttributeDefinitionRepository $repo, string $code = 'color', string $type = 'text', bool $scopable = true, ?array $axes = null): AttributeDefinition
{
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = $type;
    $def->backing = 'Json';
    $def->scopable = $scopable;
    $def->config = $axes !== null ? ['axes' => $axes] : ['axes' => ['store']];
    $repo->save($def);

    return $def;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('sets and reads a scoped Json value for a product attribute by signature', function (): void {
    [$accessor, $repo] = makeSasSetup();
    makeJsonDef($repo, 'color');

    $product = new Product();
    $signature = new ScopeSignature(['store' => 'global.us']);

    $accessor->setScoped($product, 'color', 'red', $signature);
    $result = $accessor->getScoped($product, 'color', $signature);

    expect($result)->toBe('red');
});

it('resolves the scoped Json override under a matching active scope', function (): void {
    [$accessor, $repo, $context] = makeSasSetup();
    makeJsonDef($repo, 'color');

    $product = new Product();
    $signature = new ScopeSignature(['store' => 'global.us']);

    $accessor->setScoped($product, 'color', 'blue', $signature);

    $context->in('store', 'global.us');
    $result = $accessor->resolve($product, 'color');

    expect($result)->toBe('blue');
});

it('rejects setting a scoped value on a non-scopable attribute', function (): void {
    [$accessor, $repo] = makeSasSetup();
    makeJsonDef($repo, 'internal_code', 'text', false); // scopable = false

    $product = new Product();
    $signature = new ScopeSignature(['store' => 'global.us']);

    expect(fn () => $accessor->setScoped($product, 'internal_code', 'value', $signature))
        ->toThrow(ScopeContextException::class);
});

it('validates and casts a scoped value via the attribute validator before storing', function (): void {
    [$accessor, $repo] = makeSasSetup();
    makeJsonDef($repo, 'weight', 'decimal');

    $product = new Product();
    $signature = new ScopeSignature(['store' => 'global.us']);

    // Pass an integer — DecimalType casts it to string
    $accessor->setScoped($product, 'weight', 10, $signature);
    $result = $accessor->getScoped($product, 'weight', $signature);

    expect($result)->toBe('10');

    // Pass an invalid value — validator should throw
    expect(fn () => $accessor->setScoped($product, 'weight', 'not-a-number', $signature))
        ->toThrow(InvalidAttributeValueException::class);
});

it('resolves a Column-backed attribute via the generic scope resolver over the native property', function (): void {
    [$accessor, $repo, $context, $scopedFieldRegistry] = makeSasSetup();
    // 'name' is a Column-backed static attribute (property 'name' on Product)
    // We must register Product::name as scoped in ScopedFieldRegistry before resolution,
    // otherwise axes are empty and resolution falls back to the base column.
    $scopedFieldRegistry->register(Product::class, 'name', ['store']);

    $product = new Product();
    $product->name = 'base-name';

    // Attach a HasScopesInterface companion so the resolver can store/read overrides
    $companion = new ProductScopedAttributeValues();
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($companion);

    $context->in('store', 'global.us');
    $result = $accessor->resolve($product, 'name');

    expect($result)->toBe('US Name');
});

it(
    're-throws loudly when a Column-backed scoped write targets a property with no scoped-field registration',
    function (): void {
        [$accessor, $repo] = makeSasSetup();
        // 'name' is Column-backed; we do NOT register it in ScopedFieldRegistry
        // so the ScopeResolver will throw ScopeContextException

        $product = new Product();
        $signature = new ScopeSignature(['store' => 'global.us']);

        expect(fn () => $accessor->setScoped($product, 'name', 'US Name', $signature))
            ->toThrow(ScopeContextException::class);
    },
);

it('falls back to the global Phase-2 value when no scoped override matches', function (): void {
    [$accessor, $repo, $context] = makeSasSetup();
    makeJsonDef($repo, 'color');

    $product = new Product();
    // Set the global (Phase-2) value via the global accessor — no scoped override
    $companionValues = new ProductAttributeValues();
    $companionValues->set('color', 'green');
    $product->attachCompanion($companionValues);

    $context->in('store', 'global.us');
    $result = $accessor->resolve($product, 'color');

    expect($result)->toBe('green');
});
