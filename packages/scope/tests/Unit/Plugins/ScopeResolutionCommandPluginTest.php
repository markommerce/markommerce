<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Scope\Plugins\ScopeResolutionCommandPlugin;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Marko\Routing\Http\Request;

/**
 * Fake pipeline that records method calls without needing real dependencies.
 * Extends ScopeResolutionPipeline and overrides the constructor so no real
 * registry/context/factory are needed in unit tests.
 */
class FakeScopeResolutionPipeline extends ScopeResolutionPipeline
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<Request> */
    public array $runRequests = [];

    public function __construct()
    {
        // Intentionally skip parent::__construct() — this is a test fake.
        // PHP allows calling a child constructor without calling the parent
        // when the parent's properties are not needed.
    }

    public function clear(): void
    {
        $this->calls[] = 'clear';
    }

    public function run(Request $request, string $channel): void
    {
        $this->calls[] = "run:$channel";
        $this->runRequests[] = $request;
    }
}

it('has Plugin attribute targeting CommandInterface', function (): void {
    $reflection = new ReflectionClass(ScopeResolutionCommandPlugin::class);
    $attributes = $reflection->getAttributes(Plugin::class);

    expect($attributes)->toHaveCount(1);

    $plugin = $attributes[0]->newInstance();
    expect($plugin->target)->toBe(CommandInterface::class);
});

it('beforeExecute defensively clears pipeline before resolving', function (): void {
    $fake = new FakeScopeResolutionPipeline();
    $plugin = new ScopeResolutionCommandPlugin($fake);

    $plugin->beforeExecute(new Input([]), new Output(fopen('php://memory', 'w')));

    expect($fake->calls[0])->toBe('clear');
});

it('beforeExecute runs the pipeline with cli channel', function (): void {
    $fake = new FakeScopeResolutionPipeline();
    $plugin = new ScopeResolutionCommandPlugin($fake);

    $plugin->beforeExecute(new Input([]), new Output(fopen('php://memory', 'w')));

    expect($fake->calls)->toContain('run:' . ScopeResolutionContext::CHANNEL_CLI);
});

it('beforeExecute uses a SyntheticRequest as the request', function (): void {
    $fake = new FakeScopeResolutionPipeline();
    $plugin = new ScopeResolutionCommandPlugin($fake);

    $plugin->beforeExecute(new Input([]), new Output(fopen('php://memory', 'w')));

    expect($fake->runRequests)->toHaveCount(1)
        ->and($fake->runRequests[0])->toBeInstanceOf(Request::class);
});

it('afterExecute calls pipeline clear', function (): void {
    $fake = new FakeScopeResolutionPipeline();
    $plugin = new ScopeResolutionCommandPlugin($fake);

    $plugin->afterExecute(0, new Input([]), new Output(fopen('php://memory', 'w')));

    expect($fake->calls)->toContain('clear');
});

it('afterExecute returns the original result unchanged', function (): void {
    $fake = new FakeScopeResolutionPipeline();
    $plugin = new ScopeResolutionCommandPlugin($fake);

    $result = $plugin->afterExecute(42, new Input([]), new Output(fopen('php://memory', 'w')));

    expect($result)->toBe(42);
});

it('it has Before attribute on beforeExecute targeting execute method', function (): void {
    $reflection = new ReflectionClass(ScopeResolutionCommandPlugin::class);
    $method = $reflection->getMethod('beforeExecute');
    $attributes = $method->getAttributes(Before::class);

    expect($attributes)->toHaveCount(1);

    $before = $attributes[0]->newInstance();
    expect($before->method)->toBe('execute');
});

it('it has After attribute on afterExecute targeting execute method', function (): void {
    $reflection = new ReflectionClass(ScopeResolutionCommandPlugin::class);
    $method = $reflection->getMethod('afterExecute');
    $attributes = $method->getAttributes(After::class);

    expect($attributes)->toHaveCount(1);

    $after = $attributes[0]->newInstance();
    expect($after->method)->toBe('execute');
});
