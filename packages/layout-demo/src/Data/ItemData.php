<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Data;

readonly class ItemData
{
    public function __construct(
        public int $id,
        public string $label,
        public string $formattedLabel,
        public string $galleryTitle,
    ) {}
}
