<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\ModuleLoader;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Data\ProductCardData;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function productGridBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-grid-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 3);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $viewConfig = new ViewConfig($config);
    $latteViewConfig = new LatteViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engine = (new LatteEngineFactory($viewConfig, $latteViewConfig))->create();

    // Register the template resolver loader
    $engine->setLoader(new ModuleLoader($templateResolver));

    return $engine;
}

function makeGridScopeContextStub(?string $locale): ScopeContext
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

    return new class ($locale, $registry) extends ScopeContext
    {
        public function __construct(
            private readonly ?string $activeLocale,
            ScopeRegistryInterface $registry,
        ) {
            parent::__construct($registry);
        }

        public function get(string $axis): ?string
        {
            if ($axis === 'locale') {
                return $this->activeLocale;
            }

            return null;
        }
    };
}

function makeGridMoneyFormatter(?string $locale = null): MoneyFormatter
{
    return new MoneyFormatter(makeGridScopeContextStub($locale));
}

function makeGridNoPricePriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

function productGridBuildComponent(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    ?PriceResolverInterface $priceResolver = null,
    ?MoneyFormatter $moneyFormatter = null,
): ProductGridComponent {
    $service = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    return new ProductGridComponent(
        $service,
        $priceResolver ?? makeGridNoPricePriceResolver(),
        $moneyFormatter ?? makeGridMoneyFormatter(),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'takes only CategoryAssignmentService in its constructor (no ScopeResolver, no direct repository)',
    function (): void {
        $reflection = new ReflectionClass(ProductGridComponent::class);
        $constructor = $reflection->getConstructor();
    
        expect($constructor)->not->toBeNull();
    
        $params = $constructor->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        $paramTypes = array_map(fn ($p) => $p->getType()?->getName(), $params);
    
        expect($paramNames)->toContain('categoryAssignmentService');
        expect($paramNames)->not->toContain('categoryRepository');
        expect($paramNames)->not->toContain('scopeResolver');
    
        expect($paramTypes)->toContain(CategoryAssignmentService::class);
        expect($paramTypes)->toContain(PriceResolverInterface::class);
        expect($paramTypes)->toContain(MoneyFormatter::class);
    }
);

it('has no Markommerce\\Scope imports in the ProductGridComponent class file', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Component/ProductGridComponent.php');

    expect($source)->not->toContain('Markommerce\\Scope');
});

it('returns a ProductGridData with the raw product name in resolvedNames keyed by product id', function (): void {
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

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    expect($data->resolvedNames[$product->id])->toBe('Running Shoes');
});

it(
    'returns a ProductGridData with the raw product description in resolvedDescs keyed by product id',
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
        $product->description = 'Great running shoes';
        $productRepository->save($product);
    
        $assignmentService = new CategoryAssignmentService(
            $productRepository,
            $categoryRepository,
            $assignmentRepository
        );
        $assignmentService->assign($product->id, $category->id);
    
        $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
        $data = $component->data($category);
    
        expect($data->resolvedDescs[$product->id])->toBe('Great running shoes');
    }
);

it('returns an empty products list when the category has no id', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Unsaved Category';
    // intentionally NOT saving — category has no id

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    expect($data->products)->toBeEmpty();
    expect($data->resolvedNames)->toBeEmpty();
    expect($data->resolvedDescs)->toBeEmpty();
});

it('skips products with null id when building the resolved maps', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Tech';
    $categoryRepository->save($category);

    $productWithId = new Product();
    $productWithId->sku = 'TECH-001';
    $productWithId->name = 'Gadget';
    $productRepository->save($productWithId);

    $nullIdProduct = new Product();
    $nullIdProduct->sku = 'NULL-001';
    $nullIdProduct->name = 'Ghost Product';
    // id is not set — remains null

    // Use an anonymous subclass of CategoryAssignmentService to inject a null-id product
    $assignmentService = new class ($productRepository, $categoryRepository, $assignmentRepository, $nullIdProduct) extends CategoryAssignmentService
    {
        public function __construct(
            ProductRepositoryInterface $productRepository,
            CategoryRepositoryInterface $categoryRepository,
            ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
            private Product $extraNullIdProduct,
        ) {
            parent::__construct($productRepository, $categoryRepository, $assignmentRepository);
        }

        /** @return list<Product> */
        public function productsInCategory(int $categoryId): array
        {
            $products = parent::productsInCategory($categoryId);
            $products[] = $this->extraNullIdProduct;

            return $products;
        }
    };

    $assignmentService->assign($productWithId->id, $category->id);

    $component = new ProductGridComponent(
        $assignmentService,
        makeGridNoPricePriceResolver(),
        makeGridMoneyFormatter()
    );
    $data = $component->data($category);

    // Only the product with a real id appears in the maps
    expect($data->resolvedNames)->toHaveKey($productWithId->id);
    expect($data->resolvedNames)->toHaveCount(1);
    expect($data->resolvedDescs)->toHaveKey($productWithId->id);
    expect($data->resolvedDescs)->toHaveCount(1);
    expect($data->products)->toHaveCount(2); // both products are in the list, but only one in maps
});

it('returns a typed ProductGridData DTO from data() for an existing category', function (): void {
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

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toHaveCount(1);
    expect($data->resolvedNames[$product->id])->toBe('Running Shoes');
});

it('returns an empty products list from data() when the category has no assigned products', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toBeEmpty();
    expect($data->resolvedNames)->toBeEmpty();
    expect($data->resolvedDescs)->toBeEmpty();
});

it('renders the category name as the page heading via mk-heading', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Featured Products';
    $categoryRepository->save($category);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('<mk-heading');
    expect($output)->toContain('Featured Products');
});

it('renders products inside an mk-grid element', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'ELEC-001';
    $product->name = 'Laptop';
    $productRepository->save($product);

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('<mk-grid');
});

it('renders a product grid container with a products slot placeholder when products exist', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Clothing';
    $categoryRepository->save($category);

    $product1 = new Product();
    $product1->sku = 'SHIRT-001';
    $product1->name = 'Blue T-Shirt';
    $productRepository->save($product1);

    $product2 = new Product();
    $product2->sku = 'PANTS-001';
    $product2->name = 'Black Jeans';
    $productRepository->save($product2);

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product1->id, $category->id);
    $assignmentService->assign($product2->id, $category->id);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('<mk-grid');
    expect($output)->toContain('{slot products}{/slot}');
});

it('renders the product card template with a placeholder image for the product SKU', function (): void {
    $product = new Product();
    $product->id = 42;
    $product->sku = 'BOOT-001';
    $product->name = 'Hiking Boot';

    $data = new ProductCardData(
        product: $product,
        resolvedName: 'Hiking Boot',
        resolvedDesc: '',
        inStock: true,
        extensions: new ExtensionBag(),
    );

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-card', [
        'product' => $data->product,
        'resolvedName' => $data->resolvedName,
        'resolvedDesc' => $data->resolvedDesc,
        'inStock' => $data->inStock,
        'formattedPrice' => $data->formattedPrice,
        'extensions' => $data->extensions,
    ]);

    expect($output)->toContain('placehold.co');
    expect($output)->toContain('BOOT-001');
});

it('renders a muted empty state when the category has no products', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $component = productGridBuildComponent($categoryRepository, $productRepository, $assignmentRepository);
    $data = $component->data($category);

    $engine = productGridBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('No products found');
    expect($output)->toContain('muted');
});

it('exposes formatted prices keyed by product id from the product grid', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $pricedProduct = new Product();
    $pricedProduct->sku = 'SHOE-001';
    $pricedProduct->name = 'Running Shoes';
    $pricedProduct->priceAmount = '49.99';
    $productRepository->save($pricedProduct);

    $unpricedProduct = new Product();
    $unpricedProduct->sku = 'GHOST-001';
    $unpricedProduct->name = 'No Price Item';
    $productRepository->save($unpricedProduct);

    $usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $pricedMoney = Money::of('49.99', $usd);

    $priceResolver = new class ($pricedProduct->id, $pricedMoney) implements PriceResolverInterface
    {
        public function __construct(
            private readonly int $pricedProductId,
            private readonly Money $money,
        ) {}

        public function resolve(PriceContext $context): Money
        {
            if ($context->product->id === $this->pricedProductId) {
                return $this->money;
            }

            throw PriceUnavailableException::forContext($context);
        }
    };

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($pricedProduct->id, $category->id);
    $assignmentService->assign($unpricedProduct->id, $category->id);

    $component = new ProductGridComponent($assignmentService, $priceResolver, makeGridMoneyFormatter('en_US'));
    $data = $component->data($category);

    expect($data->formattedPrices)->toHaveKey($pricedProduct->id)
        ->and($data->formattedPrices[$pricedProduct->id])->toContain('$')
        ->and($data->formattedPrices[$pricedProduct->id])->toContain('49.99')
        ->and($data->formattedPrices)->toHaveKey($unpricedProduct->id)
        ->and($data->formattedPrices[$unpricedProduct->id])->toBeNull();
});

it('renders the price for each card in the product grid', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'ELEC-001';
    $product->name = 'Laptop';
    $product->priceAmount = '999.99';
    $productRepository->save($product);

    $usd = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $money = Money::of('999.99', $usd);

    $priceResolver = new class ($money) implements PriceResolverInterface
    {
        public function __construct(private readonly Money $money) {}

        public function resolve(PriceContext $context): Money
        {
            return $this->money;
        }
    };

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $component = new ProductGridComponent($assignmentService, $priceResolver, makeGridMoneyFormatter('en_US'));
    $data = $component->data($category);

    expect($data->formattedPrices)->toHaveKey($product->id)
        ->and($data->formattedPrices[$product->id])->toContain('$')
        ->and($data->formattedPrices[$product->id])->toContain('999.99');
});
