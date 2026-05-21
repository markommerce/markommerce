<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\QueryParamResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeQueryParamStubRegistry(): ScopeRegistryInterface
{
    return new class implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw new \RuntimeException('Not implemented');
        }

        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): \Markommerce\Scope\Hierarchy\ScopeHierarchy
        {
            throw new \RuntimeException('Not implemented');
        }
    };
}

function makeQueryParamAxis(string $name = 'locale'): ScopeAxis
{
    return new ScopeAxis(
        name: $name,
        hierarchy: new ScopeHierarchy(['global', 'global.pl']),
        default: 'global',
    );
}

function makeQueryParamContext(Request $request, string $channel = ScopeResolutionContext::CHANNEL_HTTP): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: $request,
        registry: makeQueryParamStubRegistry(),
        resolved: [],
        channel: $channel,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the param value when the query string contains the configured key', function (): void {
    $request = new Request(query: ['_scope_locale' => 'global.pl']);
    $context = makeQueryParamContext($request);
    $resolver = new QueryParamResolver('_scope_locale');

    $result = $resolver->resolve(makeQueryParamAxis(), $context);

    expect($result)->toBe('global.pl');
});

it('returns null when the configured key is missing from the query string', function (): void {
    $request = new Request(query: []);
    $context = makeQueryParamContext($request);
    $resolver = new QueryParamResolver('_scope_locale');

    $result = $resolver->resolve(makeQueryParamAxis(), $context);

    expect($result)->toBeNull();
});

it('returns null when the configured key has an empty string value', function (): void {
    $request = new Request(query: ['_scope_locale' => '']);
    $context = makeQueryParamContext($request);
    $resolver = new QueryParamResolver('_scope_locale');

    $result = $resolver->resolve(makeQueryParamAxis(), $context);

    expect($result)->toBeNull();
});

it('returns null when the configured key has an array value (e.g. _scope[]=foo)', function (): void {
    $request = new Request(query: ['_scope_locale' => ['foo']]);
    $context = makeQueryParamContext($request);
    $resolver = new QueryParamResolver('_scope_locale');

    $result = $resolver->resolve(makeQueryParamAxis(), $context);

    expect($result)->toBeNull();
});

it('returns null when channel is cli', function (): void {
    $request = new Request(query: ['_scope_locale' => 'global.pl']);
    $context = makeQueryParamContext($request, ScopeResolutionContext::CHANNEL_CLI);
    $resolver = new QueryParamResolver('_scope_locale');

    $result = $resolver->resolve(makeQueryParamAxis(), $context);

    expect($result)->toBeNull();
});

it('returns null when channel is queue', function (): void {
    $request = new Request(query: ['_scope_locale' => 'global.pl']);
    $context = makeQueryParamContext($request, ScopeResolutionContext::CHANNEL_QUEUE);
    $resolver = new QueryParamResolver('_scope_locale');

    $result = $resolver->resolve(makeQueryParamAxis(), $context);

    expect($result)->toBeNull();
});
