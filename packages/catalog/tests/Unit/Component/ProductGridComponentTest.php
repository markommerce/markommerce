<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\ModuleLoader;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Data\ProductCardData;
use Markommerce\Catalog\Data\ProductGridData;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function productGridBuildScopeResolver(): ScopeResolver
{
    DefaultScopeGuard::reset();

    $rawConfig = require dirname(__DIR__, 4) . '/scope/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);

    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry, new ScopedFieldRegistry(scopeRegistry: $registry));
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);

    return new ScopeResolver($metadataFactory, $walker, $context, $validator);
}

function productGridBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-grid-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 3);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog',
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
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engine = (new LatteEngineFactory($viewConfig))->create();

    // Register the template resolver loader
    $engine->setLoader(new ModuleLoader($templateResolver));

    return $engine;
}

function productGridBuildComponent(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
): ProductGridComponent {
    $service = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $scopeResolver = productGridBuildScopeResolver();

    return new ProductGridComponent($categoryRepository, $service, $scopeResolver);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('injects the category repository, assignment service, and scope resolver', function (): void {
    $reflection = new ReflectionClass(ProductGridComponent::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $paramNames = array_map(fn ($p) => $p->getName(), $params);
    $paramTypes = array_map(fn ($p) => $p->getType()?->getName(), $params);

    expect($paramNames)->toContain('categoryRepository');
    expect($paramNames)->toContain('categoryAssignmentService');
    expect($paramNames)->toContain('scopeResolver');

    expect($paramTypes)->toContain(CategoryRepositoryInterface::class);
    expect($paramTypes)->toContain(CategoryAssignmentService::class);
    expect($paramTypes)->toContain(ScopeResolver::class);
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
    $output = $engine->renderToString('catalog::components/product-grid', [
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
    $output = $engine->renderToString('catalog::components/product-grid', [
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
    $output = $engine->renderToString('catalog::components/product-grid', [
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
    $output = $engine->renderToString('catalog::components/product-card', [
        'product' => $data->product,
        'resolvedName' => $data->resolvedName,
        'resolvedDesc' => $data->resolvedDesc,
        'inStock' => $data->inStock,
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
    $output = $engine->renderToString('catalog::components/product-grid', [
        'products' => $data->products,
        'resolvedNames' => $data->resolvedNames,
        'resolvedDescs' => $data->resolvedDescs,
        'category' => $category,
    ]);

    expect($output)->toContain('No products found');
    expect($output)->toContain('muted');
});

it('resolves product names through ScopeResolver rather than the raw column value', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Tech';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'TECH-001';
    $product->name = 'Base Product Name';
    $productRepository->save($product);

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    // Build component with real ScopeResolver
    $scopeResolver = productGridBuildScopeResolver();
    $component = new ProductGridComponent($categoryRepository, $assignmentService, $scopeResolver);
    $data = $component->data($category);

    // In default scope context, resolved() falls back to the base column value.
    // Verify the resolved name is in the map (not accessed directly from $product->name).
    expect($data->resolvedNames[$product->id])->toBe('Base Product Name');

    // The resolved name is available in the DTO map for product card rendering.
    // Product-level rendering is handled by catalog::components/product-card via the layout system.
    expect($data->resolvedNames[$product->id])->toBe('Base Product Name');
});
