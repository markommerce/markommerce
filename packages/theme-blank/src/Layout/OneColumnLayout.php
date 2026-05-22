<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlank\Layout;

use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Layout;

class OneColumnLayout implements LayoutDefinition
{
    public static function define(): Layout
    {
        return new Layout(
            handle: null,
            extends: null,
            context: [],
            slots: ['content' => []],
            template: 'theme-blank::layout/1column',
        );
    }
}
