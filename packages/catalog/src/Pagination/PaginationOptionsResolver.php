<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Markommerce\Catalog\Config\CatalogPaginationConfig;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

class PaginationOptionsResolver
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
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

        /** @var list<string> $allowedSorts */
        $allowedSorts = $this->configResolver->resolved(CatalogPaginationConfig::class, 'allowedSorts');

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
        $resolvedSort = $this->resolveSort($sort, $defaultSort, $allowedSorts);
        $strategyKind = $this->resolveStrategyKind($strategyString);
        $presentation = $this->resolvePresentation($presentationString);
        $countMode = $this->resolveCountMode($countModeString);

        $this->validateCombination($presentation, $strategyKind);

        $sortObj = new Sort(new SortField($resolvedSort, SortDirection::Ascending));
        $pageRequest = PageRequest::first($resolvedSize, $sortObj);

        return new ResolvedPaginationOptions(
            pageRequest: $pageRequest,
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
     * @param list<string> $allowedSorts
     *
     * @throws InvalidPaginationConfigException
     */
    private function resolveSort(
        ?string $sort,
        string $defaultSort,
        array $allowedSorts,
    ): string {
        if ($sort === null) {
            return $defaultSort;
        }

        if (!in_array($sort, $allowedSorts, true)) {
            throw InvalidPaginationConfigException::forInvalidSort(
                $sort,
                implode(', ', $allowedSorts),
            );
        }

        return $sort;
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
