<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Layout;

use Marko\Layout\Attributes\Component;

#[Component(template: 'frontend-demo::layout/base', slots: ['content'])]
class DemoLayout {}
