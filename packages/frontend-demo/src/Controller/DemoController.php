<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Markommerce\FrontendDemo\Middleware\EnsureFrontendDemoEnabledMiddleware;

class DemoController
{
    #[Get('/markommerce/_demo')]
    #[Middleware([EnsureFrontendDemoEnabledMiddleware::class])]
    public function index(): void {}
}
