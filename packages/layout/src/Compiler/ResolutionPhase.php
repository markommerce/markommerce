<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Discovery\DiscoveredExtension;
use Markommerce\Layout\Discovery\DiscoveryResult;
use Markommerce\Layout\Exception\DanglingAnchorException;
use Markommerce\Layout\Exception\ExtensionConflictException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;
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

class ResolutionPhase
{
    /**
     * Resolve discovered layouts and extensions into a map of handle key => ResolvedLayout.
     *
     * @return array<string, ResolvedLayout>
     *
     * @throws DanglingAnchorException
     * @throws ExtensionConflictException
     */
    public function resolve(DiscoveryResult $discoveryResult): array
    {
        $result = [];

        foreach ($discoveryResult->layouts as $discoveredLayout) {
            $layout = $discoveredLayout->layout;

            // Skip handle-less base layouts — they are not routable.
            if ($layout->handle === null) {
                continue;
            }

            $handleKey = $this->computeHandleKey($layout->handle);

            // Resolve extends chain.
            $mergedLayout = $this->resolveExtendsChain($layout);

            // Convert placements to ResolvedPlace objects.
            $resolvedSlots = $this->convertSlots($mergedLayout['slots']);

            // Apply matching extensions.
            $matchingExtensions = $this->collectMatchingExtensions(
                $layout->handle,
                $handleKey,
                $discoveryResult->extensions,
            );
            $resolvedSlots = $this->applyExtensions($resolvedSlots, $matchingExtensions);

            $result[$handleKey] = new ResolvedLayout(
                handle: $layout->handle,
                handleKey: $handleKey,
                template: $mergedLayout['template'],
                slots: $resolvedSlots,
                context: $mergedLayout['context'],
            );
        }

        return $result;
    }

    /**
     * Compute a string handle key from a handle value.
     *
     * @param array<int, string>|string $handle
     */
    private function computeHandleKey(array|string $handle): string
    {
        if (is_array($handle)) {
            return $handle[0] . '::' . $handle[1];
        }

        return $handle;
    }

    /**
     * Resolve the extends chain, returning merged slots, template, and context.
     *
     * @return array{slots: array<string, list<Place>|Slot>, template: ?string, context: list<\Markommerce\Layout\Provide>}
     */
    private function resolveExtendsChain(Layout $layout): array
    {
        if ($layout->extends === null) {
            return [
                'slots' => $layout->slots,
                'template' => $layout->template,
                'context' => $layout->context,
            ];
        }

        /** @var class-string<LayoutDefinition> $extendsClass */
        $extendsClass = $layout->extends;
        $parentLayout = $extendsClass::define();

        // Resolve parent recursively.
        $parentResolved = $this->resolveExtendsChain($parentLayout);

        // Merge: parent slots are the base, child adds/overrides.
        $mergedSlots = $parentResolved['slots'];
        foreach ($layout->slots as $slotName => $placements) {
            if (isset($mergedSlots[$slotName])) {
                // Child adds placements into parent slot.
                if (is_array($mergedSlots[$slotName]) && is_array($placements)) {
                    $mergedSlots[$slotName] = array_merge($mergedSlots[$slotName], $placements);
                } else {
                    $mergedSlots[$slotName] = $placements;
                }
            } else {
                $mergedSlots[$slotName] = $placements;
            }
        }

        // Effective template: child's if set, else nearest ancestor's.
        $effectiveTemplate = $layout->template ?? $parentResolved['template'];

        // Context: child context takes precedence (child appends to parent).
        $mergedContext = array_merge($parentResolved['context'], $layout->context);

        return [
            'slots' => $mergedSlots,
            'template' => $effectiveTemplate,
            'context' => $mergedContext,
        ];
    }

    /**
     * Convert Place/Slot arrays into ResolvedPlace/ResolvedRepeatSlot arrays.
     *
     * @param array<string, list<Place>|Slot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     */
    private function convertSlots(array $slots): array
    {
        $resolved = [];
        foreach ($slots as $slotName => $value) {
            if ($value instanceof Slot) {
                $resolved[$slotName] = new ResolvedRepeatSlot(
                    dataKey: $value->dataKey,
                    yields: $value->yields,
                    as: $value->as,
                    children: array_map(
                        fn(Place $p) => $this->convertPlace($p),
                        $value->children,
                    ),
                );
            } else {
                $resolved[$slotName] = array_map(
                    fn(Place $p) => $this->convertPlace($p),
                    $value,
                );
            }
        }
        return $resolved;
    }

    private function convertPlace(Place $place): ResolvedPlace
    {
        return new ResolvedPlace(
            component: $place->component,
            name: $place->name,
            props: $place->props,
            slots: $this->convertSlots($place->slots),
            decorators: [],
            template: $place->template,
        );
    }

    /**
     * Collect extensions matching a given handle.
     *
     * @param array<int, string>|string $handle
     * @param list<DiscoveredExtension> $extensions
     *
     * @return list<DiscoveredExtension>
     */
    private function collectMatchingExtensions(
        array|string $handle,
        string $handleKey,
        array $extensions,
    ): array {
        return array_values(array_filter(
            $extensions,
            fn(DiscoveredExtension $de) => $this->computeHandleKey($de->extension->handle) === $handleKey,
        ));
    }

    /**
     * Apply extensions in priority order to the resolved slots.
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @param list<DiscoveredExtension> $extensions
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     * @throws ExtensionConflictException
     */
    private function applyExtensions(array $slots, array $extensions): array
    {
        if (empty($extensions)) {
            return $slots;
        }

        // Group by priority.
        $byPriority = [];
        foreach ($extensions as $de) {
            $byPriority[$de->extension->priority][] = $de;
        }
        ksort($byPriority);

        foreach ($byPriority as $priority => $priorityGroup) {
            // Sort within same priority by source file path for determinism.
            usort(
                $priorityGroup,
                fn(DiscoveredExtension $a, DiscoveredExtension $b) => strcmp($a->sourceFile, $b->sourceFile),
            );

            // Check for conflicts at this priority level.
            $this->checkConflicts($priorityGroup, $priority);

            // Apply operations from all extensions at this priority.
            foreach ($priorityGroup as $de) {
                foreach ($de->extension->operations as $operation) {
                    $slots = $this->applyOperation($slots, $operation, $de->sourceFile);
                }
            }
        }

        return $slots;
    }

    /**
     * Check for conflicting operations within a same-priority group.
     *
     * @param list<DiscoveredExtension> $group
     *
     * @throws ExtensionConflictException
     */
    private function checkConflicts(array $group, int $priority): void
    {
        // Collect all anchor-targeting operations across this priority group.
        // Conflict = two operations targeting the same anchor name with incompatible operations.
        $anchorOps = [];

        foreach ($group as $de) {
            foreach ($de->extension->operations as $operation) {
                $anchor = $this->getOperationAnchor($operation);
                if ($anchor === null) {
                    continue;
                }

                $opClass = $operation::class;
                if (isset($anchorOps[$anchor])) {
                    $existing = $anchorOps[$anchor];
                    // Conflict: two operations on the same anchor at same priority.
                    if ($existing !== $opClass) {
                        throw ExtensionConflictException::forConflict($existing, $opClass, $priority);
                    }
                    // Same operation type on same anchor — also a conflict.
                    throw ExtensionConflictException::forConflict($existing, $opClass, $priority);
                }

                $anchorOps[$anchor] = $opClass;
            }
        }
    }

    /**
     * Get the anchor name for anchor-based operations, or null if not applicable.
     */
    private function getOperationAnchor(object $operation): ?string
    {
        return match (true) {
            $operation instanceof Remove => $operation->name,
            $operation instanceof Replace => $operation->name,
            $operation instanceof MergeProps => $operation->name,
            $operation instanceof ReplaceProps => $operation->name,
            $operation instanceof WrapWith => $operation->name,
            $operation instanceof InsertAfter => $operation->anchorName,
            $operation instanceof InsertBefore => $operation->anchorName,
            default => null,
        };
    }

    /**
     * Apply a single operation to the slot tree.
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyOperation(array $slots, object $operation, string $sourceFile): array
    {
        return match (true) {
            $operation instanceof InsertAfter => $this->applyInsertAfter($slots, $operation, $sourceFile),
            $operation instanceof InsertBefore => $this->applyInsertBefore($slots, $operation, $sourceFile),
            $operation instanceof Append => $this->applyAppend($slots, $operation, $sourceFile),
            $operation instanceof Prepend => $this->applyPrepend($slots, $operation, $sourceFile),
            $operation instanceof Remove => $this->applyRemove($slots, $operation, $sourceFile),
            $operation instanceof Replace => $this->applyReplace($slots, $operation, $sourceFile),
            $operation instanceof MergeProps => $this->applyMergeProps($slots, $operation, $sourceFile),
            $operation instanceof ReplaceProps => $this->applyReplaceProps($slots, $operation, $sourceFile),
            $operation instanceof WrapWith => $this->applyWrapWith($slots, $operation, $sourceFile),
            default => $slots,
        };
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyInsertAfter(array $slots, InsertAfter $op, string $sourceFile): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                $new[] = $place;
                if ($place->name === $op->anchorName) {
                    $new[] = $this->convertPlace($op->placement);
                    $found = true;
                }
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->anchorName, $sourceFile);
        }

        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyInsertBefore(array $slots, InsertBefore $op, string $sourceFile): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                if ($place->name === $op->anchorName) {
                    $new[] = $this->convertPlace($op->placement);
                    $found = true;
                }
                $new[] = $place;
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->anchorName, $sourceFile);
        }

        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyAppend(array $slots, Append $op, string $sourceFile): array
    {
        if (!isset($slots[$op->slotPath])) {
            throw DanglingAnchorException::forAnchor($op->slotPath, $sourceFile);
        }

        $slot = $slots[$op->slotPath];
        if ($slot instanceof ResolvedRepeatSlot) {
            // Cannot append to a repeat slot by slot path in this manner.
            throw DanglingAnchorException::forAnchor($op->slotPath, $sourceFile);
        }

        $slots[$op->slotPath] = array_merge($slot, [$this->convertPlace($op->placement)]);
        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyPrepend(array $slots, Prepend $op, string $sourceFile): array
    {
        if (!isset($slots[$op->slotPath])) {
            throw DanglingAnchorException::forAnchor($op->slotPath, $sourceFile);
        }

        $slot = $slots[$op->slotPath];
        if ($slot instanceof ResolvedRepeatSlot) {
            throw DanglingAnchorException::forAnchor($op->slotPath, $sourceFile);
        }

        $slots[$op->slotPath] = array_merge([$this->convertPlace($op->placement)], $slot);
        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyRemove(array $slots, Remove $op, string $sourceFile): array
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
            throw DanglingAnchorException::forAnchor($op->name, $sourceFile);
        }

        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyReplace(array $slots, Replace $op, string $sourceFile): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            $new = [];
            foreach ($placements as $place) {
                if ($place->name === $op->name) {
                    $new[] = $this->convertPlace($op->placement);
                    $found = true;
                } else {
                    $new[] = $place;
                }
            }
            return $new;
        });

        if (!$found) {
            throw DanglingAnchorException::forAnchor($op->name, $sourceFile);
        }

        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyMergeProps(array $slots, MergeProps $op, string $sourceFile): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            return array_map(function (ResolvedPlace $place) use ($op, &$found): ResolvedPlace {
                if ($place->name === $op->name) {
                    $found = true;
                    return new ResolvedPlace(
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
            throw DanglingAnchorException::forAnchor($op->name, $sourceFile);
        }

        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyReplaceProps(array $slots, ReplaceProps $op, string $sourceFile): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            return array_map(function (ResolvedPlace $place) use ($op, &$found): ResolvedPlace {
                if ($place->name === $op->name) {
                    $found = true;
                    return new ResolvedPlace(
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
            throw DanglingAnchorException::forAnchor($op->name, $sourceFile);
        }

        return $slots;
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     *
     * @throws DanglingAnchorException
     */
    private function applyWrapWith(array $slots, WrapWith $op, string $sourceFile): array
    {
        $found = false;
        $slots = $this->mapPlacements($slots, function (array $placements) use ($op, &$found): array {
            return array_map(function (ResolvedPlace $place) use ($op, &$found): ResolvedPlace {
                if ($place->name === $op->name) {
                    $found = true;
                    return new ResolvedPlace(
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
            throw DanglingAnchorException::forAnchor($op->name, $sourceFile);
        }

        return $slots;
    }

    /**
     * Map over all placement lists in the slot tree, recursing into repeat slots and sub-slots.
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @param callable(list<ResolvedPlace>): list<ResolvedPlace> $callback
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     */
    private function mapPlacements(array $slots, callable $callback): array
    {
        $result = [];
        foreach ($slots as $slotName => $value) {
            if ($value instanceof ResolvedRepeatSlot) {
                $result[$slotName] = new ResolvedRepeatSlot(
                    dataKey: $value->dataKey,
                    yields: $value->yields,
                    as: $value->as,
                    children: array_map(
                        fn(ResolvedPlace $child) => $this->mapPlacementsInPlace($child, $callback),
                        $value->children,
                    ),
                );
            } else {
                $mapped = $callback($value);
                $result[$slotName] = array_map(
                    fn(ResolvedPlace $place) => $this->mapPlacementsInPlace($place, $callback),
                    $mapped,
                );
            }
        }
        return $result;
    }

    private function mapPlacementsInPlace(ResolvedPlace $place, callable $callback): ResolvedPlace
    {
        return new ResolvedPlace(
            component: $place->component,
            name: $place->name,
            props: $place->props,
            slots: $this->mapPlacements($place->slots, $callback),
            decorators: $place->decorators,
            template: $place->template,
        );
    }
}
