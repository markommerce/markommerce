<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\SyntheticRequest;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeStubRegistry(): ScopeRegistryInterface
{
    return new class implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): \Markommerce\Scope\Axis\ScopeAxis
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

function makeRequest(): Request
{
    return new Request();
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('stores request registry resolved map and channel as readonly properties', function (): void {
    $request = makeRequest();
    $registry = makeStubRegistry();
    $resolved = ['store' => 'global.us'];
    $channel = ScopeResolutionContext::CHANNEL_HTTP;

    $context = new ScopeResolutionContext($request, $registry, $resolved, $channel);

    expect($context->request)->toBe($request)
        ->and($context->registry)->toBe($registry)
        ->and($context->resolved)->toBe($resolved)
        ->and($context->channel)->toBe($channel);
});

it('exposes CHANNEL_HTTP CHANNEL_CLI and CHANNEL_QUEUE constants with explicit string types', function (): void {
    $reflection = new ReflectionClass(ScopeResolutionContext::class);

    $httpConst = $reflection->getReflectionConstant('CHANNEL_HTTP');
    $cliConst = $reflection->getReflectionConstant('CHANNEL_CLI');
    $queueConst = $reflection->getReflectionConstant('CHANNEL_QUEUE');

    expect($httpConst)->not->toBeFalse()
        ->and($cliConst)->not->toBeFalse()
        ->and($queueConst)->not->toBeFalse();

    expect($httpConst->getType()?->getName())->toBe('string')
        ->and($cliConst->getType()?->getName())->toBe('string')
        ->and($queueConst->getType()?->getName())->toBe('string');

    expect(ScopeResolutionContext::CHANNEL_HTTP)->toBe('http')
        ->and(ScopeResolutionContext::CHANNEL_CLI)->toBe('cli')
        ->and(ScopeResolutionContext::CHANNEL_QUEUE)->toBe('queue');
});

it('accepts an empty resolved map', function (): void {
    $context = new ScopeResolutionContext(
        makeRequest(),
        makeStubRegistry(),
        [],
        ScopeResolutionContext::CHANNEL_CLI,
    );

    expect($context->resolved)->toBe([]);
});

it('accepts a populated resolved map mapping axis names to paths', function (): void {
    $resolved = [
        'store' => 'global.us',
        'website' => 'main',
    ];

    $context = new ScopeResolutionContext(
        makeRequest(),
        makeStubRegistry(),
        $resolved,
        ScopeResolutionContext::CHANNEL_HTTP,
    );

    expect($context->resolved)->toBe($resolved);
});

it('is declared as readonly class', function (): void {
    $reflection = new ReflectionClass(ScopeResolutionContext::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('SyntheticRequest factory returns a Marko Request with empty server query post and body', function (): void {
    $request = SyntheticRequest::create();

    expect($request)->toBeInstanceOf(Request::class)
        ->and($request->query())->toBe([])
        ->and($request->post())->toBe([])
        ->and($request->body())->toBe('');
});

it('SyntheticRequest factory returns a request whose method is GET and path is /', function (): void {
    $request = SyntheticRequest::create();

    expect($request->method())->toBe('GET')
        ->and($request->path())->toBe('/');
});

it('SyntheticRequest factory returns a request whose header lookup returns null for any name', function (): void {
    $request = SyntheticRequest::create();

    expect($request->header('X-Custom-Header'))->toBeNull()
        ->and($request->header('Authorization'))->toBeNull()
        ->and($request->header('Content-Type'))->toBeNull();
});

it('SyntheticRequest factory does not throw when constructed', function (): void {
    $request = null;
    $exception = null;

    try {
        $request = SyntheticRequest::create();
    } catch (\Throwable $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull()
        ->and($request)->toBeInstanceOf(Request::class);
});
