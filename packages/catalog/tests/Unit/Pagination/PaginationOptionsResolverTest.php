<?php

declare(strict_types=1);

use Markommerce\Catalog\Config\CatalogPaginationConfig;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Sort\SortDirection;

// ─── Stubs ────────────────────────────────────────────────────────────────────

/**
 * Builds a ConfigResolverInterface stub that returns CatalogPaginationConfig field values.
 *
 * @param array<string, mixed> $overrides Field name → value overrides
 */
function makePaginationConfigResolver(array $overrides = []): ConfigResolverInterface
{
    $defaults = [
        'defaultPageSize'  => 24,
        'allowedPageSizes' => [12, 24, 48, 96],
        'maxPageSize'      => 96,
        'strategy'         => 'offset',
        'presentation'     => 'numbered',
        'countMode'        => 'exact',
        'maxPageDepth'     => 100,
        'defaultSort'      => 'position',
        'enabledSorts'     => [],
        'viewAllThreshold' => 0,
        'countCacheTtl'    => 0,
    ];

    $values = array_merge($defaults, $overrides);

    return new class ($values) implements ConfigResolverInterface
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values) {}

        public function resolved(
            string $configClass,
            string $field,
        ): mixed {
            return $this->values[$field] ?? null;
        }
    };
}

/**
 * Builds a CategorySortOrderRegistry pre-populated with a position order.
 * Optionally adds more orders.
 *
 * @param list<ColumnSortOrder> $extras Extra sort orders to register
 */
function makeRegistryWithPosition(array $extras = []): CategorySortOrderRegistry
{
    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    foreach ($extras as $extra) {
        $registry->register($extra, 1);
    }

    return $registry;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it selects the default position order when no sort is requested', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(),
        makeRegistryWithPosition(),
    );

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options)->toBeInstanceOf(ResolvedPaginationOptions::class)
        ->and($options->sortOrder->key())->toBe('position')
        ->and($options->size)->toBe(24)
        ->and($options->page)->toBe(1);
});

it('it falls back to position when the configured default sort is not registered', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(['defaultSort' => 'nonexistent']),
        makeRegistryWithPosition(),
    );

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options->sortOrder->key())->toBe('position');
});

it('it selects the registered sort order matching the requested key', function (): void {
    $nameOrder = new ColumnSortOrder(
        key: 'name',
        label: 'Name',
        column: 'catalog_products.name',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(),
        makeRegistryWithPosition([$nameOrder]),
    );

    $options = $resolver->resolve(page: null, size: null, sort: 'name');

    expect($options->sortOrder->key())->toBe('name');
});

it('it throws an invalid sort exception listing available keys for an unregistered key', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(),
        makeRegistryWithPosition(),
    );

    expect(fn () => $resolver->resolve(page: null, size: null, sort: 'nonexistent'))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('it excludes orders not in a non-empty enabledSorts gate', function (): void {
    $nameOrder = new ColumnSortOrder(
        key: 'name',
        label: 'Name',
        column: 'catalog_products.name',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(['enabledSorts' => ['position']]),
        makeRegistryWithPosition([$nameOrder]),
    );

    // 'name' is registered but not in enabledSorts — should be rejected
    expect(fn () => $resolver->resolve(page: null, size: null, sort: 'name'))
        ->toThrow(InvalidPaginationConfigException::class);
});

it(
    'it throws a loud keyset-incompatibility exception when a non-keyset order is requested under the keyset strategy',
    function (): void {
        $resolver = new PaginationOptionsResolver(
            makePaginationConfigResolver([
                'strategy'     => 'keyset',
                'presentation' => 'load_more',
                'defaultSort'  => 'position',
            ]),
            makeRegistryWithPosition(),
        );

        // position does not support keyset → must throw
        expect(fn () => $resolver->resolve(page: null, size: null, sort: null))
                ->toThrow(InvalidPaginationConfigException::class);
    },
);

it('it carries the selected sort order and resolved size on the resolved options', function (): void {
    $nameOrder = new ColumnSortOrder(
        key: 'name',
        label: 'Name',
        column: 'catalog_products.name',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(['allowedPageSizes' => [12, 24, 48, 96], 'defaultPageSize' => 24]),
        makeRegistryWithPosition([$nameOrder]),
    );

    $options = $resolver->resolve(page: null, size: 48, sort: 'name');

    expect($options->sortOrder->key())->toBe('name')
        ->and($options->size)->toBe(48);
});

// ─── Pre-existing tests (updated for new API) ─────────────────────────────────

it('clamps a requested size that is not in the allowed list', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(),
        makeRegistryWithPosition(),
    );

    $options = $resolver->resolve(page: null, size: 30, sort: null);

    expect($options->size)->toBe(24);
});

it('never returns a size greater than the configured maximum', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver([
            'allowedPageSizes' => [12, 24, 48, 96, 200],
            'maxPageSize'      => 96,
        ]),
        makeRegistryWithPosition(),
    );

    $options = $resolver->resolve(page: null, size: 200, sort: null);

    expect($options->size)->toBe(24);
});

it('rejects a sort that is not in the allowed list with a loud exception', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver(),
        makeRegistryWithPosition(),
    );

    expect(fn () => $resolver->resolve(page: null, size: null, sort: 'invalid_sort'))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('resolves the offset strategy kind and exact count mode from config', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver([
            'strategy'  => 'offset',
            'countMode' => 'exact',
        ]),
        makeRegistryWithPosition(),
    );

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options->strategyKind)->toBe(PaginationStrategyKind::Offset)
        ->and($options->countMode)->toBe(CountMode::Exact);
});

it('resolves the keyset strategy kind when using a keyset-capable order', function (): void {
    $keysetOrder = new ColumnSortOrder(
        key: 'sku',
        label: 'SKU',
        column: 'catalog_products.sku',
        direction: SortDirection::Ascending,
        supportsKeyset: true,
    );
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver([
            'strategy'     => 'keyset',
            'presentation' => 'load_more',
            'defaultSort'  => 'sku',
        ]),
        makeRegistryWithPosition([$keysetOrder]),
    );

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options->strategyKind)->toBe(PaginationStrategyKind::Keyset);
});

it('rejects an unsupported count mode with a loud exception', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver([
            'countMode' => 'cached',
        ]),
        makeRegistryWithPosition(),
    );

    expect(fn () => $resolver->resolve(page: null, size: null, sort: null))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('rejects the numbered presentation combined with the keyset strategy', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver([
            'strategy'     => 'keyset',
            'presentation' => 'numbered',
        ]),
        makeRegistryWithPosition(),
    );

    expect(fn () => $resolver->resolve(page: null, size: null, sort: null))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('throws PageDepthExceededException when the requested page exceeds the max depth', function (): void {
    $resolver = new PaginationOptionsResolver(
        makePaginationConfigResolver([
            'maxPageDepth' => 100,
        ]),
        makeRegistryWithPosition(),
    );

    expect(fn () => $resolver->resolve(page: 101, size: null, sort: null))
        ->toThrow(PageDepthExceededException::class);
});
