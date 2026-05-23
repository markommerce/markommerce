<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Entity;

readonly class GalleryEntity
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        public string $title,
        public array $items,
    ) {}
}
