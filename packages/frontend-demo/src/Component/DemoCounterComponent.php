<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Component;

use Marko\Layout\Attributes\Component;

#[Component(template: 'frontend-demo::counter', handle: 'default', slot: 'content')]
class DemoCounterComponent {}
