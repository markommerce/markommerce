<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlank\Layout;

use Marko\Layout\Attributes\Component;

#[Component(template: 'theme-blank::layout/3columns', slots: ['content', 'sidebar-left', 'sidebar-right'])]
class ThreeColumnsLayout {}
