<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Component;

use Markommerce\LayoutDemo\Data\GalleryData;
use Markommerce\LayoutDemo\Entity\GalleryEntity;

class GalleryComponent
{
    public function data(
        GalleryEntity $gallery,
        int $page,
    ): GalleryData
    {
        return new GalleryData(
            title: $gallery->title,
            page: $page,
            items: $gallery->items,
        );
    }
}
