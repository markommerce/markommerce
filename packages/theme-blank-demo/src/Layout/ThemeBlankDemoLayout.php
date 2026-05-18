<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Layout;

use Marko\Layout\Attributes\Component;

#[Component(template: 'theme-blank-demo::layout/base', slots: ['content'])]
class ThemeBlankDemoLayout {}
