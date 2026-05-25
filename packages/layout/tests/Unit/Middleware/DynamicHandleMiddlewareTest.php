<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcherInterface;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Contracts\HandleProvider;
use Markommerce\Layout\Exceptions\UnknownDynamicHandleException;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;
use Markommerce\Layout\Runtime\RendererInterface;

// =============================================================================
// Fakes
// =============================================================================

class DHM_FakeRouteMatcher implements RouteMatcherInterface
{
    private ?MatchedRoute $matched;

    public function __construct(?MatchedRoute $matched)
    {
        $this->matched = $matched;
    }

    public function match(
        string $method,
        string $path,
    ): ?MatchedRoute
    {
        return $this->matched;
    }
}

class DHM_FakeArtifactReader implements ArtifactReaderInterface
{
    /** @var array<string, PreparedTree> */
    private array $artifact;

    /** @param array<string, PreparedTree> $artifact */
    public function __construct(array $artifact)
    {
        $this->artifact = $artifact;
    }

    /** @return array<string, PreparedTree> */
    public function read(): array
    {
        return $this->artifact;
    }
}

class DHM_FakeRenderer implements RendererInterface
{
    public ?PreparedTree $lastTree = null;

    public function render(
        PreparedTree $tree,
        Request $request,
        array $routeParams,
    ): string
    {
        $this->lastTree = $tree;

        return '<html>rendered</html>';
    }
}

class DHM_SpyHandleProvider implements HandleProvider
{
    /** @var array<int, array<string, mixed>> */
    public array $receivedProps = [];

    /** @var list<string> */
    private array $handles;

    /** @param list<string> $handles */
    public function __construct(array $handles)
    {
        $this->handles = $handles;
    }

    /** @return list<string> */
    public function provide(array $props): array
    {
        $this->receivedProps[] = $props;

        return $this->handles;
    }
}

class DHM_AnotherSpyHandleProvider implements HandleProvider
{
    /** @var list<string> */
    private array $handles;

    /** @param list<string> $handles */
    public function __construct(array $handles)
    {
        $this->handles = $handles;
    }

    /** @return list<string> */
    public function provide(array $props): array
    {
        return $this->handles;
    }
}

class DHM_FakeContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $bindings = [];

    public function bind(
        string $class,
        object $instance,
    ): void
    {
        $this->bindings[$class] = $instance;
    }

    public function get(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id];
        }
        if (class_exists($id)) {
            return new $id();
        }
        throw new RuntimeException("No binding for $id");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function singleton(string $id): void {}

    public function instance(
        string $id,
        object $instance,
    ): void
    {
        $this->bindings[$id] = $instance;
    }

    public function call(Closure $callable): mixed
    {
        return $callable();
    }
}

// =============================================================================
// Helpers
// =============================================================================

function dhm_makeRequest(): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products/1']);
}

function dhm_makeRoute(string $controller = 'App\\Controller\\ProductController', string $action = 'show'): MatchedRoute
{
    return new MatchedRoute(
        route: new RouteDefinition(
            method: 'GET',
            path: '/products/{id}',
            controller: $controller,
            action: $action,
        ),
        parameters: [],
    );
}

function dhm_makePlace(string $component = 'SomeComponent', ?string $name = null): PreparedPlace
{
    return new PreparedPlace(
        component: $component,
        name: $name,
        props: [],
        slots: [],
        decorators: [],
        template: '',
    );
}

function dhm_makeTree(
    string $handleKey,
    array $slots = [],
    array $context = [],
    array $handleProviders = [],
): PreparedTree {
    return new PreparedTree(
        handleKey: $handleKey,
        template: null,
        slots: $slots,
        context: $context,
        handleProviders: $handleProviders,
    );
}

function dhm_makeMiddleware(
    ?MatchedRoute $matched,
    array $artifact,
    DHM_FakeRenderer $renderer,
    ?DHM_FakeContainer $container = null,
): MarkommerceLayoutMiddleware {
    return new MarkommerceLayoutMiddleware(
        routeMatcher: new DHM_FakeRouteMatcher($matched),
        artifactReader: new DHM_FakeArtifactReader($artifact),
        renderer: $renderer,
        container: $container ?? new DHM_FakeContainer(),
    );
}

// =============================================================================
// Requirement 1: it invokes each HandleProvider declared on the base tree with resolved props
// =============================================================================

it('it invokes each HandleProvider declared on the base tree with resolved props', function (): void {
    $controller = 'App\\Controller\\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $spy = new DHM_SpyHandleProvider(['dynamic-handle']);

    $container = new DHM_FakeContainer();
    $container->bind(DHM_SpyHandleProvider::class, $spy);

    $provideHandle = new ProvideHandle(
        provider: DHM_SpyHandleProvider::class,
        props: ['myProp' => 'literalValue'],
    );

    $baseTree = dhm_makeTree(
        handleKey: $handleKey,
        handleProviders: [$provideHandle],
    );

    $dynamicTree = dhm_makeTree(handleKey: 'dynamic-handle');

    $renderer = new DHM_FakeRenderer();
    $middleware = dhm_makeMiddleware(
        matched: dhm_makeRoute($controller, $action),
        artifact: [$handleKey => $baseTree, 'dynamic-handle' => $dynamicTree],
        renderer: $renderer,
        container: $container,
    );

    $request = dhm_makeRequest();
    $middleware->handle($request, static fn (Request $r): Response => new Response('ok', 200));

    expect($spy->receivedProps)->toHaveCount(1);
    expect($spy->receivedProps[0])->toBe(['myProp' => 'literalValue']);
});

// =============================================================================
// Requirement 2: it merges every returned handle's tree into the base tree before rendering
// =============================================================================

it('it merges every returned handle\'s tree into the base tree before rendering', function (): void {
    $controller = 'App\\Controller\\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $basePlace = dhm_makePlace('BaseComponent', 'base_comp');
    $dynamicPlace = dhm_makePlace('DynamicComponent', 'dynamic_comp');

    $provider = new DHM_SpyHandleProvider(['dynamic-handle']);

    $container = new DHM_FakeContainer();
    $container->bind(DHM_SpyHandleProvider::class, $provider);

    $provideHandle = new ProvideHandle(
        provider: DHM_SpyHandleProvider::class,
        props: [],
    );

    $baseTree = dhm_makeTree(
        handleKey: $handleKey,
        slots: ['main' => [$basePlace]],
        handleProviders: [$provideHandle],
    );
    $dynamicTree = dhm_makeTree(
        handleKey: 'dynamic-handle',
        slots: ['main' => [$dynamicPlace]],
    );

    $renderer = new DHM_FakeRenderer();
    $middleware = dhm_makeMiddleware(
        matched: dhm_makeRoute($controller, $action),
        artifact: [$handleKey => $baseTree, 'dynamic-handle' => $dynamicTree],
        renderer: $renderer,
        container: $container,
    );

    $middleware->handle(
        dhm_makeRequest(),
        static fn (Request $r): Response => new Response('ok', 200),
    );

    // The renderer should have received a merged tree with both components in the main slot
    assert($renderer->lastTree !== null);
    $mainSlot = $renderer->lastTree->slots['main'] ?? [];
    expect($mainSlot)->toHaveCount(2);
    $components = array_map(fn (PreparedPlace $p) => $p->component, $mainSlot);
    expect($components)->toBe(['BaseComponent', 'DynamicComponent']);
});

// =============================================================================
// Requirement 3: it appends dynamic-handle placements to base slot entries
// =============================================================================

it('it appends dynamic-handle placements to base slot entries', function (): void {
    $controller = 'App\\Controller\\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $basePlace1 = dhm_makePlace('ComponentA', 'comp_a');
    $basePlace2 = dhm_makePlace('ComponentB', 'comp_b');
    $dynamicPlace = dhm_makePlace('DynamicComponent', 'dynamic_comp');

    $provider = new DHM_SpyHandleProvider(['dynamic-handle']);
    $container = new DHM_FakeContainer();
    $container->bind(DHM_SpyHandleProvider::class, $provider);

    $baseTree = dhm_makeTree(
        handleKey: $handleKey,
        slots: ['main' => [$basePlace1, $basePlace2]],
        handleProviders: [new ProvideHandle(provider: DHM_SpyHandleProvider::class, props: [])],
    );
    $dynamicTree = dhm_makeTree(
        handleKey: 'dynamic-handle',
        slots: ['main' => [$dynamicPlace]],
    );

    $renderer = new DHM_FakeRenderer();
    $middleware = dhm_makeMiddleware(
        matched: dhm_makeRoute($controller, $action),
        artifact: [$handleKey => $baseTree, 'dynamic-handle' => $dynamicTree],
        renderer: $renderer,
        container: $container,
    );

    $middleware->handle(
        dhm_makeRequest(),
        static fn (Request $r): Response => new Response('ok', 200),
    );

    assert($renderer->lastTree !== null);
    $mainSlot = $renderer->lastTree->slots['main'];
    expect($mainSlot)->toHaveCount(3);

    // Dynamic placements are appended AFTER base placements
    $components = array_map(fn (PreparedPlace $p) => $p->component, $mainSlot);
    expect($components)->toBe(['ComponentA', 'ComponentB', 'DynamicComponent']);
});

// =============================================================================
// Requirement 4: it preserves declaration order when multiple providers return overlapping handles
// =============================================================================

it('it preserves declaration order when multiple providers return overlapping handles', function (): void {
    $controller = 'App\\Controller\\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $basePlace = dhm_makePlace('BaseComponent', 'base');
    $dynamicPlaceA = dhm_makePlace('DynamicA', 'dyn_a');
    $dynamicPlaceB = dhm_makePlace('DynamicB', 'dyn_b');

    // Provider 1 returns ['handle-a', 'handle-b']
    $provider1 = new DHM_SpyHandleProvider(['handle-a', 'handle-b']);
    // Provider 2 returns ['handle-b', 'handle-a'] — same handles, different order
    $provider2 = new DHM_AnotherSpyHandleProvider(['handle-b', 'handle-a']);

    $container = new DHM_FakeContainer();
    $container->bind(DHM_SpyHandleProvider::class, $provider1);
    $container->bind(DHM_AnotherSpyHandleProvider::class, $provider2);

    $baseTree = dhm_makeTree(
        handleKey: $handleKey,
        slots: ['main' => [$basePlace]],
        handleProviders: [
            new ProvideHandle(provider: DHM_SpyHandleProvider::class, props: []),
            new ProvideHandle(provider: DHM_AnotherSpyHandleProvider::class, props: []),
        ],
    );
    $treeA = dhm_makeTree(handleKey: 'handle-a', slots: ['main' => [$dynamicPlaceA]]);
    $treeB = dhm_makeTree(handleKey: 'handle-b', slots: ['main' => [$dynamicPlaceB]]);

    $renderer = new DHM_FakeRenderer();
    $middleware = dhm_makeMiddleware(
        matched: dhm_makeRoute($controller, $action),
        artifact: [$handleKey => $baseTree, 'handle-a' => $treeA, 'handle-b' => $treeB],
        renderer: $renderer,
        container: $container,
    );

    $middleware->handle(
        dhm_makeRequest(),
        static fn (Request $r): Response => new Response('ok', 200),
    );

    assert($renderer->lastTree !== null);
    $mainSlot = $renderer->lastTree->slots['main'];

    // Declaration order: handle-a first (from provider1), then handle-b
    // Duplicates are deduped preserving first-seen order
    expect($mainSlot)->toHaveCount(3); // base + dynamicA + dynamicB
    $components = array_map(fn (PreparedPlace $p) => $p->component, $mainSlot);
    expect($components)->toBe(['BaseComponent', 'DynamicA', 'DynamicB']);
});

// =============================================================================
// Requirement 5: it deduplicates handle keys returned by multiple providers
// =============================================================================

it('it deduplicates handle keys returned by multiple providers', function (): void {
    $controller = 'App\\Controller\\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $basePlace = dhm_makePlace('BaseComponent', 'base');
    $dynamicPlace = dhm_makePlace('DynamicComponent', 'dynamic');

    // Both providers return the same handle key
    $provider1 = new DHM_SpyHandleProvider(['shared-handle']);
    $provider2 = new DHM_AnotherSpyHandleProvider(['shared-handle']);

    $container = new DHM_FakeContainer();
    $container->bind(DHM_SpyHandleProvider::class, $provider1);
    $container->bind(DHM_AnotherSpyHandleProvider::class, $provider2);

    $baseTree = dhm_makeTree(
        handleKey: $handleKey,
        slots: ['main' => [$basePlace]],
        handleProviders: [
            new ProvideHandle(provider: DHM_SpyHandleProvider::class, props: []),
            new ProvideHandle(provider: DHM_AnotherSpyHandleProvider::class, props: []),
        ],
    );
    $sharedTree = dhm_makeTree(handleKey: 'shared-handle', slots: ['main' => [$dynamicPlace]]);

    $renderer = new DHM_FakeRenderer();
    $middleware = dhm_makeMiddleware(
        matched: dhm_makeRoute($controller, $action),
        artifact: [$handleKey => $baseTree, 'shared-handle' => $sharedTree],
        renderer: $renderer,
        container: $container,
    );

    $middleware->handle(
        dhm_makeRequest(),
        static fn (Request $r): Response => new Response('ok', 200),
    );

    assert($renderer->lastTree !== null);
    $mainSlot = $renderer->lastTree->slots['main'];

    // Deduplicated: only one copy of 'shared-handle' should be merged
    expect($mainSlot)->toHaveCount(2); // base + dynamic (NOT base + dynamic + dynamic)
    $components = array_map(fn (PreparedPlace $p) => $p->component, $mainSlot);
    expect($components)->toBe(['BaseComponent', 'DynamicComponent']);
});

// =============================================================================
// Requirement 6: it renders the base tree unchanged when the base tree declares no handleProviders
// =============================================================================

it('it renders the base tree unchanged when the base tree declares no handleProviders', function (): void {
    $controller = 'App\\Controller\\ProductController';
    $action = 'show';
    $handleKey = $controller . '::' . $action;

    $basePlace = dhm_makePlace('BaseComponent', 'base');
    $baseTree = dhm_makeTree(
        handleKey: $handleKey,
        slots: ['main' => [$basePlace]],
        handleProviders: [], // No handle providers — fast path
    );

    $renderer = new DHM_FakeRenderer();
    $middleware = dhm_makeMiddleware(
        matched: dhm_makeRoute($controller, $action),
        artifact: [$handleKey => $baseTree],
        renderer: $renderer,
    );

    $middleware->handle(
        dhm_makeRequest(),
        static fn (Request $r): Response => new Response('ok', 200),
    );

    assert($renderer->lastTree !== null);
    // The exact same tree object should be passed — fast path, no merge
    expect($renderer->lastTree)->toBe($baseTree);
});

// =============================================================================
// Requirement 7: it falls through to next middleware when no base tree exists in the artifact
// (unchanged from existing behavior)
// =============================================================================

it(
    'it falls through to next middleware when no base tree exists in the artifact (unchanged from existing behavior)',
    function (): void {
        $controller = 'App\\Controller\\ProductController';
        $action = 'show';
        $handleKey = $controller . '::' . $action;
    
        $renderer = new DHM_FakeRenderer();
        // Artifact has no entry for this handle
    $middleware = dhm_makeMiddleware(
            matched: dhm_makeRoute($controller, $action),
            artifact: [],
            renderer: $renderer,
        );
    
        $nextCalled = false;
        $middleware->handle(
            dhm_makeRequest(),
            static function (Request $r) use (&$nextCalled): Response {
                $nextCalled = true;

                return new Response('next response', 200);
            },
        );
    
        expect($renderer->lastTree)->toBeNull();
        expect($nextCalled)->toBeTrue();
    }
);

// =============================================================================
// Fake context provider for testing context concatenation
// =============================================================================

class DHM_FakeContextProvider implements ContextProvider
{
    private object $value;

    public function __construct(object $value)
    {
        $this->value = $value;
    }

    public function provide(array $props): object
    {
        return $this->value;
    }
}

// =============================================================================
// Requirement 8: it concatenates dynamic-handle context providers onto the base tree context
// so the renderer phase 0 picks them up
// =============================================================================

it(
    'it concatenates dynamic-handle context providers onto the base tree context so the renderer phase 0 picks them up',
    function (): void {
        $controller = 'App\\Controller\\ProductController';
        $action = 'show';
        $handleKey = $controller . '::' . $action;
    
        $baseContextValue = new stdClass();
        $dynamicContextValue = new stdClass();
    
        $baseContextProviderInstance = new DHM_FakeContextProvider($baseContextValue);
        $dynamicContextProviderInstance = new DHM_FakeContextProvider($dynamicContextValue);
    
        $baseContextProvide = new Provide('BaseToken', DHM_FakeContextProvider::class . '_base', []);
        $dynamicContextProvide = new Provide('DynamicToken', DHM_FakeContextProvider::class . '_dynamic', []);
    
        $handleProviderInstance = new DHM_SpyHandleProvider(['dynamic-handle']);
    
        $container = new DHM_FakeContainer();
        $container->bind(DHM_SpyHandleProvider::class, $handleProviderInstance);
        $container->bind(DHM_FakeContextProvider::class . '_base', $baseContextProviderInstance);
        $container->bind(DHM_FakeContextProvider::class . '_dynamic', $dynamicContextProviderInstance);
    
        $baseTree = dhm_makeTree(
            handleKey: $handleKey,
            context: [$baseContextProvide],
            handleProviders: [new ProvideHandle(provider: DHM_SpyHandleProvider::class, props: [])],
        );
        $dynamicTree = dhm_makeTree(
            handleKey: 'dynamic-handle',
            context: [$dynamicContextProvide],
        );
    
        $renderer = new DHM_FakeRenderer();
        $middleware = dhm_makeMiddleware(
            matched: dhm_makeRoute($controller, $action),
            artifact: [$handleKey => $baseTree, 'dynamic-handle' => $dynamicTree],
            renderer: $renderer,
            container: $container,
        );
    
        $middleware->handle(
            dhm_makeRequest(),
            static fn (Request $r): Response => new Response('ok', 200),
        );
    
        assert($renderer->lastTree !== null);
        // Base context providers come first, dynamic ones are appended
    expect($renderer->lastTree->context)->toHaveCount(2);
        expect($renderer->lastTree->context[0]->token)->toBe('BaseToken');
        expect($renderer->lastTree->context[1]->token)->toBe('DynamicToken');
    }
);

// =============================================================================
// Requirement 9: it throws UnknownDynamicHandleException when a provider returns
// a handle key not present in the artifact
// =============================================================================

it(
    'it throws UnknownDynamicHandleException when a provider returns a handle key not present in the artifact',
    function (): void {
        $controller = 'App\\Controller\\ProductController';
        $action = 'show';
        $handleKey = $controller . '::' . $action;
    
        // Provider returns a handle key that does not exist in the artifact
    $provider = new DHM_SpyHandleProvider(['nonexistent-handle']);
        $container = new DHM_FakeContainer();
        $container->bind(DHM_SpyHandleProvider::class, $provider);
    
        $baseTree = dhm_makeTree(
            handleKey: $handleKey,
            handleProviders: [new ProvideHandle(provider: DHM_SpyHandleProvider::class, props: [])],
        );
    
        $renderer = new DHM_FakeRenderer();
        $middleware = dhm_makeMiddleware(
            matched: dhm_makeRoute($controller, $action),
            artifact: [$handleKey => $baseTree], // 'nonexistent-handle' is NOT in the artifact
        renderer: $renderer,
            container: $container,
        );
    
        expect(
            fn () => $middleware->handle(
                dhm_makeRequest(),
                static fn (Request $r): Response => new Response('ok', 200),
            ),
        )->toThrow(UnknownDynamicHandleException::class);
    }
);
