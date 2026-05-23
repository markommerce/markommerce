<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Operation\Remove;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;

return new Layout(
    handle: 'layout_demo_child',
    extends: null,
    inherits: LayoutDemoController::class . '::show',
    operations: [
        new Remove(name: 'layout_demo.gallery_footer'),
    ],
);
