<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlank\Layout;

use Marko\Layout\Attributes\Component;

#[Component(template: 'theme-blank::layout/1column', slots: ['content'])]
class OneColumnLayout {}
