<?php

declare(strict_types=1);

use Markommerce\Layout\Cache\ArtifactReader;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Layout\Runtime\RendererInterface;

return [
    'bindings' => [
        ArtifactReaderInterface::class => ArtifactReader::class,
        RendererInterface::class => Renderer::class,
        MarkommerceLayoutMiddleware::class => MarkommerceLayoutMiddleware::class,
    ],
    'globalMiddleware' => [
        ['class' => MarkommerceLayoutMiddleware::class, 'priority' => 30],
    ],
];
