<?php

declare(strict_types=1);

namespace Markommerce\Pricing;

use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\Money;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Resolver\ScopeResolver;

class PriceResolver implements PriceResolverInterface
{
    public function __construct(
        private ScopeResolver $scopeResolver,
        private ScopeContext $scopeContext,
        private CurrencyResolver $currencyResolver,
    ) {}

    /**
     * @throws PriceUnavailableException|ScopeContextException|UnknownAxisException
     */
    public function resolve(PriceContext $context): Money
    {
        $previous = $this->scopeContext->get('market');

        try {
            if ($context->market !== null) {
                $this->scopeContext->in('market', $context->market);
            }

            /** @var string|null $amount */
            $amount = $this->scopeResolver->resolved($context->product, 'priceAmount');
            $currency = $this->currencyResolver->base();
        } finally {
            if ($previous === null) {
                $this->scopeContext->clear('market');
            } else {
                $this->scopeContext->in('market', $previous);
            }
        }

        if ($amount === null) {
            throw PriceUnavailableException::forContext($context);
        }

        return Money::of($amount, $currency);
    }
}
