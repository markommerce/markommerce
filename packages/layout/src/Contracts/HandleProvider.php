<?php

declare(strict_types=1);

namespace Markommerce\Layout\Contracts;

interface HandleProvider
{
    /**
     * @param array<string, mixed> $props
     * @return list<string>
     */
    public function provide(array $props): array;
}
