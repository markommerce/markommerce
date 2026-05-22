<?php

declare(strict_types=1);

namespace Markommerce\Layout\Runtime;

use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Exception\ChainedHandleProviderException;
use Markommerce\Layout\Exception\DynamicHandleConflictException;

/**
 * Pure function: merges a list of dynamic PreparedTrees into a base PreparedTree.
 *
 * Merge rules:
 *   - Slots: dynamic-handle placements are appended to the base tree's matching slots.
 *   - Context providers: concatenated onto the base's context list (base first, then additions).
 *   - HandleProviders: preserved from the base tree only (dynamic trees' providers are not re-applied).
 */
class TreeMerger
{
    /**
     * @param list<PreparedTree> $additions
     *
     * @throws DynamicHandleConflictException
     * @throws ChainedHandleProviderException
     */
    public function merge(PreparedTree $base, array $additions): PreparedTree
    {
        if ($additions === []) {
            return $base;
        }

        $mergedSlots = $base->slots;
        $mergedContext = $base->context;
        $basePlacementNames = $base->placementNames;

        foreach ($additions as $addition) {
            // Runtime check: dynamic handle must not declare its own handleProviders
            if ($addition->handleProviders !== []) {
                $providerClass = $addition->handleProviders[0]->provider;
                throw ChainedHandleProviderException::forChain($providerClass, $addition->handleKey);
            }

            // Runtime check: addition's named placements must not collide with base
            if ($basePlacementNames !== []) {
                $additionNames = $this->collectNamedPlacements($addition->slots);
                foreach ($additionNames as $name) {
                    if (in_array($name, $basePlacementNames, true)) {
                        throw DynamicHandleConflictException::forCollidingPlacement(
                            $name,
                            $base->handleKey,
                            $addition->handleKey,
                        );
                    }
                }
            }

            // Append context providers from dynamic trees
            $mergedContext = array_merge($mergedContext, $addition->context);

            // Merge slots: append dynamic placements to matching slots
            foreach ($addition->slots as $slotName => $slotContent) {
                if (!isset($mergedSlots[$slotName])) {
                    $mergedSlots[$slotName] = $slotContent;
                } else {
                    $baseSlot = $mergedSlots[$slotName];

                    // Only merge when both are flat lists — repeat slots are not mergeable
                    if (is_array($baseSlot) && is_array($slotContent)) {
                        $mergedSlots[$slotName] = array_merge($baseSlot, $slotContent);
                    } else {
                        // For repeat slots or mixed types, the dynamic slot wins (appended)
                        $mergedSlots[$slotName] = $slotContent;
                    }
                }
            }
        }

        return new PreparedTree(
            handleKey: $base->handleKey,
            template: $base->template,
            slots: $mergedSlots,
            context: $mergedContext,
            handleProviders: $base->handleProviders,
            placementNames: $base->placementNames,
        );
    }

    /**
     * Recursively collect all named placement names from prepared slots.
     *
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     * @return list<string>
     */
    private function collectNamedPlacements(array $slots): array
    {
        $names = [];
        foreach ($slots as $value) {
            if ($value instanceof PreparedRepeatSlot) {
                foreach ($value->children as $child) {
                    $names = array_merge($names, $this->collectNamedPlacementsFromPlace($child));
                }
            } else {
                foreach ($value as $place) {
                    $names = array_merge($names, $this->collectNamedPlacementsFromPlace($place));
                }
            }
        }
        return $names;
    }

    /**
     * @return list<string>
     */
    private function collectNamedPlacementsFromPlace(PreparedPlace $place): array
    {
        $names = [];
        if ($place->name !== null) {
            $names[] = $place->name;
        }
        $names = array_merge($names, $this->collectNamedPlacements($place->slots));
        return $names;
    }
}
