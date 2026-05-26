<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

use Markommerce\Layout\Contracts\Operation;
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
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\ContextSource;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;
use Markommerce\Layout\Source\QuerySource;
use Markommerce\Layout\Source\RouteSource;
use Markommerce\Layout\Source\ServiceSource;
use RuntimeException;

/**
 * Custom code emitter that walks a PreparedTree and emits literal
 * `new \Fully\Qualified\ClassName(arg, arg, ...)` constructor-call source
 * for every value object, recursing into nested objects/arrays.
 *
 * This sidesteps `var_export()` + `__set_state()` entirely and works with
 * readonly + constructor-promoted properties.
 *
 * The set of supported value object types is closed. If a new object type is
 * added it must be registered here with an explicit emit case, otherwise the
 * emitter will throw an UnsupportedValueException at compile time.
 */
class PhpCodeEmitter
{
    public function emitArtifact(array $trees): string
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', '', 'return ['];
        foreach ($trees as $key => $tree) {
            $lines[] = '    ' . $this->emitString($key) . ' => ' . $this->emitPreparedTree($tree) . ',';
        }
        $lines[] = '];';

        return implode("\n", $lines) . "\n";
    }

    private function emitPreparedTree(PreparedTree $tree): string
    {
        $slotsCode = $this->emitSlots($tree->slots);
        $contextCode = $this->emitList($tree->context, fn ($item) => $this->emitProvide($item));
        $handleProvidersCode = $this->emitList(
            $tree->handleProviders,
            fn ($item) => $this->emitProvideHandle($item),
        );
        $placementNamesCode = $this->emitStringList($tree->placementNames);
        $operationsCode = $this->emitList($tree->operations, fn ($op) => $this->emitOperation($op));

        return sprintf(
            'new \%s(' . "\n" .
            '        handleKey: %s,' . "\n" .
            '        template: %s,' . "\n" .
            '        slots: %s,' . "\n" .
            '        context: %s,' . "\n" .
            '        handleProviders: %s,' . "\n" .
            '        placementNames: %s,' . "\n" .
            '        operations: %s,' . "\n" .
            '    )',
            PreparedTree::class,
            $this->emitString($tree->handleKey),
            $this->emitNullableString($tree->template),
            $slotsCode,
            $contextCode,
            $handleProvidersCode,
            $placementNamesCode,
            $operationsCode,
        );
    }

    private function emitOperation(Operation $op): string
    {
        return match (true) {
            $op instanceof Remove => sprintf(
                'new \%s(name: %s)',
                Remove::class,
                $this->emitString($op->name),
            ),
            $op instanceof WrapWith => sprintf(
                'new \%s(name: %s, decorator: %s)',
                WrapWith::class,
                $this->emitString($op->name),
                $this->emitString($op->decorator),
            ),
            $op instanceof MergeProps => sprintf(
                'new \%s(name: %s, props: %s)',
                MergeProps::class,
                $this->emitString($op->name),
                $this->emitProps($op->props),
            ),
            $op instanceof ReplaceProps => sprintf(
                'new \%s(name: %s, props: %s)',
                ReplaceProps::class,
                $this->emitString($op->name),
                $this->emitProps($op->props),
            ),
            $op instanceof InsertAfter => sprintf(
                'new \%s(anchorName: %s, placement: %s)',
                InsertAfter::class,
                $this->emitString($op->anchorName),
                $this->emitPlace($op->placement),
            ),
            $op instanceof InsertBefore => sprintf(
                'new \%s(anchorName: %s, placement: %s)',
                InsertBefore::class,
                $this->emitString($op->anchorName),
                $this->emitPlace($op->placement),
            ),
            $op instanceof Append => sprintf(
                'new \%s(slotPath: %s, placement: %s)',
                Append::class,
                $this->emitString($op->slotPath),
                $this->emitPlace($op->placement),
            ),
            $op instanceof Prepend => sprintf(
                'new \%s(slotPath: %s, placement: %s)',
                Prepend::class,
                $this->emitString($op->slotPath),
                $this->emitPlace($op->placement),
            ),
            $op instanceof Replace => sprintf(
                'new \%s(name: %s, placement: %s)',
                Replace::class,
                $this->emitString($op->name),
                $this->emitPlace($op->placement),
            ),
            default => throw new RuntimeException(
                sprintf(
                    'PhpCodeEmitter: unsupported operation type "%s". Register it in emitOperation().',
                    $op::class,
                ),
            ),
        };
    }

    private function emitPlace(Place $place): string
    {
        return sprintf(
            'new \%s(' . "\n" .
            '            component: %s,' . "\n" .
            '            name: %s,' . "\n" .
            '            props: %s,' . "\n" .
            '            slots: %s,' . "\n" .
            '            template: %s,' . "\n" .
            '        )',
            Place::class,
            $this->emitString($place->component),
            $this->emitNullableString($place->name),
            $this->emitProps($place->props),
            $this->emitPlaceSlots($place->slots),
            $this->emitString($place->template),
        );
    }

    /**
     * @param array<string, list<Place>|Slot> $slots
     */
    private function emitPlaceSlots(array $slots): string
    {
        if ($slots === []) {
            return '[]';
        }
        $parts = [];
        foreach ($slots as $key => $value) {
            if ($value instanceof Slot) {
                $childrenCode = $this->emitList($value->children, fn ($c) => $this->emitPlace($c));
                $slotCode = sprintf(
                    '\%s::repeat(%s, %s, %s, %s)',
                    Slot::class,
                    $this->emitString($value->dataKey),
                    $this->emitString($value->yields),
                    $this->emitString($value->as),
                    $childrenCode,
                );
                $parts[] = $this->emitString($key) . ' => ' . $slotCode;
            } else {
                $children = array_map([$this, 'emitPlace'], $value);
                $parts[] = $this->emitString($key) . ' => [' . implode(', ', $children) . ']';
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function emitProvideHandle(ProvideHandle $provideHandle): string
    {
        return sprintf(
            'new \%s(' . "\n" .
            '            provider: %s,' . "\n" .
            '            props: %s,' . "\n" .
            '        )',
            ProvideHandle::class,
            $this->emitString($provideHandle->provider),
            $this->emitProps($provideHandle->props),
        );
    }

    private function emitPreparedPlace(PreparedPlace $place): string
    {
        $slotsCode = $this->emitSlots($place->slots);
        $propsCode = $this->emitProps($place->props);
        $decoratorsCode = $this->emitStringList($place->decorators);

        return sprintf(
            'new \%s(' . "\n" .
            '            component: %s,' . "\n" .
            '            name: %s,' . "\n" .
            '            props: %s,' . "\n" .
            '            slots: %s,' . "\n" .
            '            decorators: %s,' . "\n" .
            '            template: %s,' . "\n" .
            '        )',
            PreparedPlace::class,
            $this->emitString($place->component),
            $this->emitNullableString($place->name),
            $propsCode,
            $slotsCode,
            $decoratorsCode,
            $this->emitString($place->template),
        );
    }

    private function emitPreparedRepeatSlot(PreparedRepeatSlot $slot): string
    {
        $childrenCode = $this->emitList(
            $slot->children,
            fn ($child) => $this->emitPreparedPlace($child),
        );

        return sprintf(
            'new \%s(' . "\n" .
            '            dataKey: %s,' . "\n" .
            '            yields: %s,' . "\n" .
            '            as: %s,' . "\n" .
            '            children: %s,' . "\n" .
            '        )',
            PreparedRepeatSlot::class,
            $this->emitString($slot->dataKey),
            $this->emitString($slot->yields),
            $this->emitString($slot->as),
            $childrenCode,
        );
    }

    private function emitProvide(Provide $provide): string
    {
        return sprintf(
            'new \%s(' . "\n" .
            '            token: %s,' . "\n" .
            '            provider: %s,' . "\n" .
            '            props: %s,' . "\n" .
            '        )',
            Provide::class,
            $this->emitString($provide->token),
            $this->emitString($provide->provider),
            $this->emitProps($provide->props),
        );
    }

    private function emitSource(object $source): string
    {
        return match (true) {
            $source instanceof RouteSource => sprintf(
                'new \%s(name: %s, as: %s)',
                RouteSource::class,
                $this->emitString($source->name),
                $this->emitString($source->as),
            ),
            $source instanceof QuerySource => sprintf(
                'new \%s(name: %s, default: %s, as: %s)',
                QuerySource::class,
                $this->emitString($source->name),
                $this->emitScalar($source->default),
                $this->emitString($source->as),
            ),
            $source instanceof ContextSource => sprintf(
                'new \%s(token: %s, path: %s)',
                ContextSource::class,
                $this->emitString($source->token),
                $this->emitNullableString($source->path),
            ),
            $source instanceof IteratedSource => sprintf(
                'new \%s(token: %s, path: %s)',
                IteratedSource::class,
                $this->emitString($source->token),
                $this->emitNullableString($source->path),
            ),
            $source instanceof ParentDataSource => sprintf(
                'new \%s(key: %s, as: %s)',
                ParentDataSource::class,
                $this->emitString($source->key),
                $this->emitString($source->as),
            ),
            $source instanceof ServiceSource => sprintf(
                'new \%s(class: %s)',
                ServiceSource::class,
                $this->emitString($source->class),
            ),
            default => throw new RuntimeException(
                sprintf(
                    'PhpCodeEmitter: unsupported source type "%s". Register it in the emitter.',
                    $source::class,
                ),
            ),
        };
    }

    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     */
    private function emitSlots(array $slots): string
    {
        if ($slots === []) {
            return '[]';
        }
        $parts = [];
        foreach ($slots as $key => $value) {
            if ($value instanceof PreparedRepeatSlot) {
                $parts[] = $this->emitString($key) . ' => ' . $this->emitPreparedRepeatSlot($value);
            } else {
                $children = array_map([$this, 'emitPreparedPlace'], $value);
                $parts[] = $this->emitString($key) . ' => [' . implode(', ', $children) . ']';
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param array<string, mixed> $props
     */
    private function emitProps(array $props): string
    {
        if ($props === []) {
            return '[]';
        }
        $parts = [];
        foreach ($props as $key => $value) {
            $parts[] = $this->emitString($key) . ' => ' . $this->emitValue($value);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param list<string> $list
     */
    private function emitStringList(array $list): string
    {
        if ($list === []) {
            return '[]';
        }

        return '[' . implode(', ', array_map([$this, 'emitString'], $list)) . ']';
    }

    /**
     * @template T
     * @param list<T> $list
     * @param callable(T): string $emitter
     */
    private function emitList(
        array $list,
        callable $emitter,
    ): string {
        if ($list === []) {
            return '[]';
        }

        return '[' . implode(', ', array_map($emitter, $list)) . ']';
    }

    private function emitValue(mixed $value): string
    {
        if (is_object($value)) {
            return $this->emitSource($value);
        }

        return $this->emitScalar($value);
    }

    private function emitScalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $this->emitString($value);
        }
        if (is_array($value)) {
            return $this->emitProps($value);
        }
        throw new RuntimeException(
            sprintf('PhpCodeEmitter: unsupported scalar type "%s".', gettype($value)),
        );
    }

    private function emitString(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }

    private function emitNullableString(?string $value): string
    {
        if ($value === null) {
            return 'null';
        }

        return $this->emitString($value);
    }
}
