<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Data;

use Markommerce\LayoutDemo\Entity\Item;

readonly class GalleryData
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        public string $title,
        public int $page,
        public array $items,
    ) {}
}
