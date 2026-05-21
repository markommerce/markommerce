<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\CookieResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\SyntheticRequest;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeCookieStubRegistry(): ScopeRegistryInterface
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

function makeCookieAxis(string $name = 'store'): ScopeAxis
{
    return new ScopeAxis(
        name: $name,
        hierarchy: new ScopeHierarchy(['global', 'global.us']),
        default: 'global',
    );
}

function makeCookieContext(string $channel = ScopeResolutionContext::CHANNEL_HTTP): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: SyntheticRequest::create(),
        registry: makeCookieStubRegistry(),
        resolved: [],
        channel: $channel,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the cookie value when the configured cookie is present in the injected cookie map', function (): void {
    $resolver = new CookieResolver('store_view', ['store_view' => 'global.us']);
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_HTTP);
    $axis = makeCookieAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('global.us');
});

it('returns null when the cookie value is empty string', function (): void {
    $resolver = new CookieResolver('store_view', ['store_view' => '']);
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_HTTP);
    $axis = makeCookieAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('reads from $_COOKIE superglobal when no explicit map is injected', function (): void {
    $resolver = new CookieResolver('store_view');
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_HTTP);
    $axis = makeCookieAxis();

    $_COOKIE['store_view'] = 'global.eu';
    try {
        $result = $resolver->resolve($axis, $context);
    } finally {
        unset($_COOKIE['store_view']);
    }

    expect($result)->toBe('global.eu');
});

it('ignores cookies with a different name', function (): void {
    $resolver = new CookieResolver('store_view', ['other_cookie' => 'global.us']);
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_HTTP);
    $axis = makeCookieAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when channel is queue', function (): void {
    $resolver = new CookieResolver('store_view', ['store_view' => 'global.us']);
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_QUEUE);
    $axis = makeCookieAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when channel is cli', function (): void {
    $resolver = new CookieResolver('store_view', ['store_view' => 'global.us']);
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_CLI);
    $axis = makeCookieAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when the configured cookie is not set', function (): void {
    $resolver = new CookieResolver('store_view', []);
    $context = makeCookieContext(ScopeResolutionContext::CHANNEL_HTTP);
    $axis = makeCookieAxis();

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});
