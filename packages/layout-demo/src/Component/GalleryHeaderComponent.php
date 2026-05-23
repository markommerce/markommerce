<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Component;

use Markommerce\LayoutDemo\Data\GalleryHeaderData;
use Markommerce\LayoutDemo\Entity\GalleryEntity;

class GalleryHeaderComponent
{
    public function data(GalleryEntity $gallery): GalleryHeaderData
    {
        return new GalleryHeaderData(title: $gallery->title);
    }
}
