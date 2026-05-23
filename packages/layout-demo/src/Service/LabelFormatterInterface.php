<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Service;

interface LabelFormatterInterface
{
    public function format(string $label): string;
}
