<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Context;

use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\LayoutDemo\Entity\GalleryEntity;
use Markommerce\LayoutDemo\Entity\Item;

class GalleryContextProvider implements ContextProvider
{
    /**
     * @param array<string, mixed> $props
     */
    public function provide(array $props): object
    {
        $id = (int) ($props['gallery'] ?? 1);

        $items = match ($id) {
            2 => [
                new Item(id: 1, label: 'Second Gallery Item A'),
                new Item(id: 2, label: 'Second Gallery Item B'),
                new Item(id: 3, label: 'Second Gallery Item C'),
            ],
            3 => [
                new Item(id: 1, label: 'Third Gallery Item A'),
                new Item(id: 2, label: 'Third Gallery Item B'),
                new Item(id: 3, label: 'Third Gallery Item C'),
            ],
            default => [
                new Item(id: 1, label: 'Alpha'),
                new Item(id: 2, label: 'Beta'),
                new Item(id: 3, label: 'Gamma'),
            ],
        };

        $title = match ($id) {
            2 => 'Gallery Two',
            3 => 'Gallery Three',
            default => 'Gallery One',
        };

        return new GalleryEntity(title: $title, items: $items);
    }
}
