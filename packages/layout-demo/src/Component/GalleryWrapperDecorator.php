<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Component;

use Markommerce\Layout\Contracts\DecoratorInterface;

class GalleryWrapperDecorator implements DecoratorInterface
{
    public function template(): string
    {
        return '<div class="gallery-wrapper">{slot inner}</div>';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function wrap(
        string $innerHtml,
        array $data = [],
    ): string
    {
        return str_replace('{slot inner}', $innerHtml, $this->template());
    }
}
