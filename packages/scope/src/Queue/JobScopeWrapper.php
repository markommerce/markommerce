<?php

declare(strict_types=1);

namespace Markommerce\Scope\Queue;

use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Markommerce\Scope\Resolver\Resolution\SyntheticRequest;
use Throwable;

/**
 * Manual opt-in helper that wraps queue job logic with scope resolution.
 *
 * Marko's `Worker` deserializes jobs and invokes `handle()` directly, bypassing
 * the container — plugin-based auto-resolution is not possible. Users must
 * explicitly call `JobScopeWrapper::withScope()` inside `handle()`.
 *
 * Usage:
 * ```php
 * public function handle(): void
 * {
 *     $this->jobScopeWrapper->withScope(fn () => $this->doActualWork());
 * }
 * ```
 */
readonly class JobScopeWrapper
{
    public function __construct(
        private ScopeResolutionPipeline $scopeResolutionPipeline,
    ) {}

    /**
     * Resolve scope for the current queue job and execute the given callable.
     *
     * Defensively clears any previously-leaked scope first, runs the pipeline
     * with the queue channel, then ensures scope is cleared in a finally block.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     * @throws Throwable rethrows whatever $work throws after clearing scope
     */
    public function withScope(callable $work): mixed
    {
        $this->scopeResolutionPipeline->clear();
        $this->scopeResolutionPipeline->run(SyntheticRequest::create(), ScopeResolutionContext::CHANNEL_QUEUE);

        try {
            return $work();
        } finally {
            $this->scopeResolutionPipeline->clear();
        }
    }
}
