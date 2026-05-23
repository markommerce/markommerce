<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Discovery\DiscoveredExtension;
use Markommerce\Layout\Discovery\DiscoveredLayout;
use Markommerce\Layout\Discovery\DiscoveryResult;
use Markommerce\Layout\Exception\CircularInheritanceException;
use Markommerce\Layout\Exception\DanglingAnchorException;
use Markommerce\Layout\Exception\DefaultHandleConflictException;
use Markommerce\Layout\Exception\DuplicateContextTokenException;
use Markommerce\Layout\Exception\ExtensionConflictException;
use Markommerce\Layout\Exception\UnknownParentHandleException;
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
    public const string HANDLE_DEFAULT = 'default';

    /**
     * Resolve discovered layouts and extensions into a map of handle key => ResolvedLayout.
     *
     * @return array<string, ResolvedLayout>
     *
     * @throws CircularInheritanceException
     * @throws DanglingAnchorException
     * @throws DefaultHandleConflictException
     * @throws DuplicateContextTokenException
     * @throws ExtensionConflictException
     * @throws UnknownParentHandleException
     */
    public function resolve(DiscoveryResult $discoveryResult): array
    {
        // Build a map of handle key => DiscoveredLayout for routable layouts.
        $routableLayouts = [];
        foreach ($discoveryResult->layouts as $discoveredLayout) {
            $layout = $discoveredLayout->layout;
            if ($layout->handle === null) {
                continue;
            }
            $handleKey = $this->computeHandleKey($layout->handle);
            $routableLayouts[$handleKey] = $discoveredLayout;
        }

        // Validate default handle constraints before any resolution.
        if (isset($routableLayouts[self::HANDLE_DEFAULT])) {
            $this->validateDefaultHandle($routableLayouts[self::HANDLE_DEFAULT]->layout);
        }

        // Determine resolution order: parents must be resolved before children.
        $orderedKeys = $this->topologicalSort($routableLayouts);

        // Collect default layout's resolved slots/context for Half A merge.
        /** @var array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $defaultSlots */
        $defaultSlots = [];
        /** @var list<\Markommerce\Layout\Provide> $defaultContext */
        $defaultContext = [];
        /** @var Layout|null $defaultLayout */
        $defaultLayout = null;
        /** @var string $defaultSourceFile */
        $defaultSourceFile = '';
        if (isset($routableLayouts[self::HANDLE_DEFAULT])) {
            $defaultDiscoveredLayout = $routableLayouts[self::HANDLE_DEFAULT];
            $defaultLayout = $defaultDiscoveredLayout->layout;
            $defaultSourceFile = $defaultDiscoveredLayout->sourceFile;
            $mergedDefault = $this->resolveExtendsChain($defaultLayout);
            $defaultSlots = $this->convertSlots($mergedDefault['slots']);
            $defaultContext = $mergedDefault['context'];
        }

        // $resolvedLayoutsWithoutDefault: used for inherits lookups to prevent double-merge.
        $resolvedLayoutsWithoutDefault = [];
        $result = [];

        foreach ($orderedKeys as $handleKey) {
            $discoveredLayout = $routableLayouts[$handleKey];
            $layout = $discoveredLayout->layout;
            // $layout->handle is guaranteed non-null — only routable (non-null handle) layouts are in $routableLayouts.
            assert($layout->handle !== null);

            // Skip the default handle itself in this loop — it is processed separately.
            if ($handleKey === self::HANDLE_DEFAULT) {
                continue;
            }

            if ($layout->inherits !== null) {
                // When inherits is present, we need shell → parent → own ordering.
                // Resolve the extends chain shell only (no own slots from this layout merged in).
                $shellData = $this->resolveExtendsChainShellOnly($layout);
                $shellResolvedSlots = $this->convertSlots($shellData['slots']);
                $ownResolvedSlots = $this->convertSlots($layout->slots);

                // Apply inheritance: shell + parent + own.
                // The inherits lookup reads $resolvedLayoutsWithoutDefault (pre-Half-A) to prevent double-merge.
                [$resolvedSlots, $resolvedContext, $resolvedTemplate] = $this->applyInheritanceWithShellAndOwn(
                    $shellResolvedSlots,
                    $ownResolvedSlots,
                    $shellData['context'],
                    $layout->context,
                    $shellData['template'],
                    $layout->template,
                    $layout->inherits,
                    $handleKey,
                    $resolvedLayoutsWithoutDefault,
                );
            } else {
                // No inheritance: standard extends-chain resolution.
                $mergedLayout = $this->resolveExtendsChain($layout);

                $resolvedSlots = $this->convertSlots($mergedLayout['slots']);
                $resolvedContext = $mergedLayout['context'];
                $resolvedTemplate = $mergedLayout['template'];
            }

            // Store pre-Half-A version for inherits lookups.
            $resolvedLayoutsWithoutDefault[$handleKey] = new ResolvedLayout(
                handle: $layout->handle,
                handleKey: $handleKey,
                template: $resolvedTemplate,
                slots: $resolvedSlots,
                context: $resolvedContext,
                handleProviders: $layout->handleProviders,
            );

            // Half A: prepend default placements and context providers (before own operations).
            if ($defaultLayout !== null) {
                $resolvedSlots = $this->prependDefaultSlots($defaultSlots, $resolvedSlots);
                $resolvedContext = $this->mergeDefaultContext($defaultContext, $resolvedContext, $handleKey);
            }

            // Apply the layout's own operations (after extends chain, inheritance, and default Half A merge).
            foreach ($layout->operations as $operation) {
                $resolvedSlots = $this->applyOperation($resolvedSlots, $operation, $discoveredLayout->sourceFile);
            }

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
                template: $resolvedTemplate,
                slots: $resolvedSlots,
                context: $resolvedContext,
                handleProviders: $layout->handleProviders,
                operations: $layout->operations,
            );
        }

        // Half B: apply default handle's own operations and default-targeted extensions to every non-default handle.
        if ($defaultLayout !== null) {
            $defaultExtensions = $this->collectMatchingExtensions(
                self::HANDLE_DEFAULT,
                self::HANDLE_DEFAULT,
                $discoveryResult->extensions,
            );

            foreach ($result as $handleKey => $resolvedLayout) {
                $slots = $resolvedLayout->slots;

                // Apply default layout's own operations.
                foreach ($defaultLayout->operations as $operation) {
                    $slots = $this->applyOperation($slots, $operation, $defaultSourceFile);
                }

                // Apply default-targeted extensions.
                $slots = $this->applyExtensions($slots, $defaultExtensions);

                $result[$handleKey] = new ResolvedLayout(
                    handle: $resolvedLayout->handle,
                    handleKey: $resolvedLayout->handleKey,
                    template: $resolvedLayout->template,
                    slots: $slots,
                    context: $resolvedLayout->context,
                    handleProviders: $resolvedLayout->handleProviders,
                    operations: $resolvedLayout->operations,
                );
            }
        }

        return $result;
    }

    /**
     * Validate that the 'default' handle does not declare forbidden fields.
     *
     * @throws DefaultHandleConflictException
     */
    private function validateDefaultHandle(Layout $layout): void
    {
        if ($layout->extends !== null) {
            throw DefaultHandleConflictException::forField('extends');
        }
        if ($layout->inherits !== null) {
            throw DefaultHandleConflictException::forField('inherits');
        }
        if (!empty($layout->handleProviders)) {
            throw DefaultHandleConflictException::forField('handleProviders');
        }
    }

    /**
     * Prepend default slots into sibling slots (default placements come first).
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $defaultSlots
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $siblingSlots
     *
     * @return array<string, list<ResolvedPlace>|ResolvedRepeatSlot>
     */
    private function prependDefaultSlots(array $defaultSlots, array $siblingSlots): array
    {
        $merged = $siblingSlots;
        foreach ($defaultSlots as $slotName => $defaultEntries) {
            if (isset($merged[$slotName]) && is_array($defaultEntries) && is_array($merged[$slotName])) {
                $merged[$slotName] = array_merge($defaultEntries, $merged[$slotName]);
            } else {
                // Default slot not present in sibling — prepend it.
                $merged = [$slotName => $defaultEntries] + $merged;
            }
        }
        return $merged;
    }

    /**
     * Merge default context providers ahead of sibling context providers.
     * Throws DuplicateContextTokenException if any tokens overlap.
     *
     * @param list<\Markommerce\Layout\Provide> $defaultContext
     * @param list<\Markommerce\Layout\Provide> $siblingContext
     *
     * @return list<\Markommerce\Layout\Provide>
     *
     * @throws DuplicateContextTokenException
     */
    private function mergeDefaultContext(array $defaultContext, array $siblingContext, string $siblingHandleKey): array
    {
        $defaultTokens = array_map(fn(\Markommerce\Layout\Provide $p) => $p->token, $defaultContext);
        foreach ($siblingContext as $provide) {
            if (in_array($provide->token, $defaultTokens, true)) {
                throw DuplicateContextTokenException::forToken($provide->token, self::HANDLE_DEFAULT, $siblingHandleKey);
            }
        }
        return array_merge($defaultContext, $siblingContext);
    }

    /**
     * Sort routable layouts topologically so parents are resolved before children.
     *
     * @param array<string, DiscoveredLayout> $routableLayouts
     *
     * @return list<string>
     *
     * @throws CircularInheritanceException
     */
    private function topologicalSort(array $routableLayouts): array
    {
        $ordered = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($routableLayouts) as $handleKey) {
            $this->topoVisit($handleKey, $routableLayouts, $ordered, $visited, $visiting, []);
        }

        return $ordered;
    }

    /**
     * @param array<string, DiscoveredLayout> $routableLayouts
     * @param list<string> $ordered
     * @param array<string, true> $visited
     * @param array<string, true> $visiting
     * @param list<string> $chain
     *
     * @throws CircularInheritanceException
     */
    private function topoVisit(
        string $handleKey,
        array $routableLayouts,
        array &$ordered,
        array &$visited,
        array &$visiting,
        array $chain,
    ): void {
        if (isset($visited[$handleKey])) {
            return;
        }

        $chain[] = $handleKey;

        if (isset($visiting[$handleKey])) {
            throw CircularInheritanceException::forChain($chain);
        }

        $visiting[$handleKey] = true;

        $parentHandle = $routableLayouts[$handleKey]->layout->inherits ?? null;

        if ($parentHandle !== null) {
            if (!isset($routableLayouts[$parentHandle])) {
                throw UnknownParentHandleException::forParent($parentHandle, $handleKey);
            }
            $this->topoVisit($parentHandle, $routableLayouts, $ordered, $visited, $visiting, $chain);
        }

        unset($visiting[$handleKey]);
        $visited[$handleKey] = true;
        $ordered[] = $handleKey;
    }

    /**
     * Merge shell, parent handle, and own slots/context into the final resolved state.
     *
     * Order: shell → parent handle → own.
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $shellResolvedSlots
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $ownResolvedSlots
     * @param list<\Markommerce\Layout\Provide> $shellContext
     * @param list<\Markommerce\Layout\Provide> $ownContext
     * @param array<string, ResolvedLayout> $resolvedSoFar
     *
     * @return array{0: array<string, list<ResolvedPlace>|ResolvedRepeatSlot>, 1: list<\Markommerce\Layout\Provide>, 2: ?string}
     *
     * @throws DuplicateContextTokenException
     */
    private function applyInheritanceWithShellAndOwn(
        array $shellResolvedSlots,
        array $ownResolvedSlots,
        array $shellContext,
        array $ownContext,
        ?string $shellTemplate,
        ?string $ownTemplate,
        string $parentHandleKey,
        string $childHandleKey,
        array $resolvedSoFar,
    ): array {
        $parentResolved = $resolvedSoFar[$parentHandleKey];

        // Merge slots in order: shell → parent → own.
        // Start with shell slots.
        $mergedSlots = $shellResolvedSlots;

        // Append parent's slots after shell.
        foreach ($parentResolved->slots as $slotName => $parentEntries) {
            if (isset($mergedSlots[$slotName]) && is_array($mergedSlots[$slotName]) && is_array($parentEntries)) {
                $mergedSlots[$slotName] = array_merge($mergedSlots[$slotName], $parentEntries);
            } else {
                $mergedSlots[$slotName] = $parentEntries;
            }
        }

        // Append own slots after parent's.
        foreach ($ownResolvedSlots as $slotName => $ownEntries) {
            if (isset($mergedSlots[$slotName]) && is_array($mergedSlots[$slotName]) && is_array($ownEntries)) {
                $mergedSlots[$slotName] = array_merge($mergedSlots[$slotName], $ownEntries);
            } else {
                $mergedSlots[$slotName] = $ownEntries;
            }
        }

        // Check for duplicate context tokens between parent and child (shell + own).
        $parentTokens = array_map(fn(\Markommerce\Layout\Provide $p) => $p->token, $parentResolved->context);
        $shellTokens = array_map(fn(\Markommerce\Layout\Provide $p) => $p->token, $shellContext);
        $allIncomingTokens = array_merge($shellTokens, $parentTokens);

        foreach ($ownContext as $provide) {
            if (in_array($provide->token, $allIncomingTokens, true)) {
                throw DuplicateContextTokenException::forToken($provide->token, $parentHandleKey, $childHandleKey);
            }
        }
        foreach ($shellContext as $provide) {
            if (in_array($provide->token, $parentTokens, true)) {
                throw DuplicateContextTokenException::forToken($provide->token, $parentHandleKey, $childHandleKey);
            }
        }

        // Merge context: shell → parent → own.
        $mergedContext = array_merge($shellContext, $parentResolved->context, $ownContext);

        // Template: own if set, else parent's, else shell's.
        $effectiveTemplate = $ownTemplate ?? $parentResolved->template ?? $shellTemplate;

        return [$mergedSlots, $mergedContext, $effectiveTemplate];
    }

    /**
     * Resolve only the extends chain's shell slots (no own slots from this layout merged in).
     * Used when inherits is also set, to keep the ordering correct.
     *
     * @return array{slots: array<string, list<Place>|Slot>, template: ?string, context: list<\Markommerce\Layout\Provide>}
     */
    private function resolveExtendsChainShellOnly(Layout $layout): array
    {
        if ($layout->extends === null) {
            return [
                'slots' => [],
                'template' => null,
                'context' => [],
            ];
        }

        /** @var class-string<LayoutDefinition> $extendsClass */
        $extendsClass = $layout->extends;
        $parentLayout = $extendsClass::define();

        // Resolve the full extends chain of the shell (which includes the shell layout's own slots).
        return $this->resolveExtendsChain($parentLayout);
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
                $mappedChildren = $callback($value->children);
                $result[$slotName] = new ResolvedRepeatSlot(
                    dataKey: $value->dataKey,
                    yields: $value->yields,
                    as: $value->as,
                    children: array_map(
                        fn(ResolvedPlace $child) => $this->mapPlacementsInPlace($child, $callback),
                        $mappedChildren,
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
