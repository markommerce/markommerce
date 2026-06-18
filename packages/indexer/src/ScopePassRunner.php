<?php

declare(strict_types=1);

namespace Markommerce\Indexer;

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Signature\ScopeSignature;

class ScopePassRunner
{
    public function __construct(
        private ScopeContext $scopeContext,
    ) {}

    /**
     * Run $fn once with null (base/global pass) and once per ScopeSignature.
     * The full ambient ScopeContext state is saved before and restored after
     * (including on exception).
     *
     * @param list<ScopeSignature> $signatures
     * @throws UnknownAxisException|ScopeContextException
     */
    public function each(
        array $signatures,
        callable $fn,
    ): void {
        $saved = $this->scopeContext->state();

        try {
            // Base pass — clear all axes, signal with null
            $this->scopeContext->clearAll();
            $fn(null);

            // Scoped passes — one per signature
            foreach ($signatures as $signature) {
                $this->scopeContext->clearAll();
                foreach ($signature->axes() as $axis) {
                    $this->scopeContext->in($axis, (string) $signature->get($axis));
                }
                $fn($signature);
            }
        } finally {
            // Restore the full ambient state
            $this->scopeContext->clearAll();
            foreach ($saved as $axis => $path) {
                $this->scopeContext->in($axis, $path);
            }
        }
    }
}
