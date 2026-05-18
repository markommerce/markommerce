<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Component;

use Marko\Layout\Attributes\Component;

#[Component(template: 'theme-blank-demo::showcase', handle: 'default', slot: 'content')]
class ThemeBlankShowcaseComponent {}
