<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Controller;

use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Markommerce\ThemeBlankDemo\Layout\ThemeBlankDemoLayout;
use Markommerce\ThemeBlankDemo\Middleware\EnsureThemeBlankDemoEnabledMiddleware;

#[Layout(ThemeBlankDemoLayout::class)]
class ThemeBlankDemoController
{
    #[Get('/markommerce/_demo/theme-blank')]
    #[Middleware([EnsureThemeBlankDemoEnabledMiddleware::class])]
    public function index(): void {}
}
