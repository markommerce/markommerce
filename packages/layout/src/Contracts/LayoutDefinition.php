<?php

declare(strict_types=1);

namespace Markommerce\Layout\Contracts;

use Markommerce\Layout\Layout;

interface LayoutDefinition
{
    public static function define(): Layout;
}
