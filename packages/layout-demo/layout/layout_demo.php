<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;
use Markommerce\LayoutDemo\Component\GalleryComponent;
use Markommerce\LayoutDemo\Component\GalleryFooterComponent;
use Markommerce\LayoutDemo\Component\GalleryHeaderComponent;
use Markommerce\LayoutDemo\Component\ItemComponent;
use Markommerce\LayoutDemo\Context\GalleryContextProvider;
use Markommerce\LayoutDemo\Context\GalleryToken;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;
use Markommerce\LayoutDemo\Entity\Item;
use Markommerce\LayoutDemo\Iteration\ItemIteration;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

return new Layout(
    handle: [LayoutDemoController::class, 'show'],
    extends: OneColumnLayout::class,
    context: [
        new Provide(
            token: GalleryToken::class,
            provider: GalleryContextProvider::class,
            props: ['id' => Source::route('id', 'int')],
        ),
    ],
    slots: [
        'content' => [
            new Place(
                component: GalleryHeaderComponent::class,
                name: 'layout_demo.gallery_header',
                props: ['gallery' => Source::context(GalleryToken::class)],
                slots: [],
                template: 'layout-demo::gallery-header',
            ),
            new Place(
                component: GalleryComponent::class,
                name: 'layout_demo.gallery',
                props: [
                    'gallery' => Source::context(GalleryToken::class),
                    'page' => Source::query('page', 1, 'int'),
                ],
                slots: [
                    'items' => Slot::repeat(
                        dataKey: 'items',
                        yields: Item::class,
                        as: ItemIteration::class,
                        children: [
                            new Place(
                                component: ItemComponent::class,
                                name: 'layout_demo.item',
                                props: [
                                    'item' => Source::iterated(ItemIteration::class),
                                    'galleryTitle' => Source::parentData('title', 'string'),
                                    'labelFormatter' => Source::service(LabelFormatterInterface::class),
                                ],
                                slots: [],
                                template: 'layout-demo::item',
                            ),
                        ],
                    ),
                ],
                template: 'layout-demo::gallery',
            ),
            new Place(
                component: GalleryFooterComponent::class,
                name: 'layout_demo.gallery_footer',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-footer',
            ),
        ],
    ],
);
