<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Exception\LayoutException;

interface CompilerInterface
{
    /**
     * Run the full compile pipeline: discover → resolve → validate → build trees.
     *
     * @return array<string, PreparedTree>
     *
     * @throws LayoutException
     */
    public function compile(): array;
}
