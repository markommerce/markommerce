<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing;

use Markommerce\Catalog\Pricing\Contracts\PriceContributorInterface;

class PriceContributorRegistry
{
    /** @var list<array{priority: int, contributor: PriceContributorInterface}> */
    private array $registered = [];

    public function register(
        PriceContributorInterface $priceContributor,
        int $priority = 0,
    ): void
    {
        $this->registered[] = ['priority' => $priority, 'contributor' => $priceContributor];
    }

    /** @return list<PriceContributorInterface> */
    public function all(): array
    {
        $sorted = $this->registered;
        usort($sorted, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_map(fn (array $entry): PriceContributorInterface => $entry['contributor'], $sorted);
    }
}
