<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\PathPrefixResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makePathPrefixRegistry(): ScopeRegistryInterface
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

function makePathPrefixAxis(): ScopeAxis
{
    return new ScopeAxis(
        name: 'market',
        hierarchy: new ScopeHierarchy(['global', 'global.eu']),
        default: 'global',
    );
}

function makePathContext(string $path, string $channel = ScopeResolutionContext::CHANNEL_HTTP): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: new Request(server: ['REQUEST_URI' => $path]),
        registry: makePathPrefixRegistry(),
        resolved: [],
        channel: $channel,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the first non-empty path segment when segment is zero', function (): void {
    $resolver = new PathPrefixResolver(0);
    $context = makePathContext('/eu/products');
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('eu');
});

it('returns the second non-empty path segment when segment is one', function (): void {
    $resolver = new PathPrefixResolver(1);
    $context = makePathContext('/eu/products');
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('products');
});

it('returns null when path is /', function (): void {
    $resolver = new PathPrefixResolver(0);
    $context = makePathContext('/');
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when path has fewer segments than the configured index', function (): void {
    $resolver = new PathPrefixResolver(2);
    $context = makePathContext('/eu/products');
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('ignores leading and trailing slashes', function (): void {
    $resolver = new PathPrefixResolver(0);
    $context = makePathContext('/eu/');
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('eu');
});

it('returns null when channel is cli', function (): void {
    $resolver = new PathPrefixResolver(0);
    $context = makePathContext('/eu/products', ScopeResolutionContext::CHANNEL_CLI);
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when channel is queue', function (): void {
    $resolver = new PathPrefixResolver(0);
    $context = makePathContext('/eu/products', ScopeResolutionContext::CHANNEL_QUEUE);
    $axis = makePathPrefixAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});
