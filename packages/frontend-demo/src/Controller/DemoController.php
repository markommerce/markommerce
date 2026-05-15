<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Controller;

use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Markommerce\FrontendDemo\Layout\DemoLayout;
use Markommerce\FrontendDemo\Middleware\EnsureFrontendDemoEnabledMiddleware;

#[Layout(DemoLayout::class)]
class DemoController
{
    #[Get('/markommerce/_demo')]
    #[Middleware([EnsureFrontendDemoEnabledMiddleware::class])]
    public function index(): void {}
}
