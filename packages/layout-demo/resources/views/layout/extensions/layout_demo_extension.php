<?php

declare(strict_types=1);

use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\Append;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Prepend;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Operation\Replace;
use Markommerce\Layout\Operation\ReplaceProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\FeaturedBadgeComponent;
use Markommerce\LayoutDemo\Component\GalleryAnnouncementComponent;
use Markommerce\LayoutDemo\Component\GalleryCustomComponent;
use Markommerce\LayoutDemo\Component\GallerySubtitleComponent;
use Markommerce\LayoutDemo\Component\GallerySummaryComponent;
use Markommerce\LayoutDemo\Component\GalleryWrapperDecorator;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;

return new LayoutExtension(
    handle: [LayoutDemoController::class, 'show'],
    operations: [
        // Insert a badge before each repeat-slot item
        new InsertBefore(
            anchorName: 'layout_demo.item',
            placement: new Place(
                component: FeaturedBadgeComponent::class,
                name: 'layout_demo.featured_badge',
                props: [],
                slots: [],
                template: 'layout-demo::featured-badge',
            ),
        ),
        // Insert a subtitle after the gallery header
        new InsertAfter(
            anchorName: 'layout_demo.gallery_header',
            placement: new Place(
                component: GallerySubtitleComponent::class,
                name: 'layout_demo.gallery_subtitle',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-subtitle',
            ),
        ),
        // Wrap the gallery section with a decorator
        new WrapWith(
            name: 'layout_demo.gallery',
            decorator: GalleryWrapperDecorator::class,
        ),
        // Add a CSS class to the footer via prop merge
        new MergeProps(
            name: 'layout_demo.gallery_footer',
            props: ['class' => 'highlighted'],
        ),
        // Replace all props on the notice (adds a class)
        new ReplaceProps(
            name: 'layout_demo.notice',
            props: ['class' => 'extension-notice'],
        ),
        // Replace the placeholder with a custom component
        new Replace(
            name: 'layout_demo.placeholder',
            placement: new Place(
                component: GalleryCustomComponent::class,
                name: 'layout_demo.custom',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-custom',
            ),
        ),
        // Remove the deprecated element entirely
        new Remove(
            name: 'layout_demo.deprecated',
        ),
        // Prepend an announcement to the top of the content slot
        new Prepend(
            slotPath: 'content',
            placement: new Place(
                component: GalleryAnnouncementComponent::class,
                name: 'layout_demo.announcement',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-announcement',
            ),
        ),
        // Append a summary to the bottom of the content slot
        new Append(
            slotPath: 'content',
            placement: new Place(
                component: GallerySummaryComponent::class,
                name: 'layout_demo.summary',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-summary',
            ),
        ),
    ],
    priority: 0,
);
