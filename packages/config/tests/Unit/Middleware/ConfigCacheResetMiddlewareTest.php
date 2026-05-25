<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Middleware\ConfigCacheResetMiddleware;

// ---- Fakes ----

class FakeConfigCache implements ConfigCacheInterface
{
    public int $clearCallCount = 0;

    /** @var array<string, mixed> */
    private array $store = [];

    public function get(
        string $cacheKey,
        Closure $loader,
    ): mixed
    {
        if (array_key_exists($cacheKey, $this->store)) {
            return $this->store[$cacheKey];
        }

        return $this->store[$cacheKey] = $loader();
    }

    public function invalidatePrefix(string $configKey): void
    {
        $prefix = $configKey . '|';

        foreach (array_keys($this->store) as $key) {
            if ($key === $configKey || str_starts_with($key, $prefix)) {
                unset($this->store[$key]);
            }
        }
    }

    public function clear(): void
    {
        $this->clearCallCount++;
        $this->store = [];
    }
}

// ---- Tests ----

it('ConfigCacheResetMiddleware calls cache.clear() before forwarding to the next handler', function (): void {
    $cache = new FakeConfigCache();

    // Pre-populate cache to verify it gets cleared
    $cache->get('some-key', fn () => 'cached-value');

    $middleware = new ConfigCacheResetMiddleware($cache);

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    $response = new Response(body: 'ok', statusCode: 200);

    $clearCalledBeforeNext = false;

    $next = function (Request $req) use ($response, $cache, &$clearCalledBeforeNext): Response {
        // When next is called, clear should have already been called
        $clearCalledBeforeNext = $cache->clearCallCount > 0;

        return $response;
    };

    $result = $middleware->handle($request, $next);

    expect($clearCalledBeforeNext)->toBeTrue()
        ->and($cache->clearCallCount)->toBe(1)
        ->and($result)->toBe($response);
});
