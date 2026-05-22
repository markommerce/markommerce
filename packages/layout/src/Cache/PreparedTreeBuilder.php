<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Compiler\ResolvedPlace;
use Markommerce\Layout\Compiler\ResolvedRepeatSlot;

class PreparedTreeBuilder
{
    public function build(ResolvedLayout $resolvedLayout): PreparedTree
    {
        return new PreparedTree(
            handleKey: $resolvedLayout->handleKey,
            template: $resolvedLayout->template,
            slots: $this->buildSlots($resolvedLayout->slots),
            context: $resolvedLayout->context,
        );
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     */
    private function buildSlots(array $slots): array
    {
        $prepared = [];
        foreach ($slots as $key => $value) {
            if ($value instanceof ResolvedRepeatSlot) {
                $prepared[$key] = new PreparedRepeatSlot(
                    dataKey: $value->dataKey,
                    yields: $value->yields,
                    as: $value->as,
                    children: array_map([$this, 'buildPlace'], $value->children),
                );
            } else {
                $prepared[$key] = array_map([$this, 'buildPlace'], $value);
            }
        }
        return $prepared;
    }

    private function buildPlace(ResolvedPlace $resolvedPlace): PreparedPlace
    {
        return new PreparedPlace(
            component: $resolvedPlace->component,
            name: $resolvedPlace->name,
            props: $resolvedPlace->props,
            slots: $this->buildSlots($resolvedPlace->slots),
            decorators: $resolvedPlace->decorators,
        );
    }
}
