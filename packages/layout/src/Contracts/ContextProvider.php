<?php

declare(strict_types=1);

namespace Markommerce\Layout\Contracts;

interface ContextProvider
{
    public function provide(array $props): object;
}
