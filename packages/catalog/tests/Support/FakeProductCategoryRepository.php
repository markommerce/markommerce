<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface;

class FakeProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    /** @var array<array{0: int, 1: int}> */
    public array $assignments = [];

    public function assign(int $productId, int $categoryId): bool
    {
        foreach ($this->assignments as $pair) {
            if ($pair[0] === $productId && $pair[1] === $categoryId) {
                return false;
            }
        }

        $this->assignments[] = [$productId, $categoryId];

        return true;
    }

    public function unassign(int $productId, int $categoryId): bool
    {
        $before = count($this->assignments);
        $this->assignments = array_values(array_filter(
            $this->assignments,
            fn (array $p) => !($p[0] === $productId && $p[1] === $categoryId),
        ));

        return count($this->assignments) !== $before;
    }

    /** @return array<int> */
    public function findCategoryIdsForProduct(int $productId): array
    {
        return array_values(array_map(
            fn (array $p): int => $p[1],
            array_filter($this->assignments, fn (array $p) => $p[0] === $productId),
        ));
    }

    /** @return array<int> */
    public function findProductIdsForCategory(int $categoryId): array
    {
        return array_values(array_map(
            fn (array $p): int => $p[0],
            array_filter($this->assignments, fn (array $p) => $p[1] === $categoryId),
        ));
    }
}
