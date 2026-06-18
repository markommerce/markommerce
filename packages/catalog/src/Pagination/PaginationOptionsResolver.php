<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Markommerce\Catalog\Config\CatalogPaginationConfig;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Exceptions\UnknownSortRequestedException;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Sort\SortDirection;

class PaginationOptionsResolver
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
        private CategorySortOrderRegistry $categorySortOrderRegistry,
    ) {}

    /**
     * @throws InvalidPaginationConfigException|PageDepthExceededException
     */
    public function resolve(
        ?int $page,
        ?int $size,
        ?string $sort,
    ): ResolvedPaginationOptions {
        /** @var int $defaultPageSize */
        $defaultPageSize = $this->configResolver->resolved(CatalogPaginationConfig::class, 'defaultPageSize');

        /** @var list<int> $allowedPageSizes */
        $allowedPageSizes = $this->configResolver->resolved(CatalogPaginationConfig::class, 'allowedPageSizes');

        /** @var int $maxPageSize */
        $maxPageSize = $this->configResolver->resolved(CatalogPaginationConfig::class, 'maxPageSize');

        /** @var int $maxPageDepth */
        $maxPageDepth = $this->configResolver->resolved(CatalogPaginationConfig::class, 'maxPageDepth');

        /** @var string $defaultSort */
        $defaultSort = $this->configResolver->resolved(CatalogPaginationConfig::class, 'defaultSort');

        /** @var list<string> $enabledSorts */
        $enabledSorts = $this->configResolver->resolved(CatalogPaginationConfig::class, 'enabledSorts');

        /** @var string $strategyString */
        $strategyString = $this->configResolver->resolved(CatalogPaginationConfig::class, 'strategy');

        /** @var string $presentationString */
        $presentationString = $this->configResolver->resolved(CatalogPaginationConfig::class, 'presentation');

        /** @var string $countModeString */
        $countModeString = $this->configResolver->resolved(CatalogPaginationConfig::class, 'countMode');

        $resolvedPage = $page ?? 1;

        if ($resolvedPage > $maxPageDepth) {
            throw PageDepthExceededException::forDepth($resolvedPage, $maxPageDepth);
        }

        $resolvedSize = $this->resolveSize($size, $defaultPageSize, $allowedPageSizes, $maxPageSize);
        $strategyKind = $this->resolveStrategyKind($strategyString);
        $presentation = $this->resolvePresentation($presentationString);
        $countMode = $this->resolveCountMode($countModeString);

        $this->validateCombination($presentation, $strategyKind);

        $sortOrder = $this->resolveSortOrder($sort, $defaultSort, $enabledSorts, $strategyKind);

        return new ResolvedPaginationOptions(
            sortOrder: $sortOrder,
            size: $resolvedSize,
            page: $resolvedPage,
            presentation: $presentation,
            strategyKind: $strategyKind,
            countMode: $countMode,
        );
    }

    /**
     * @param list<int> $allowedPageSizes
     */
    private function resolveSize(
        ?int $size,
        int $defaultPageSize,
        array $allowedPageSizes,
        int $maxPageSize,
    ): int {
        if ($size === null || !in_array($size, $allowedPageSizes, true)) {
            return $defaultPageSize;
        }

        if ($size > $maxPageSize) {
            return $defaultPageSize;
        }

        return $size;
    }

    /**
     * @param list<string> $enabledSorts
     *
     * @throws InvalidPaginationConfigException
     */
    private function resolveSortOrder(
        ?string $sort,
        string $defaultSort,
        array $enabledSorts,
        PaginationStrategyKind $strategyKind,
    ): CategorySortOrderInterface {
        if ($sort === null) {
            $order = $this->resolveDefaultSortOrder($defaultSort);
        } else {
            $order = $this->resolveRequestedSortOrder($sort, $enabledSorts);
        }

        if ($strategyKind === PaginationStrategyKind::Keyset && !$order->supportsKeyset()) {
            throw InvalidPaginationConfigException::forKeysetIncompatibleSort($order->key());
        }

        return $order;
    }

    /**
     * Resolves default sort order — falls back to registry default (position or first registered) on miss.
     */
    private function resolveDefaultSortOrder(string $defaultSort): CategorySortOrderInterface
    {
        $order = $this->categorySortOrderRegistry->get($defaultSort);

        if ($order !== null) {
            return $order;
        }

        // Fallback: position or first registered
        $fallback = $this->categorySortOrderRegistry->get('position');

        if ($fallback !== null) {
            return $fallback;
        }

        $all = $this->categorySortOrderRegistry->all();

        if ($all !== []) {
            return $all[0];
        }

        // Registry is empty — return a no-op position placeholder so other validation can still run
        // (keyset check will fire if needed; sort order contract requires at least position)
        return new ColumnSortOrder(
            key: $defaultSort,
            label: $defaultSort,
            column: 'catalog_product_category.position',
            direction: SortDirection::Ascending,
            supportsKeyset: false,
        );
    }

    /**
     * @param list<string> $enabledSorts
     *
     * @throws InvalidPaginationConfigException
     */
    private function resolveRequestedSortOrder(
        string $sort,
        array $enabledSorts,
    ): CategorySortOrderInterface {
        // If enabledSorts is non-empty, apply gate filter first
        if ($enabledSorts !== [] && !in_array($sort, $enabledSorts, true)) {
            $available = $this->getAvailableKeys($enabledSorts);
            throw UnknownSortRequestedException::forRequestedKey($sort, implode(', ', $available));
        }

        $order = $this->categorySortOrderRegistry->get($sort);

        if ($order === null) {
            $available = $this->getAvailableKeys($enabledSorts);
            throw UnknownSortRequestedException::forRequestedKey($sort, implode(', ', $available));
        }

        return $order;
    }

    /**
     * Returns the list of available sort keys, filtered by enabledSorts if non-empty.
     *
     * @param list<string> $enabledSorts
     * @return list<string>
     */
    private function getAvailableKeys(array $enabledSorts): array
    {
        $registeredKeys = array_map(
            fn (CategorySortOrderInterface $order): string => $order->key(),
            $this->categorySortOrderRegistry->all(),
        );

        if ($enabledSorts === []) {
            return $registeredKeys;
        }

        return array_values(array_intersect($registeredKeys, $enabledSorts));
    }

    /**
     * @throws InvalidPaginationConfigException
     */
    private function resolveStrategyKind(string $strategy): PaginationStrategyKind
    {
        $kind = PaginationStrategyKind::tryFrom($strategy);

        if ($kind === null) {
            throw InvalidPaginationConfigException::forUnknownStrategy($strategy);
        }

        return $kind;
    }

    /**
     * @throws InvalidPaginationConfigException
     */
    private function resolvePresentation(string $presentation): PaginationPresentation
    {
        $enum = PaginationPresentation::tryFrom($presentation);

        if ($enum === null) {
            throw InvalidPaginationConfigException::forUnknownPresentation($presentation);
        }

        return $enum;
    }

    /**
     * @throws InvalidPaginationConfigException
     */
    private function resolveCountMode(string $countMode): CountMode
    {
        $enum = CountMode::tryFrom($countMode);

        if ($enum === null) {
            throw InvalidPaginationConfigException::forUnsupportedCountMode($countMode);
        }

        return $enum;
    }

    /**
     * @throws InvalidPaginationConfigException
     */
    private function validateCombination(
        PaginationPresentation $presentation,
        PaginationStrategyKind $strategyKind,
    ): void {
        if ($presentation === PaginationPresentation::Numbered && $strategyKind === PaginationStrategyKind::Keyset) {
            throw InvalidPaginationConfigException::forNumberedKeysetCombination();
        }
    }
}
