<?php

declare(strict_types=1);

use Markommerce\Catalog\Pricing\Contracts\PriceContributorInterface;
use Markommerce\Catalog\Pricing\PriceBatch;
use Markommerce\Catalog\Pricing\PriceContributorRegistry;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class StubContributor implements PriceContributorInterface
{
    public function __construct(public readonly string $label) {}

    public function contribute(PriceBatch $batch): void {}
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers a contributor and returns it from all', function (): void {
    $registry    = new PriceContributorRegistry();
    $contributor = new StubContributor('a');
    $registry->register($contributor);

    expect($registry->all())->toBe([$contributor]);
});

it('orders contributors by ascending priority', function (): void {
    $registry = new PriceContributorRegistry();
    $high     = new StubContributor('high');
    $low      = new StubContributor('low');

    $registry->register($high, 10);
    $registry->register($low, 1);

    $all = $registry->all();

    expect($all[0])->toBe($low)
        ->and($all[1])->toBe($high);
});

it('preserves registration order for contributors of equal priority', function (): void {
    $registry = new PriceContributorRegistry();
    $first    = new StubContributor('first');
    $second   = new StubContributor('second');

    $registry->register($first, 5);
    $registry->register($second, 5);

    $all = $registry->all();

    expect($all[0])->toBe($first)
        ->and($all[1])->toBe($second);
});
