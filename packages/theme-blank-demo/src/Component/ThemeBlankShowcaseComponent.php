<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Component;

use Marko\Layout\Attributes\Component;
use Markommerce\ThemeBlankDemo\Controller\ThemeBlankDemoController;

#[Component(
    template: 'theme-blank-demo::showcase',
    handle: [ThemeBlankDemoController::class, 'index'],
    slot: 'content',
)]
class ThemeBlankShowcaseComponent {}
