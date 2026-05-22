<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Compiler\ResolvedPlace;
use Markommerce\Layout\Compiler\ResolvedRepeatSlot;
use Markommerce\Layout\Contracts\HandleProvider;
use Markommerce\Layout\Exception\InvalidLayoutFileException;
use Markommerce\Layout\Exception\InvalidSourceTypeException;
use Markommerce\Layout\ProvideHandle;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;

class PreparedTreeBuilder
{
    /**
     * @throws InvalidLayoutFileException
     * @throws InvalidSourceTypeException
     */
    public function build(ResolvedLayout $resolvedLayout): PreparedTree
    {
        $this->validateHandleProviders($resolvedLayout);

        $placementNames = $this->collectPlacementNames($resolvedLayout->slots);

        return new PreparedTree(
            handleKey: $resolvedLayout->handleKey,
            template: $resolvedLayout->template,
            slots: $this->buildSlots($resolvedLayout->slots),
            context: $resolvedLayout->context,
            handleProviders: $resolvedLayout->handleProviders,
            placementNames: $placementNames,
        );
    }

    /**
     * @throws InvalidLayoutFileException
     * @throws InvalidSourceTypeException
     */
    private function validateHandleProviders(ResolvedLayout $resolvedLayout): void
    {
        foreach ($resolvedLayout->handleProviders as $provideHandle) {
            $this->validateProviderClass($resolvedLayout->handleKey, $provideHandle);
            $this->validateProviderProps($resolvedLayout->handleKey, $provideHandle);
        }
    }

    /**
     * @throws InvalidLayoutFileException
     */
    private function validateProviderClass(string $handleKey, ProvideHandle $provideHandle): void
    {
        $exists = class_exists($provideHandle->provider) || interface_exists($provideHandle->provider);

        if (!$exists || !is_a($provideHandle->provider, HandleProvider::class, true)) {
            throw InvalidLayoutFileException::forInvalidHandleProvider($handleKey, $provideHandle->provider);
        }
    }

    /**
     * @throws InvalidSourceTypeException
     */
    private function validateProviderProps(string $handleKey, ProvideHandle $provideHandle): void
    {
        foreach ($provideHandle->props as $propKey => $source) {
            if ($source instanceof ParentDataSource || $source instanceof IteratedSource) {
                throw InvalidSourceTypeException::forDisallowedHandleProviderSource(
                    $handleKey,
                    $provideHandle->provider,
                    $propKey,
                    $source::class,
                );
            }
        }
    }

    /**
     * Recursively collect all named placement names from resolved slots.
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @return list<string>
     */
    private function collectPlacementNames(array $slots): array
    {
        $names = [];
        foreach ($slots as $value) {
            if ($value instanceof ResolvedRepeatSlot) {
                foreach ($value->children as $child) {
                    $names = array_merge($names, $this->collectPlaceNames($child));
                }
            } else {
                foreach ($value as $place) {
                    $names = array_merge($names, $this->collectPlaceNames($place));
                }
            }
        }
        return $names;
    }

    /**
     * @return list<string>
     */
    private function collectPlaceNames(ResolvedPlace $place): array
    {
        $names = [];
        if ($place->name !== null) {
            $names[] = $place->name;
        }
        $names = array_merge($names, $this->collectPlacementNames($place->slots));
        return $names;
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
            template: $resolvedPlace->template,
        );
    }
}
