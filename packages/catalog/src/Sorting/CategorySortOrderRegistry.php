<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Sorting;

class CategorySortOrderRegistry
{
    /** @var list<array{priority: int, order: CategorySortOrderInterface}> */
    private array $registered = [];

    public function register(
        CategorySortOrderInterface $categorySortOrder,
        int $priority = 0,
    ): void
    {
        if ($this->has($categorySortOrder->key())) {
            return;
        }

        $this->registered[] = ['priority' => $priority, 'order' => $categorySortOrder];
    }

    /** @return list<CategorySortOrderInterface> */
    public function all(): array
    {
        $sorted = $this->registered;
        usort($sorted, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_map(fn (array $entry): CategorySortOrderInterface => $entry['order'], $sorted);
    }

    public function get(string $key): ?CategorySortOrderInterface
    {
        $entry = array_find(
            $this->registered,
            fn (array $entry): bool => $entry['order']->key() === $key,
        );

        return $entry !== null ? $entry['order'] : null;
    }

    public function has(string $key): bool
    {
        return array_any(
            $this->registered,
            fn (array $entry): bool => $entry['order']->key() === $key,
        );
    }
}
