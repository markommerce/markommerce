<?php

declare(strict_types=1);

use Markommerce\FrontendDemo\Component\DemoCounterComponent;
use Markommerce\FrontendDemo\Controller\DemoController;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;

return new Layout(
    handle: [DemoController::class, 'index'],
    extends: null,
    context: [],
    slots: [
        'content' => [
            new Place(
                component: DemoCounterComponent::class,
                name: 'frontend_demo.counter',
                props: [],
                slots: [],
                template: 'frontend-demo::counter',
            ),
        ],
    ],
    template: 'frontend-demo::layout/base',
);
