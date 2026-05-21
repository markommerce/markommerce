<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\StaticResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\SyntheticRequest;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeStaticStubRegistry(): ScopeRegistryInterface
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

function makeStaticAxis(): ScopeAxis
{
    return new ScopeAxis(
        name: 'store',
        hierarchy: new ScopeHierarchy(['global', 'global.us']),
        default: 'global',
    );
}

function makeStaticContext(string $channel): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: SyntheticRequest::create(),
        registry: makeStaticStubRegistry(),
        resolved: [],
        channel: $channel,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the configured value when channel is http', function (): void {
    $resolver = new StaticResolver('global.us');
    $context = makeStaticContext(ScopeResolutionContext::CHANNEL_HTTP);

    $result = $resolver->resolve(makeStaticAxis(), $context);

    expect($result)->toBe('global.us');
});

it('returns the configured value when channel is cli', function (): void {
    $resolver = new StaticResolver('global.eu');
    $context = makeStaticContext(ScopeResolutionContext::CHANNEL_CLI);

    $result = $resolver->resolve(makeStaticAxis(), $context);

    expect($result)->toBe('global.eu');
});

it('returns the configured value when channel is queue', function (): void {
    $resolver = new StaticResolver('global.ca');
    $context = makeStaticContext(ScopeResolutionContext::CHANNEL_QUEUE);

    $result = $resolver->resolve(makeStaticAxis(), $context);

    expect($result)->toBe('global.ca');
});

it('returns the same value for repeated calls', function (): void {
    $resolver = new StaticResolver('global.us');
    $axis = makeStaticAxis();
    $context = makeStaticContext(ScopeResolutionContext::CHANNEL_HTTP);

    $first = $resolver->resolve($axis, $context);
    $second = $resolver->resolve($axis, $context);

    expect($first)->toBe('global.us')
        ->and($second)->toBe('global.us');
});

it('ignores the request entirely', function (): void {
    $resolver = new StaticResolver('global.us');
    $axis = makeStaticAxis();

    $contextA = new ScopeResolutionContext(
        request: SyntheticRequest::create(),
        registry: makeStaticStubRegistry(),
        resolved: ['store' => 'something-else'],
        channel: ScopeResolutionContext::CHANNEL_HTTP,
    );

    $contextB = new ScopeResolutionContext(
        request: SyntheticRequest::create(),
        registry: makeStaticStubRegistry(),
        resolved: [],
        channel: ScopeResolutionContext::CHANNEL_CLI,
    );

    expect($resolver->resolve($axis, $contextA))->toBe('global.us')
        ->and($resolver->resolve($axis, $contextB))->toBe('global.us');
});
