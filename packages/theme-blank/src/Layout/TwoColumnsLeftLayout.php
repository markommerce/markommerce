<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlank\Layout;

use Marko\Layout\Attributes\Component;

#[Component(template: 'theme-blank::layout/2columns-left', slots: ['content', 'sidebar-left'])]
class TwoColumnsLeftLayout {}
