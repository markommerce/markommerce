<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Routing\Http\Request;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeResolutionException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Throwable;

class ScopeResolutionPipeline
{
    public function __construct(
        private readonly ScopeRegistryInterface $scopeRegistry,
        private readonly ScopeContext $scopeContext,
        private readonly ScopeResolverChainFactory $scopeResolverChainFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Run the scope resolution pipeline for every registered axis.
     *
     * Does not throw — all resolver exceptions are caught, wrapped in
     * `ScopeResolutionException`, logged, and the chain continues.
     *
     * @param string $channel One of the ScopeResolutionContext::CHANNEL_* constants.
     */
    public function run(
        Request $request,
        string $channel,
    ): void {
        /** @var array<string, string> $resolved */
        $resolved = [];

        foreach ($this->scopeRegistry->listAxes() as $axisName) {
            $axis = $this->scopeRegistry->getAxis($axisName);
            $chain = $this->scopeResolverChainFactory->for($axisName);
            $accepted = null;

            foreach ($chain as $resolver) {
                $context = new ScopeResolutionContext(
                    request: $request,
                    registry: $this->scopeRegistry,
                    resolved: $resolved,
                    channel: $channel,
                );

                try {
                    $path = $resolver->resolve($axis, $context);
                } catch (Throwable $throwable) {
                    $exception = ScopeResolutionException::resolverFailed(
                        resolverClass: $resolver::class,
                        axisName: $axisName,
                        previous: $throwable,
                    );

                    if ($this->logger !== null) {
                        $this->logger->error($exception->getMessage(), ['exception' => $exception]);
                    }

                    continue;
                }

                if ($path === null) {
                    continue;
                }

                if (!$axis->hierarchy->exists($path)) {
                    $exception = ScopeResolutionException::invalidPath(
                        resolverClass: $resolver::class,
                        axisName: $axisName,
                        path: $path,
                    );

                    if ($this->logger !== null) {
                        $this->logger->error($exception->getMessage(), ['exception' => $exception]);
                    }

                    continue;
                }

                $accepted = $path;
                break;
            }

            $resolved[$axisName] = $accepted ?? $axis->default;
            $this->scopeContext->in($axisName, $resolved[$axisName]);
        }
    }

    /**
     * Clear all axis scopes from the ScopeContext.
     *
     * Called by lifecycle hooks in their finally blocks to ensure a fresh
     * ScopeContext at every HTTP request / CLI command / queue job boundary.
     */
    public function clear(): void
    {
        $this->scopeContext->clearAll();
    }
}
