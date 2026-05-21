<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\Builtin\AcceptLanguageResolver;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\SyntheticRequest;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeAcceptLanguageStubRegistry(): ScopeRegistryInterface
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

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw new \RuntimeException('Not implemented');
        }
    };
}

/**
 * @param list<string> $paths
 */
function makeAcceptLanguageAxis(array $paths): ScopeAxis
{
    return new ScopeAxis(
        name: 'language',
        hierarchy: new ScopeHierarchy($paths),
        default: $paths[0] ?? 'en',
    );
}

function makeAcceptLanguageContext(Request $request, string $channel = ScopeResolutionContext::CHANNEL_HTTP): ScopeResolutionContext
{
    return new ScopeResolutionContext(
        request: $request,
        registry: makeAcceptLanguageStubRegistry(),
        resolved: [],
        channel: $channel,
    );
}

function makeRequestWithAcceptLanguage(string $headerValue): Request
{
    return new Request(server: ['HTTP_ACCEPT_LANGUAGE' => $headerValue]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns the highest q-value language that exists in the axis hierarchy', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl', 'de']);
    $request = makeRequestWithAcceptLanguage('pl;q=0.8,en;q=0.9,de;q=0.7');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('en');
});

it('returns null when no language in the header matches the hierarchy', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['fr', 'de']);
    $request = makeRequestWithAcceptLanguage('en-US,en;q=0.9,pl;q=0.8');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when the Accept-Language header is missing', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    $request = SyntheticRequest::create();
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when the Accept-Language header is empty string', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    $request = makeRequestWithAcceptLanguage('');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when the Accept-Language header is malformed', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    $request = makeRequestWithAcceptLanguage(';;;???!!!');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('treats omitted q-value as 1.0', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl', 'de']);
    // 'pl' has no q so defaults to 1.0; 'en' has q=0.9
    $request = makeRequestWithAcceptLanguage('pl,en;q=0.9');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('pl');
});

it('falls back to language code without region when the regional variant is not in the hierarchy', function (): void {
    $resolver = new AcceptLanguageResolver();
    // 'en-us' is not in hierarchy but 'en' is
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    $request = makeRequestWithAcceptLanguage('en-US,pl;q=0.8');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('en');
});

it('lowercases language tags before checking the hierarchy', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    // Header may send uppercase or mixed-case tags
    $request = makeRequestWithAcceptLanguage('EN,PL;q=0.8');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('en');
});

it('skips candidates with q-values outside the 0 to 1 range', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl', 'de']);
    // 'en' has q=1.5 (out of range, skip), 'pl' has q=0.8, 'de' has q=-0.1 (out of range, skip)
    $request = makeRequestWithAcceptLanguage('en;q=1.5,pl;q=0.8,de;q=-0.1');
    $context = makeAcceptLanguageContext($request);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBe('pl');
});

it('returns null when channel is cli', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    $request = makeRequestWithAcceptLanguage('en,pl;q=0.9');
    $context = makeAcceptLanguageContext($request, ScopeResolutionContext::CHANNEL_CLI);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});

it('returns null when channel is queue', function (): void {
    $resolver = new AcceptLanguageResolver();
    $axis = makeAcceptLanguageAxis(['en', 'pl']);
    $request = makeRequestWithAcceptLanguage('en,pl;q=0.9');
    $context = makeAcceptLanguageContext($request, ScopeResolutionContext::CHANNEL_QUEUE);

    $result = $resolver->resolve($axis, $context);

    expect($result)->toBeNull();
});
