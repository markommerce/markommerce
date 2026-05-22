<?php

declare(strict_types=1);

use Markommerce\LayoutDemo\Service\DefaultLabelFormatter;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;

return [
    'bindings' => [
        LabelFormatterInterface::class => DefaultLabelFormatter::class,
    ],
];
