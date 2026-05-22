<?php

declare(strict_types=1);

use Markommerce\LayoutDemo\Service\DefaultLabelFormatter;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;

return [
    LabelFormatterInterface::class => DefaultLabelFormatter::class,
];
