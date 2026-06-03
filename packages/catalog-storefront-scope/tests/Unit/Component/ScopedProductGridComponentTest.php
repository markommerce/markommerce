<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefrontScope\Component\ScopedProductGridComponent;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Price helpers ────────────────────────────────────────────────────────────

function scopedGridMakeMoneyFormatter(): MoneyFormatter
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

    $scopeContext = new ScopeContext($registry);

    return new MoneyFormatter($scopeContext);
}

function scopedGridMakeNoPriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

// ─── Scope helpers ────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function scopedGridMakeRegistry(
    array $axes = ['locale' => ['global', 'global.de', 'global.fr']],
    array $defaults = ['locale' => 'global'],
): ScopeRegistryInterface {
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(
            array $axes,
            array $defaults = [],
        )
        {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? $paths[0];
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

function scopedGridMakeResolver(?ScopeRegistryInterface $registry = null): array
{
    $registry ??= scopedGridMakeRegistry();
    $fieldRegistry = new ScopedFieldRegistry(scopeRegistry: $registry);

    // Register Product::$name and Product::$description as locale-scoped
    $fieldRegistry->register(Product::class, 'name', ['locale']);
    $fieldRegistry->register(Product::class, 'description', ['locale']);

    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry, $fieldRegistry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    return [$resolver, $context, $registry];
}

function scopedGridBuildComponent(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    ?ScopeResolver $scopeResolver = null,
): ScopedProductGridComponent {
    if ($scopeResolver === null) {
        [$scopeResolver] = scopedGridMakeResolver();
    }

    $service = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    return new ScopedProductGridComponent(
        categoryAssignmentService: $service,
        scopeResolver: $scopeResolver,
        priceResolver: scopedGridMakeNoPriceResolver(),
        moneyFormatter: scopedGridMakeMoneyFormatter(),
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('carries the #[Preference(replaces: ProductGridComponent::class)] attribute', function (): void {
    $reflection = new ReflectionClass(ScopedProductGridComponent::class);
    $attributes = $reflection->getAttributes(Preference::class);

    expect($attributes)->toHaveCount(1);

    $preference = $attributes[0]->newInstance();

    expect($preference->replaces)->toBe(ProductGridComponent::class);
});

it('extends Markommerce\\CatalogStorefront\\Component\\ProductGridComponent', function (): void {
    expect(ScopedProductGridComponent::class)
        ->toExtend(ProductGridComponent::class);
});

it('accepts CategoryAssignmentService and ScopeResolver in its constructor (no direct repository)', function (): void {
    $reflection = new ReflectionClass(ScopedProductGridComponent::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $paramNames = array_map(fn ($p) => $p->getName(), $params);

    expect($paramNames)->not->toContain('categoryRepository');
    expect($paramNames)->toContain('categoryAssignmentService');
    expect($paramNames)->toContain('scopeResolver');
    expect($paramNames)->toContain('priceResolver');
    expect($paramNames)->toContain('moneyFormatter');
    expect($params)->toHaveCount(4);
});

it(
    'returns a ProductGridData populated by parent::data() then overwrites resolvedNames with values from ScopeResolver::resolved',
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
        $productRepository->save($product);
    
        $service = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
        $service->assign($product->id, $category->id);
    
        [$resolver, $context] = scopedGridMakeResolver();
        $context->in('locale', 'global.de');
    
        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('locale:global.de', 'name', 'Laufschuhe');
        $product->attachCompanion($overrides);
    
        $component = scopedGridBuildComponent(
            $categoryRepository,
            $productRepository,
            $assignmentRepository,
            $resolver
        );
        $data = $component->data($category);
    
        expect($data->resolvedNames[$product->id])->toBe('Laufschuhe');
    }
);

it('returns a ProductGridData with resolvedDescs overwritten from ScopeResolver::resolved', function (): void {
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

    $service = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $service->assign($product->id, $category->id);

    [$resolver, $context] = scopedGridMakeResolver();
    $context->in('locale', 'global.de');

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('locale:global.de', 'description', 'Tolle Laufschuhe');
    $product->attachCompanion($overrides);

    $component = scopedGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository, $resolver);
    $data = $component->data($category);

    expect($data->resolvedDescs[$product->id])->toBe('Tolle Laufschuhe');
});

it(
    'falls back to the raw product name when ScopeResolver::resolved returns the raw value (no override set)',
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
        $productRepository->save($product);
    
        $service = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
        $service->assign($product->id, $category->id);
    
        // No companion attached, no override set — resolver falls back to raw value
    [$resolver, $context] = scopedGridMakeResolver();
        $context->in('locale', 'global.de');
    
        $component = scopedGridBuildComponent(
            $categoryRepository,
            $productRepository,
            $assignmentRepository,
            $resolver
        );
        $data = $component->data($category);
    
        expect($data->resolvedNames[$product->id])->toBe('Running Shoes');
    }
);

it(
    'returns a scoped value for resolvedNames when a locale override is set on the ProductScopedOverrides companion and the active locale context matches',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();
    
        $category = new Category();
        $category->name = 'Chaussures';
        $categoryRepository->save($category);
    
        $product = new Product();
        $product->sku = 'SHOE-002';
        $product->name = 'Running Shoes';
        $productRepository->save($product);
    
        $service = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
        $service->assign($product->id, $category->id);
    
        [$resolver, $context] = scopedGridMakeResolver();
        $context->in('locale', 'global.fr');
    
        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('locale:global.fr', 'name', 'Chaussures de course');
        $product->attachCompanion($overrides);
    
        $component = scopedGridBuildComponent(
            $categoryRepository,
            $productRepository,
            $assignmentRepository,
            $resolver
        );
        $data = $component->data($category);
    
        expect($data->resolvedNames[$product->id])->toBe('Chaussures de course');
    }
);

it(
    'is resolved by the container as the preferred binding for ProductGridComponent when catalog-storefront-scope is installed (verifies #[Preference] discovery)',
    function (): void {
        $manifest = new ModuleManifest(
            name: 'markommerce/catalog-storefront-scope',
            version: '1.0.0',
            path: dirname(__DIR__, 3),
        );
    
        $discovery = new PreferenceDiscovery();
        $records = $discovery->discoverInModule($manifest);
    
        $scopedGridRecord = array_find(
            $records,
            fn ($r) => $r->replaces === ProductGridComponent::class,
        );
    
        expect($scopedGridRecord)->not->toBeNull();
        expect($scopedGridRecord->replacement)->toBe(ScopedProductGridComponent::class);
    
        $registry = new PreferenceRegistry();
        $registry->register(
            original: $scopedGridRecord->replaces,
            replacement: $scopedGridRecord->replacement,
        );
    
        $container = new Container($registry);
    
        // Bind services required to wire ScopedProductGridComponent
    $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();
        [$scopeResolver] = scopedGridMakeResolver();
    
        $container->bind(
            CategoryAssignmentService::class,
            fn () => new CategoryAssignmentService(
                $productRepository,
                $categoryRepository,
                $assignmentRepository,
            ),
        );
        $container->bind(
            ScopeResolver::class,
            fn () => $scopeResolver,
        );
        $container->bind(
            PriceResolverInterface::class,
            fn () => scopedGridMakeNoPriceResolver(),
        );
        $container->bind(
            MoneyFormatter::class,
            fn () => scopedGridMakeMoneyFormatter(),
        );
    
        $instance = $container->get(ProductGridComponent::class);
    
        expect($instance)->toBeInstanceOf(ScopedProductGridComponent::class);
    }
);
