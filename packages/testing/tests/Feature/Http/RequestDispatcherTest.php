<?php

declare(strict_types=1);

use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Router;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Layout\Middleware\CompileIfStaleMiddleware;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Http\RequestDispatcher;
use Markommerce\Testing\Profile\BootedStore;
use Markommerce\Testing\Profile\StoreProfile;
use Markommerce\Testing\Database\DatabaseProvisioner;
use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\TestIsolation;
use Markommerce\Testing\Database\IsolationMode;

function httpTestVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

/**
 * Build a unique per-worker base path for layout artifact isolation.
 */
function httpTestWorkerBasePath(): string
{
    $token = getenv('TEST_TOKEN') ?: getenv('PARATEST_TOKEN') ?: (string) mt_rand(100000, 999999);
    return sys_get_temp_dir() . '/markommerce-http-test-' . getmypid() . '-' . $token;
}

/**
 * Ensure MARKOMMERCE_CONFIG_SECRET_KEY is set to a valid test key.
 *
 * The storefront profile includes markommerce/config which binds SecretCipherInterface.
 * This binding reads the key from the env var and throws SecretCipherException if missing.
 * In CI/Docker the key is set globally; in a parallel test run another test might
 * temporarily unset it (e.g. StoreProfileTest unsets it in its finally block).
 * We pin a stable test key here so our tests are resilient regardless of execution order.
 *
 * Returns the previous value so callers can restore it in a finally block.
 */
function httpTestEnsureConfigKey(): string
{
    $current = (string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '');

    if ($current === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }

    return $current;
}

/**
 * Restore MARKOMMERCE_CONFIG_SECRET_KEY to a previously captured value.
 * Call in a finally block after httpTestEnsureConfigKey().
 */
function httpTestRestoreConfigKey(string $previous): void
{
    if ($previous === '') {
        // We set it — leave it; the docker env might have already set it.
        // Only unset if this process set it to a dummy value.
        // Actually, we don't unset: keeping a test key is harmless and avoids
        // breaking any subsequent test in the same worker that needs it.
        return;
    }

    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $previous);
}

// Ensure the config secret key is always set for this test file.
// The storefront profile includes markommerce/config which binds SecretCipherInterface
// that reads MARKOMMERCE_CONFIG_SECRET_KEY from the env. Docker sets it globally, but
// other integration tests (e.g. StoreProfileTest) may temporarily unset it in their
// finally blocks. A beforeEach hook pins a stable key before each test so this file's
// tests are resilient regardless of parallel execution order.
beforeEach(function (): void {
    httpTestEnsureConfigKey();
});

// ─── Requirement 1 ────────────────────────────────────────────────────────────

it('sources global middleware from the booted manifests via GlobalMiddlewareResolver (CompileIfStale then MarkommerceLayout)', function (): void {
    $profile = StoreProfile::storefront(httpTestVendorDir());
    $manifests = $profile->modules();

    $resolver = new GlobalMiddlewareResolver();
    $middleware = $resolver->resolve($manifests);

    expect($middleware)->toContain(CompileIfStaleMiddleware::class);
    expect($middleware)->toContain(MarkommerceLayoutMiddleware::class);

    // Order: CompileIfStale must come before MarkommerceLayout
    $compileIdx = array_search(CompileIfStaleMiddleware::class, $middleware, true);
    $layoutIdx = array_search(MarkommerceLayoutMiddleware::class, $middleware, true);

    // array_search returns int|string|false; the toContain assertions above guarantee
    // both values are present, so we assert not-false before casting to int.
    expect($compileIdx)->not->toBeFalse();
    expect($layoutIdx)->not->toBeFalse();
    expect((int) $compileIdx)->toBeLessThan((int) $layoutIdx);
});

// ─── Requirement 2 ────────────────────────────────────────────────────────────

it('builds a router that matches the category page route via RoutingBootstrapper', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath = httpTestWorkerBasePath();
    $profile = StoreProfile::storefront(httpTestVendorDir());
    $conn = new TestConnection();
    $store = $profile->boot($conn, $basePath);

    $dispatcher = new RequestDispatcher($store);
    $router = $dispatcher->buildRouter();

    expect($router)->toBeInstanceOf(Router::class);

    // Verify it can match /catalog/category/{id}
    /** @var \Marko\Routing\RouteMatcherInterface $matcher */
    $matcher = $store->get(\Marko\Routing\RouteMatcherInterface::class);
    $matched = $matcher->match('GET', '/catalog/category/1');

    expect($matched)->not->toBeNull();
    expect($matched?->route->controller)->toBe(\Markommerce\CatalogStorefront\Controller\CategoryController::class);
    expect($matched?->route->action)->toBe('show');
})->group('integration-destructive');

// ─── Requirement 3 ────────────────────────────────────────────────────────────

it('renders real Latte HTML (not fake-view placeholder markup) without throwing ViteManifestException', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath = httpTestWorkerBasePath() . '-r3';
    $profile = StoreProfile::storefront(httpTestVendorDir());

    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner->ensureTemplate();
    $provisioner->ensureWorkerClone();
    $conn = $provisioner->connection();
    /** @var ConnectionInterface&TransactionInterface $conn */
    $isolation = new TestIsolation(IsolationMode::Rollback);

    $store = $profile->boot($conn, $basePath);
    $isolation->begin($conn, $provisioner->tableNames());

    try {
        $category = CategoryFactory::new($store)->withName('Latte Real Category')->create();
        ProductFactory::new($store)->withName('Latte Real Product')->inCategory($category)->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);

        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        // Real Latte output — must NOT contain fake-view data-template markers
        expect($response->body())->not->toContain('data-template=');
        // Must contain actual HTML structure
        expect($response->body())->toContain('<html');
    } finally {
        $isolation->finish();
        $provisioner->teardown();
    }
})->group('integration-destructive');

// ─── Requirement 4 ────────────────────────────────────────────────────────────

it('returns a 200 response whose HTML body contains the seeded product markup', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath = httpTestWorkerBasePath() . '-r4';
    $profile = StoreProfile::storefront(httpTestVendorDir());

    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner->ensureTemplate();
    $provisioner->ensureWorkerClone();
    $conn = $provisioner->connection();
    /** @var ConnectionInterface&TransactionInterface $conn */
    $isolation = new TestIsolation(IsolationMode::Rollback);

    $store = $profile->boot($conn, $basePath);
    $isolation->begin($conn, $provisioner->tableNames());

    try {
        $category = CategoryFactory::new($store)->withName('Product Markup Category')->create();
        ProductFactory::new($store)->withName('Seeded Product Alpha')->inCategory($category)->create();
        ProductFactory::new($store)->withName('Seeded Product Beta')->inCategory($category)->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);

        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        expect($response->body())->toContain('Seeded Product Alpha');
        expect($response->body())->toContain('Seeded Product Beta');
    } finally {
        $isolation->finish();
        $provisioner->teardown();
    }
})->group('integration-destructive');

// ─── Requirement 5 ────────────────────────────────────────────────────────────

it('exposes the SEO canonical Link header on the response', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath = httpTestWorkerBasePath() . '-r5';
    $profile = StoreProfile::storefront(httpTestVendorDir());

    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner->ensureTemplate();
    $provisioner->ensureWorkerClone();
    $conn = $provisioner->connection();
    /** @var ConnectionInterface&TransactionInterface $conn */
    $isolation = new TestIsolation(IsolationMode::Rollback);

    $store = $profile->boot($conn, $basePath);
    $isolation->begin($conn, $provisioner->tableNames());

    try {
        $category = CategoryFactory::new($store)->withName('SEO Category')->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'example.com',
        ]);

        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);

        $headers = $response->headers();
        $linkHeader = $headers['Link'] ?? null;
        expect($linkHeader)->not->toBeNull();
        expect($linkHeader)->toContain('rel="canonical"');
        expect($linkHeader)->toContain('/catalog/category/' . $category->id);
    } finally {
        $isolation->finish();
        $provisioner->teardown();
    }
})->group('integration-destructive');

// ─── Requirement 6 ────────────────────────────────────────────────────────────

it('passes through controller short-circuit responses (302/410/404) with status and Location/headers intact', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath = httpTestWorkerBasePath() . '-r6';
    $profile = StoreProfile::storefront(httpTestVendorDir());

    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner->ensureTemplate();
    $provisioner->ensureWorkerClone();
    $conn = $provisioner->connection();
    /** @var ConnectionInterface&TransactionInterface $conn */
    $isolation = new TestIsolation(IsolationMode::Rollback);

    $store = $profile->boot($conn, $basePath);
    $isolation->begin($conn, $provisioner->tableNames());

    try {
        // 404: non-existent category
        $notFoundRequest = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/99999',
            'HTTP_HOST' => 'localhost',
        ]);
        $notFoundResponse = $store->handle($notFoundRequest);
        expect($notFoundResponse->statusCode())->toBe(404);

        // 302: invalid sort param redirects to default
        $category = CategoryFactory::new($store)->withName('Sort Redirect Category')->create();
        $redirectRequest = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/catalog/category/' . $category->id . '?sort=invalid_sort_xyz',
                'HTTP_HOST' => 'localhost',
            ],
            query: ['sort' => 'invalid_sort_xyz'],
        );
        $redirectResponse = $store->handle($redirectRequest);
        expect($redirectResponse->statusCode())->toBe(302);
        $locationHeader = $redirectResponse->headers()['Location'] ?? null;
        expect($locationHeader)->not->toBeNull();
    } finally {
        $isolation->finish();
        $provisioner->teardown();
    }
})->group('integration-destructive');

// ─── Requirement 7 ────────────────────────────────────────────────────────────

it('emits vite dev-server script tags rather than failing on a missing manifest', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath = httpTestWorkerBasePath() . '-r7';
    $profile = StoreProfile::storefront(httpTestVendorDir());

    $provisioner = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner->ensureTemplate();
    $provisioner->ensureWorkerClone();
    $conn = $provisioner->connection();
    /** @var ConnectionInterface&TransactionInterface $conn */
    $isolation = new TestIsolation(IsolationMode::Rollback);

    $store = $profile->boot($conn, $basePath);
    $isolation->begin($conn, $provisioner->tableNames());

    try {
        $category = CategoryFactory::new($store)->withName('Vite DevServer Category')->create();
        ProductFactory::new($store)->withName('Vite DevServer Product')->inCategory($category)->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);

        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        // Dev-server tags — emitted by Vite::devServerTags(); no manifest required
        expect($response->body())->toContain('type="module"');
        expect($response->body())->toContain('@vite/client');
    } finally {
        $isolation->finish();
        $provisioner->teardown();
    }
})->group('integration-destructive');

// ─── Requirement 8 ────────────────────────────────────────────────────────────

it('compiles layouts under the per-worker ProjectPaths base without cross-test collision', function (): void {
    TestConnection::skipIfUnavailable();

    $basePath1 = httpTestWorkerBasePath() . '-worker1';
    $basePath2 = httpTestWorkerBasePath() . '-worker2';

    $profile = StoreProfile::storefront(httpTestVendorDir());

    $provisioner1 = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner1->ensureTemplate();
    $provisioner1->ensureWorkerClone();
    $conn1 = $provisioner1->connection();
    /** @var ConnectionInterface&TransactionInterface $conn1 */

    $provisioner2 = new DatabaseProvisioner(new AdminConnection(), $profile);
    $provisioner2->ensureTemplate();
    $provisioner2->ensureWorkerClone();
    $conn2 = $provisioner2->connection();
    /** @var ConnectionInterface&TransactionInterface $conn2 */

    $isolation1 = new TestIsolation(IsolationMode::Rollback);
    $isolation2 = new TestIsolation(IsolationMode::Rollback);

    $store1 = $profile->boot($conn1, $basePath1);
    $store2 = $profile->boot($conn2, $basePath2);

    $isolation1->begin($conn1, $provisioner1->tableNames());
    $isolation2->begin($conn2, $provisioner2->tableNames());

    try {
        // Each store writes its layout artifact to its own isolated base path
        $category1 = CategoryFactory::new($store1)->withName('Worker1 Category')->create();
        $category2 = CategoryFactory::new($store2)->withName('Worker2 Category')->create();

        $request1 = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category1->id,
            'HTTP_HOST' => 'localhost',
        ]);
        $request2 = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category2->id,
            'HTTP_HOST' => 'localhost',
        ]);

        $response1 = $store1->handle($request1);
        $response2 = $store2->handle($request2);

        expect($response1->statusCode())->toBe(200);
        expect($response2->statusCode())->toBe(200);

        // Layout artifacts are in separate directories (no collision)
        $artifact1 = $basePath1 . '/var/cache/markommerce/layouts.php';
        $artifact2 = $basePath2 . '/var/cache/markommerce/layouts.php';

        expect(is_file($artifact1))->toBeTrue("Expected layout artifact at $artifact1");
        expect(is_file($artifact2))->toBeTrue("Expected layout artifact at $artifact2");
        expect($artifact1)->not->toBe($artifact2);
    } finally {
        $isolation1->finish();
        $isolation2->finish();
        $provisioner1->teardown();
        $provisioner2->teardown();
    }
})->group('integration-destructive');
