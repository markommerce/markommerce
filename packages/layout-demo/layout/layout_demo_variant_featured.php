<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\FeaturedCalloutComponent;

return new Layout(
    handle: 'layout_demo.variant.featured',
    extends: null,
    slots: [
        'content' => [
            new Place(
                component: FeaturedCalloutComponent::class,
                name: 'layout_demo.variant.featured_callout',
                props: [],
                slots: [],
                template: 'layout-demo::featured-callout',
            ),
        ],
    ],
    operations: [
        // Remove the default-handle's sitewide notice from this dynamic overlay tree.
        // Dynamic handle trees must not contain placement names already present in the base tree.
        new Remove(name: 'default.sitewide_notice'),
    ],
);
