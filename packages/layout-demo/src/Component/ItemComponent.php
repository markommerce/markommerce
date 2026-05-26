<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Component;

use Markommerce\LayoutDemo\Data\ItemData;
use Markommerce\LayoutDemo\Entity\Item;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;

class ItemComponent
{
    public function __construct(
        private LabelFormatterInterface $labelFormatter,
    ) {}

    public function data(
        Item $item,
        string $galleryTitle,
        LabelFormatterInterface $labelFormatter,
    ): ItemData {
        return new ItemData(
            id: $item->id,
            label: $item->label,
            formattedLabel: $labelFormatter->format($item->label),
            galleryTitle: $galleryTitle,
        );
    }
}
