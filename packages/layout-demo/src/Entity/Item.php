<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Entity;

readonly class Item
{
    public function __construct(
        public int $id,
        public string $label,
    ) {}
}
