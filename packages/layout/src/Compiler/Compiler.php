<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Exceptions\InvalidLayoutFileException;
use Markommerce\Layout\Exceptions\LayoutException;

class Compiler implements CompilerInterface
{
    public function __construct(
        private LayoutDiscovery $layoutDiscovery,
        private ResolutionPhase $resolutionPhase,
        private ValidationPhase $validationPhase,
        private PreparedTreeBuilder $preparedTreeBuilder,
    ) {}

    /**
     * Run the full compile pipeline: discover → resolve → validate → build trees.
     *
     * @return array<string, PreparedTree>
     *
     * @throws LayoutException|InvalidLayoutFileException
     */
    public function compile(): array
    {
        $discoveryResult = $this->layoutDiscovery->discover();
        $resolvedLayouts = $this->resolutionPhase->resolve($discoveryResult);
        $this->validationPhase->validate($resolvedLayouts);

        $trees = [];
        foreach ($resolvedLayouts as $handleKey => $resolvedLayout) {
            $trees[$handleKey] = $this->preparedTreeBuilder->build($resolvedLayout);
        }

        return $trees;
    }
}
