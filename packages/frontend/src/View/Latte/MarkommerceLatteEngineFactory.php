<?php

declare(strict_types=1);

namespace Markommerce\Frontend\View\Latte;

use Latte\Engine;
use Marko\Core\Attributes\Preference;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\ViewConfig;

#[Preference(replaces: LatteEngineFactory::class)]
readonly class MarkommerceLatteEngineFactory extends LatteEngineFactory
{
    public function __construct(
        ViewConfig $viewConfig,
        private ViteExtension $viteExtension,
    ) {
        parent::__construct($viewConfig);
    }

    public function create(): Engine
    {
        $engine = parent::create();
        $engine->addExtension($this->viteExtension);

        return $engine;
    }
}
