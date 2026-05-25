<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\SitewideNoticeComponent;

return new Layout(
    handle: 'default',
    extends: null,
    slots: [
        'content' => [
            new Place(
                component: SitewideNoticeComponent::class,
                name: 'default.sitewide_notice',
                props: [],
                slots: [],
                template: 'layout-demo::sitewide-notice',
            ),
        ],
    ],
);
