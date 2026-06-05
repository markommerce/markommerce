<?php

declare(strict_types=1);

use Markommerce\Catalog\Config\CatalogPaginationConfig;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Page\PageRequest;

// ─── Stub ─────────────────────────────────────────────────────────────────────

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
        'allowedSorts'     => ['position', 'name', 'sku', 'price'],
        'viewAllThreshold' => 0,
        'countCacheTtl'    => 0,
    ];

    $values = array_merge($defaults, $overrides);

    return new class ($values) implements ConfigResolverInterface {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values) {}

        public function resolved(string $configClass, string $field): mixed
        {
            return $this->values[$field] ?? null;
        }
    };
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('builds a page request using the configured default size and sort', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver());

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options)->toBeInstanceOf(ResolvedPaginationOptions::class)
        ->and($options->pageRequest)->toBeInstanceOf(PageRequest::class)
        ->and($options->pageRequest->size)->toBe(24)
        ->and($options->pageRequest->position)->toBeNull()
        ->and($options->page)->toBe(1)
        ->and($options->pageRequest->sort->fields[0]->column)->toBe('position');
});

it('clamps a requested size that is not in the allowed list', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver());

    $options = $resolver->resolve(page: null, size: 30, sort: null);

    expect($options->pageRequest->size)->toBe(24);
});

it('never returns a size greater than the configured maximum', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver([
        'allowedPageSizes' => [12, 24, 48, 96, 200],
        'maxPageSize'      => 96,
    ]));

    $options = $resolver->resolve(page: null, size: 200, sort: null);

    expect($options->pageRequest->size)->toBe(24);
});

it('rejects a sort that is not in the allowed list with a loud exception', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver());

    expect(fn () => $resolver->resolve(page: null, size: null, sort: 'invalid_sort'))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('resolves the offset strategy kind and exact count mode from config', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver([
        'strategy'   => 'offset',
        'countMode'  => 'exact',
    ]));

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options->strategyKind)->toBe(PaginationStrategyKind::Offset)
        ->and($options->countMode)->toBe(CountMode::Exact);
});

it('resolves the keyset strategy kind when configured', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver([
        'strategy'     => 'keyset',
        'presentation' => 'load_more',
    ]));

    $options = $resolver->resolve(page: null, size: null, sort: null);

    expect($options->strategyKind)->toBe(PaginationStrategyKind::Keyset);
});

it('rejects an unsupported count mode with a loud exception', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver([
        'countMode' => 'cached',
    ]));

    expect(fn () => $resolver->resolve(page: null, size: null, sort: null))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('rejects the numbered presentation combined with the keyset strategy', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver([
        'strategy'     => 'keyset',
        'presentation' => 'numbered',
    ]));

    expect(fn () => $resolver->resolve(page: null, size: null, sort: null))
        ->toThrow(InvalidPaginationConfigException::class);
});

it('throws PageDepthExceededException when the requested page exceeds the max depth', function (): void {
    $resolver = new PaginationOptionsResolver(makePaginationConfigResolver([
        'maxPageDepth' => 100,
    ]));

    expect(fn () => $resolver->resolve(page: 101, size: null, sort: null))
        ->toThrow(PageDepthExceededException::class);
});
