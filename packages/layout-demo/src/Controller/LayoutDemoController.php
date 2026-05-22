<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Markommerce\LayoutDemo\Middleware\EnsureLayoutDemoEnabledMiddleware;

class LayoutDemoController
{
    #[Get('/markommerce/_demo/layout/{id}')]
    #[Middleware([EnsureLayoutDemoEnabledMiddleware::class])]
    public function show(): void {}
}
