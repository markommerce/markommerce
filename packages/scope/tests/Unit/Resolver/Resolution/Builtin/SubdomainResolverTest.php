<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\SubdomainResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeSubdomainStubRegistry(): ScopeRegistryInterface
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

function makeSubdomainAxis(string $name = 'market'): ScopeAxis
{
    return new ScopeAxis(
        name: $name,
        hierarchy: new ScopeHierarchy(['global', 'global.eu']),
        default: 'global',
    );
}

function makeSubdomainContext(string $host, string $channel = ScopeResolutionContext::CHANNEL_HTTP): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: new Request(server: ['HTTP_HOST' => $host]),
        registry: makeSubdomainStubRegistry(),
        resolved: [],
        channel: $channel,
    );
}

function makeSubdomainContextNoHost(string $channel = ScopeResolutionContext::CHANNEL_HTTP): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: new Request(server: []),
        registry: makeSubdomainStubRegistry(),
        resolved: [],
        channel: $channel,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the first subdomain label when segment is zero', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContext('eu.example.com');
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('eu');
});

it('returns the second subdomain label when segment is one', function (): void {
    $resolver = new SubdomainResolver(1);
    $context = makeSubdomainContext('eu.example.com');
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('example');
});

it('returns null when host has fewer segments than the configured index', function (): void {
    $resolver = new SubdomainResolver(3);
    $context = makeSubdomainContext('eu.example.com');
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when host is empty', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContext('');
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when host header is missing entirely', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContextNoHost();
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('strips port suffix from the host before splitting', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContext('eu.example.com:8080');
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('eu');
});

it('returns null when host is a raw IPv4 address', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContext('192.0.2.1');
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when channel is cli', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContext('eu.example.com', ScopeResolutionContext::CHANNEL_CLI);
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when channel is queue', function (): void {
    $resolver = new SubdomainResolver(0);
    $context = makeSubdomainContext('eu.example.com', ScopeResolutionContext::CHANNEL_QUEUE);
    $axis = makeSubdomainAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});
