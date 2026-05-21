<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\HeaderResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeHeaderTestRegistry(): ScopeRegistryInterface
{
    return new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw new RuntimeException('Not implemented');
        }

        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw new RuntimeException('Not implemented');
        }
    };
}

function makeHttpContext(Request $request): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: $request,
        registry: makeHeaderTestRegistry(),
        resolved: [],
        channel: ScopeResolutionContext::CHANNEL_HTTP,
    );
}

function makeHeaderAxis(string $name = 'store'): ScopeAxis
{
    return new ScopeAxis(
        name: $name,
        hierarchy: new ScopeHierarchy(['global', 'global.us']),
        default: 'global',
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the header value when the configured header is present', function (): void {
    $request = new Request(server: ['HTTP_X_SCOPE_STORE' => 'global.us']);
    $context = makeHttpContext($request);
    $resolver = new HeaderResolver('X-Scope-Store');

    $result = $resolver->resolve(makeHeaderAxis(), $context);

    expect($result)->toBe('global.us');
});

it('returns null when the configured header is missing', function (): void {
    $request = new Request(server: []);
    $context = makeHttpContext($request);
    $resolver = new HeaderResolver('X-Scope-Store');

    $result = $resolver->resolve(makeHeaderAxis(), $context);

    expect($result)->toBeNull();
});

it('returns null when the configured header is present but empty string', function (): void {
    $request = new Request(server: ['HTTP_X_SCOPE_STORE' => '']);
    $context = makeHttpContext($request);
    $resolver = new HeaderResolver('X-Scope-Store');

    $result = $resolver->resolve(makeHeaderAxis(), $context);

    expect($result)->toBeNull();
});

it('is case-insensitive for the header name', function (): void {
    $request = new Request(server: ['HTTP_X_SCOPE_STORE' => 'global.us']);
    $context = makeHttpContext($request);
    $resolver = new HeaderResolver('x-scope-store');

    $result = $resolver->resolve(makeHeaderAxis(), $context);

    expect($result)->toBe('global.us');
});

it('returns null when channel is cli', function (): void {
    $request = new Request(server: ['HTTP_X_SCOPE_STORE' => 'global.us']);
    $context = new ScopeResolutionContext(
        request: $request,
        registry: makeHeaderTestRegistry(),
        resolved: [],
        channel: ScopeResolutionContext::CHANNEL_CLI,
    );
    $resolver = new HeaderResolver('X-Scope-Store');

    $result = $resolver->resolve(makeHeaderAxis(), $context);

    expect($result)->toBeNull();
});

it('returns null when channel is queue', function (): void {
    $request = new Request(server: ['HTTP_X_SCOPE_STORE' => 'global.us']);
    $context = new ScopeResolutionContext(
        request: $request,
        registry: makeHeaderTestRegistry(),
        resolved: [],
        channel: ScopeResolutionContext::CHANNEL_QUEUE,
    );
    $resolver = new HeaderResolver('X-Scope-Store');

    $result = $resolver->resolve(makeHeaderAxis(), $context);

    expect($result)->toBeNull();
});
