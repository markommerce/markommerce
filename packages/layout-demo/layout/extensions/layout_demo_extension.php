<?php

declare(strict_types=1);

use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\FeaturedBadgeComponent;
use Markommerce\LayoutDemo\Component\GalleryWrapperDecorator;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;

return new LayoutExtension(
    handle: [LayoutDemoController::class, 'show'],
    operations: [
        new InsertBefore(
            anchorName: 'layout_demo.gallery',
            placement: new Place(
                component: FeaturedBadgeComponent::class,
                name: 'layout_demo.featured_badge',
                props: [],
                slots: [],
                template: 'layout-demo::featured-badge',
            ),
        ),
        new WrapWith(
            name: 'layout_demo.gallery_header',
            decorator: GalleryWrapperDecorator::class,
        ),
        new MergeProps(
            name: 'layout_demo.gallery_footer',
            props: ['class' => 'highlighted'],
        ),
    ],
    priority: 0,
);
