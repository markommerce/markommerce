<?php

declare(strict_types=1);

namespace Markommerce\Scope\Plugins;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Markommerce\Scope\Resolver\Resolution\SyntheticRequest;

/**
 * Resolves scope before any CLI command executes and clears it afterward.
 *
 * Known limitation: `#[After]` does NOT run when `execute()` throws (Marko plugin
 * chain limitation). The `beforeExecute` hook defensively clears state to compensate.
 * CLI processes are short-lived (one command per process invocation), so a leaked
 * ScopeContext on uncaught command failure is process-local and lost when the process
 * dies.
 */
#[Plugin(target: CommandInterface::class)]
readonly class ScopeResolutionCommandPlugin
{
    public function __construct(
        private ScopeResolutionPipeline $scopeResolutionPipeline,
    ) {}

    #[Before(method: 'execute')]
    public function beforeExecute(
        Input $input,
        Output $output,
    ): void {
        $this->scopeResolutionPipeline->clear();
        $this->scopeResolutionPipeline->run(SyntheticRequest::create(), ScopeResolutionContext::CHANNEL_CLI);
    }

    #[After(method: 'execute')]
    public function afterExecute(
        int $result,
        Input $input,
        Output $output,
    ): int {
        $this->scopeResolutionPipeline->clear();

        return $result;
    }
}
