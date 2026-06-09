<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Sorting;

use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

/**
 * Builds the market-aware COALESCE sort expression for indexed price sort orders.
 *
 * Injection-safety invariant: the COALESCE expression is built exclusively from
 * code-defined constants and scope-registry-derived market identifiers — never
 * from raw request input — making it safe to interpolate into a raw SQL fragment.
 */
readonly class MarketScopedPriceExpression
{
    private const string PRICE_INDEX_TABLE = 'catalog_product_price_index';

    private const string AMOUNT_COLUMN = 'catalog_product_price_index.amount';

    private const string MARKET_AXIS = 'market';

    public function __construct(
        private ScopeRegistryInterface $scopeRegistry,
        private ScopeContext $scopeContext,
    ) {}

    /**
     * Return sort fields for the given direction, using a market-scoped COALESCE
     * expression when a non-default market scope is active, or falling back to
     * the plain amount column when no market is active.
     *
     * @return list<SortField>
     */
    public function sortFields(SortDirection $direction): array
    {
        $marketPath = $this->resolveActiveMarketPath();

        if ($marketPath === null) {
            return [
                new SortField(
                    column: self::AMOUNT_COLUMN,
                    direction: $direction,
                    nulls: NullsPlacement::Last,
                ),
            ];
        }

        // Injection-safety: $marketPath is derived from the scope registry configuration
        // (a code-defined data structure), never from raw user input.
        $expression = sprintf(
            "COALESCE((%s.scopes->'%s'->>'amount')::numeric, %s.amount)",
            self::PRICE_INDEX_TABLE,
            $marketPath,
            self::PRICE_INDEX_TABLE,
        );

        return [
            new SortField(
                column: self::AMOUNT_COLUMN,
                direction: $direction,
                expression: $expression,
                nulls: NullsPlacement::Last,
            ),
        ];
    }

    /**
     * Returns the active market scope path (e.g. "market:us") or null when
     * no market axis is configured or no market scope is active.
     */
    private function resolveActiveMarketPath(): ?string
    {
        try {
            $axis        = $this->scopeRegistry->getAxis(self::MARKET_AXIS);
            $activeScope = $this->scopeContext->get(self::MARKET_AXIS);

            if ($activeScope === null || $activeScope === $axis->default) {
                return null;
            }

            return self::MARKET_AXIS . ':' . $activeScope;
        } catch (UnknownAxisException) {
            return null;
        }
    }
}
