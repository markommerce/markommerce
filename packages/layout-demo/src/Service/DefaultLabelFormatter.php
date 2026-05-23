<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Service;

class DefaultLabelFormatter implements LabelFormatterInterface
{
    public function format(string $label): string
    {
        return strtoupper($label);
    }
}
