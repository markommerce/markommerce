<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Handle;

use Markommerce\Layout\Attributes\ProvidesHandles;
use Markommerce\Layout\Contracts\HandleProvider;

#[ProvidesHandles(handles: ['layout_demo.variant.featured'])]
class GalleryVariantHandleProvider implements HandleProvider
{
    /**
     * @param array<string, mixed> $props
     * @return list<string>
     */
    public function provide(array $props): array
    {
        if (($props['variant'] ?? '') === 'featured') {
            return ['layout_demo.variant.featured'];
        }

        return [];
    }
}
