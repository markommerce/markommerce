<?php

declare(strict_types=1);

namespace Markommerce\Layout\Runtime;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;

readonly class ResolutionContext
{
    /**
     * @param array<string, string> $routeParams
     * @param array<string, object> $contextMap
     */
    public function __construct(
        public Request $request,
        public array $routeParams,
        public array $contextMap,
        public mixed $iterationItem,
        public mixed $parentData,
        public ?ContainerInterface $container,
        public string $placementChain,
    ) {}
}
