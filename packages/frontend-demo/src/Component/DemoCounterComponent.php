<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Component;

use Marko\Layout\Attributes\Component;
use Markommerce\FrontendDemo\Controller\DemoController;

#[Component(template: 'frontend-demo::counter', handle: [DemoController::class, 'index'], slot: 'content')]
class DemoCounterComponent {}
