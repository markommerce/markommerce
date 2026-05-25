<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Scope\Queue\JobScopeWrapper;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;

// ─── Fake Pipeline ───────────────────────────────────────────────────────────

/**
 * A recording fake for ScopeResolutionPipeline that tracks calls in order.
 */
function makeFakePipeline(): object
{
    return new class () extends ScopeResolutionPipeline
    {
        /** @var list<string> */
        public array $calls = [];

        public function __construct()
        {
            // skip parent constructor
        }

        public function run(
            Request $request,
            string $channel,
        ): void
        {
            $this->calls[] = 'run:' . $channel;
        }

        public function clear(): void
        {
            $this->calls[] = 'clear';
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('withScope defensively clears the pipeline before resolving', function (): void {
    $pipeline = makeFakePipeline();
    $wrapper = new JobScopeWrapper($pipeline);

    $wrapper->withScope(fn () => null);

    expect($pipeline->calls[0])->toBe('clear');
});

it('withScope runs the pipeline with queue channel', function (): void {
    $pipeline = makeFakePipeline();
    $wrapper = new JobScopeWrapper($pipeline);

    $wrapper->withScope(fn () => null);

    expect($pipeline->calls)->toContain('run:queue');
});

it('withScope uses a SyntheticRequest as the request', function (): void {
    $capturedRequest = null;

    $recordingPipeline = new class ($capturedRequest) extends ScopeResolutionPipeline
    {
        public function __construct(private mixed &$capturedRequest)
        {
            // skip parent constructor
        }

        public function run(
            Request $request,
            string $channel,
        ): void
        {
            $this->capturedRequest = $request;
        }

        public function clear(): void {}
    };

    $wrapper = new JobScopeWrapper($recordingPipeline);
    $wrapper->withScope(fn () => null);

    expect($capturedRequest)->toBeInstanceOf(Request::class);
});

it('withScope returns the value produced by the wrapped callable', function (): void {
    $pipeline = makeFakePipeline();
    $wrapper = new JobScopeWrapper($pipeline);

    $result = $wrapper->withScope(fn () => 'expected-value');

    expect($result)->toBe('expected-value');
});

it('withScope calls pipeline clear after the callable returns normally', function (): void {
    $pipeline = makeFakePipeline();
    $wrapper = new JobScopeWrapper($pipeline);

    $wrapper->withScope(fn () => 'ok');

    // calls sequence: clear (defensive), run:queue, clear (finally)
    $lastCall = $pipeline->calls[array_key_last($pipeline->calls)];
    expect($lastCall)->toBe('clear');
});

it('withScope rethrows the original throwable when the callable throws', function (): void {
    $pipeline = makeFakePipeline();
    $wrapper = new JobScopeWrapper($pipeline);
    $original = new RuntimeException('original error');

    expect(fn () => $wrapper->withScope(function () use ($original): void {
        throw $original;
    }))->toThrow(RuntimeException::class, 'original error');
});

it('withScope calls pipeline clear after the callable throws', function (): void {
    $pipeline = makeFakePipeline();
    $wrapper = new JobScopeWrapper($pipeline);

    try {
        $wrapper->withScope(function (): void {
            throw new RuntimeException('job failed');
        });
    } catch (Throwable) {
        // expected
    }

    $lastCall = $pipeline->calls[array_key_last($pipeline->calls)];
    expect($lastCall)->toBe('clear');
});

it('the class carries no Plugin attribute (verified via reflection)', function (): void {
    $reflection = new ReflectionClass(JobScopeWrapper::class);

    $pluginAttributes = $reflection->getAttributes();
    $pluginAttributeNames = array_map(
        fn (ReflectionAttribute $attr) => $attr->getName(),
        $pluginAttributes,
    );

    $hasPluginAttribute = array_any(
        $pluginAttributeNames,
        fn (string $name) => str_contains($name, 'Plugin'),
    );

    expect($hasPluginAttribute)->toBeFalse();
});
