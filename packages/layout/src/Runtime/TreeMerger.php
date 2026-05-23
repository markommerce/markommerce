<?php

declare(strict_types=1);

namespace Markommerce\Layout\Runtime;

use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Exception\ChainedHandleProviderException;
use Markommerce\Layout\Exception\DanglingAnchorException;
use Markommerce\Layout\Exception\DynamicHandleConflictException;
use Markommerce\Layout\Operation\Append;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Prepend;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Operation\Replace;
use Markommerce\Layout\Operation\ReplaceProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\Place;
use Markommerce\Layout\Slot;

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
     * @throws DanglingAnchorException
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

            // Apply the dynamic tree's own operations on the merged slots.
            // This allows a dynamic handle to Remove/WrapWith/etc. placements from the base tree.
            foreach ($addition->operations as $operation) {
                $mergedSlots = $this->applyOperation($mergedSlots, $operation, $addition->handleKey);
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
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyOperation(array $slots, object $operation, string $handleKey): array
    {
        return match (true) {
            $operation instanceof Remove => $this->applyRemove($slots, $operation, $handleKey),
            $operation instanceof WrapWith => $this->applyWrapWith($slots, $operation, $handleKey),
            $operation instanceof MergeProps => $this->applyMergeProps($slots, $operation, $handleKey),
            $operation instanceof ReplaceProps => $this->applyReplaceProps($slots, $operation, $handleKey),
            $operation instanceof InsertAfter => $this->applyInsertAfter($slots, $operation, $handleKey),
            $operation instanceof InsertBefore => $this->applyInsertBefore($slots, $operation, $handleKey),
            $operation instanceof Append => $this->applyAppend($slots, $operation, $handleKey),
            $operation instanceof Prepend => $this->applyPrepend($slots, $operation, $handleKey),
            $operation instanceof Replace => $this->applyReplace($slots, $operation, $handleKey),
            default => $slots,
        };
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyRemove(array $slots, Remove $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                if ($place->name === $op->name) {
                    $found = true;
                    continue;
                }
                $new[] = $place;
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->name, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyWrapWith(array $slots, WrapWith $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            return array_map(function (PreparedPlace $place) use ($op, &$found): PreparedPlace {
                if ($place->name === $op->name) {
                    $found = true;
                    return new PreparedPlace(
                        component: $place->component,
                        name: $place->name,
                        props: $place->props,
                        slots: $place->slots,
                        decorators: array_merge($place->decorators, [$op->decorator]),
                        template: $place->template,
                    );
                }
                return $place;
            }, $placements);
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->name, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyMergeProps(array $slots, MergeProps $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            return array_map(function (PreparedPlace $place) use ($op, &$found): PreparedPlace {
                if ($place->name === $op->name) {
                    $found = true;
                    return new PreparedPlace(
                        component: $place->component,
                        name: $place->name,
                        props: array_merge($place->props, $op->props),
                        slots: $place->slots,
                        decorators: $place->decorators,
                        template: $place->template,
                    );
                }
                return $place;
            }, $placements);
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->name, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyReplaceProps(array $slots, ReplaceProps $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            return array_map(function (PreparedPlace $place) use ($op, &$found): PreparedPlace {
                if ($place->name === $op->name) {
                    $found = true;
                    return new PreparedPlace(
                        component: $place->component,
                        name: $place->name,
                        props: $op->props,
                        slots: $place->slots,
                        decorators: $place->decorators,
                        template: $place->template,
                    );
                }
                return $place;
            }, $placements);
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->name, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyInsertAfter(array $slots, InsertAfter $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                $new[] = $place;
                if ($place->name === $op->anchorName) {
                    $new[] = $this->placeToPrep($op->placement);
                    $found = true;
                }
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->anchorName, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyInsertBefore(array $slots, InsertBefore $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                if ($place->name === $op->anchorName) {
                    $new[] = $this->placeToPrep($op->placement);
                    $found = true;
                }
                $new[] = $place;
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->anchorName, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyAppend(array $slots, Append $op, string $handleKey): array
    {
        if (!isset($slots[$op->slotPath])) {
            throw DanglingAnchorException::forAnchor($op->slotPath, "dynamic handle '$handleKey'");
        }

        $slot = $slots[$op->slotPath];
        if ($slot instanceof PreparedRepeatSlot) {
            throw DanglingAnchorException::forAnchor($op->slotPath, "dynamic handle '$handleKey'");
        }

        $slots[$op->slotPath] = array_merge($slot, [$this->placeToPrep($op->placement)]);
        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyPrepend(array $slots, Prepend $op, string $handleKey): array
    {
        if (!isset($slots[$op->slotPath])) {
            throw DanglingAnchorException::forAnchor($op->slotPath, "dynamic handle '$handleKey'");
        }

        $slot = $slots[$op->slotPath];
        if ($slot instanceof PreparedRepeatSlot) {
            throw DanglingAnchorException::forAnchor($op->slotPath, "dynamic handle '$handleKey'");
        }

        $slots[$op->slotPath] = array_merge([$this->placeToPrep($op->placement)], $slot);
        return $slots;
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyReplace(array $slots, Replace $op, string $handleKey): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                if ($place->name === $op->name) {
                    $new[] = $this->placeToPrep($op->placement);
                    $found = true;
                } else {
                    $new[] = $place;
                }
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->name, "dynamic handle '$handleKey'");
        }

        return $slots;
    }

    /**
     * Map over all placement lists in the prepared slot tree, recursing into sub-slots.
     *
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     * @param callable(list<PreparedPlace>): list<PreparedPlace> $callback
     *
     * @return array<string, list<PreparedPlace>|PreparedRepeatSlot>
     */
    private function mapPlacements(array $slots, callable $callback): array
    {
        $result = [];
        foreach ($slots as $slotName => $value) {
            if ($value instanceof PreparedRepeatSlot) {
                $mappedChildren = $callback($value->children);
                $result[$slotName] = new PreparedRepeatSlot(
                    dataKey: $value->dataKey,
                    yields: $value->yields,
                    as: $value->as,
                    children: array_map(
                        fn(PreparedPlace $child) => $this->mapPlacementsInPlace($child, $callback),
                        $mappedChildren,
                    ),
                );
            } else {
                $mapped = $callback($value);
                $result[$slotName] = array_map(
                    fn(PreparedPlace $place) => $this->mapPlacementsInPlace($place, $callback),
                    $mapped,
                );
            }
        }
        return $result;
    }

    private function mapPlacementsInPlace(PreparedPlace $place, callable $callback): PreparedPlace
    {
        return new PreparedPlace(
            component: $place->component,
            name: $place->name,
            props: $place->props,
            slots: $this->mapPlacements($place->slots, $callback),
            decorators: $place->decorators,
            template: $place->template,
        );
    }

    private function placeToPrep(Place $place): PreparedPlace
    {
        $slots = [];
        foreach ($place->slots as $key => $value) {
            if ($value instanceof Slot) {
                $slots[$key] = new PreparedRepeatSlot(
                    dataKey: $value->dataKey,
                    yields: $value->yields,
                    as: $value->as,
                    children: array_map([$this, 'placeToPrep'], $value->children),
                );
            } else {
                $slots[$key] = array_map([$this, 'placeToPrep'], $value);
            }
        }
        return new PreparedPlace(
            component: $place->component,
            name: $place->name,
            props: $place->props,
            slots: $slots,
            decorators: [],
            template: $place->template,
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
