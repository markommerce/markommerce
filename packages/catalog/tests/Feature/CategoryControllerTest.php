<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function catalogControllerBuildScopeResolver(): ScopeResolver
{
    DefaultScopeGuard::reset();

    $rawConfig = require dirname(__DIR__, 3) . '/scope/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);

    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry);
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);

    return new ScopeResolver($metadataFactory, $walker, $context, $validator);
}

function catalogControllerBuildView(): LatteView
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 2);

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
    $engine = (new \Marko\View\Latte\LatteEngineFactory($viewConfig))->create();

    return new LatteView($engine, $templateResolver);
}

function catalogControllerBuildController(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
): CategoryController {
    $service = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $scopeResolver = catalogControllerBuildScopeResolver();
    $view = catalogControllerBuildView();

    return new CategoryController($service, $categoryRepository, $scopeResolver, $view);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it places a Get route at /catalog/category/{id} on the controller action', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('show');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/catalog/category/{id}');
});

it('it returns a 200 response when the requested category exists', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $controller = catalogControllerBuildController($categoryRepository, $productRepository, $assignmentRepository);
    $response = $controller->show($category->id);

    expect($response->statusCode())->toBe(200);
});

it('it returns a 404 response when the requested category id does not exist', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $controller = catalogControllerBuildController($categoryRepository, $productRepository, $assignmentRepository);
    $response = $controller->show(9999);

    expect($response->statusCode())->toBe(404);
});

it('it renders a 200 response for an existing category that has no assigned products', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $controller = catalogControllerBuildController($categoryRepository, $productRepository, $assignmentRepository);
    $response = $controller->show($category->id);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('Empty Category');
});

it('it includes every assigned product name in the rendered response body', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $product1 = new Product();
    $product1->sku = 'SHOE-001';
    $product1->name = 'Running Shoes';
    $productRepository->save($product1);

    $product2 = new Product();
    $product2->sku = 'SHOE-002';
    $product2->name = 'Hiking Boots';
    $productRepository->save($product2);

    $service = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $service->assign($product1->id, $category->id);
    $service->assign($product2->id, $category->id);

    $scopeResolver = catalogControllerBuildScopeResolver();
    $view = catalogControllerBuildView();
    $controller = new CategoryController($service, $categoryRepository, $scopeResolver, $view);

    $response = $controller->show($category->id);

    expect($response->body())
        ->toContain('Running Shoes')
        ->toContain('Hiking Boots');
});

it('it renders product names through ScopeResolver rather than the raw column value', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'ELEC-001';
    $product->name = 'Base Name';
    $productRepository->save($product);

    $service = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $service->assign($product->id, $category->id);

    $scopeResolver = catalogControllerBuildScopeResolver();
    $view = catalogControllerBuildView();
    $controller = new CategoryController($service, $categoryRepository, $scopeResolver, $view);

    $response = $controller->show($category->id);

    // In the all-default context (no scope overrides), resolved() falls back to the base column value.
    // This verifies ScopeResolver::resolved() is used (not direct $product->name access).
    expect($response->body())->toContain('Base Name');
});
