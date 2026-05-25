<?php

declare(strict_types=1);

namespace Markommerce\Layout\Runtime;

use Marko\Routing\Http\Request;
use Markommerce\Layout\Cache\PreparedTree;
use RuntimeException;

interface RendererInterface
{
    /**
     * Render the prepared tree into an HTML string.
     *
     * @param array<string, string> $routeParams
     * @throws RuntimeException
     */
    public function render(
        PreparedTree $tree,
        Request $request,
        array $routeParams,
    ): string;
}
