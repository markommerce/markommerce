<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use InvalidArgumentException;
use Markommerce\Layout\Attributes\ProvidesHandles;
use Markommerce\Layout\Exceptions\ChainedHandleProviderException;
use Markommerce\Layout\Exceptions\DanglingAnchorException;
use Markommerce\Layout\Exceptions\DuplicateContextTokenException;
use Markommerce\Layout\Exceptions\DuplicateNameException;
use Markommerce\Layout\Exceptions\DynamicHandleConflictException;
use Markommerce\Layout\Exceptions\LayoutException;
use Markommerce\Layout\Exceptions\MissingDataKeyException;
use Markommerce\Layout\Exceptions\MissingPropException;
use Markommerce\Layout\Exceptions\RepeatTypeMismatchException;
use Markommerce\Layout\Exceptions\TypeMismatchException;
use Markommerce\Layout\Exceptions\UnknownContextException;
use Markommerce\Layout\Exceptions\UnknownIterationException;
use Markommerce\Layout\Source\ContextSource;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;
use Markommerce\Layout\Source\QuerySource;
use Markommerce\Layout\Source\RouteSource;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

class ValidationPhase
{
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/';

    /**
     * Validate all resolved layouts. Throws on first violation.
     *
     * @param array<string, ResolvedLayout> $resolvedLayouts
     *
     * @throws DuplicateNameException|DanglingAnchorException|UnknownContextException|UnknownIterationException|MissingDataKeyException|RepeatTypeMismatchException|TypeMismatchException|MissingPropException|DynamicHandleConflictException|DuplicateContextTokenException|ChainedHandleProviderException|LayoutException
     */
    public function validate(array $resolvedLayouts): void
    {
        foreach ($resolvedLayouts as $handleKey => $layout) {
            $this->validateLayout($handleKey, $layout);
        }

        $this->validateCrossHandleConflicts($resolvedLayouts);
    }

    /**
     * @throws DuplicateNameException|DanglingAnchorException|UnknownContextException|UnknownIterationException|MissingDataKeyException|RepeatTypeMismatchException|TypeMismatchException|MissingPropException|LayoutException
     */
    private function validateLayout(
        string $handleKey,
        ResolvedLayout $layout,
    ): void
    {
        $seenNames = [];
        $contextTokens = array_map(fn ($p) => $p->token, $layout->context);

        $this->validateSlots(
            slots: $layout->slots,
            handleKey: $handleKey,
            seenNames: $seenNames,
            contextTokens: $contextTokens,
            activeIterationTokens: [],
            parentChain: [],
            parentPlace: null,
        );

        // Collect all named placement names for dangling anchor check
        $allNames = $seenNames;
        $this->checkDanglingAnchors($layout->slots, $allNames, $handleKey);
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @param array<string, bool> $seenNames
     * @param list<string> $contextTokens
     * @param list<string> $activeIterationTokens
     * @param list<string> $parentChain
     *
     * @throws DuplicateNameException|UnknownContextException|UnknownIterationException|MissingDataKeyException|RepeatTypeMismatchException|TypeMismatchException|MissingPropException|LayoutException
     */
    private function validateSlots(
        array $slots,
        string $handleKey,
        array &$seenNames,
        array $contextTokens,
        array $activeIterationTokens,
        array $parentChain,
        ?ResolvedPlace $parentPlace,
    ): void {
        foreach ($slots as $slotName => $value) {
            if ($value instanceof ResolvedRepeatSlot) {
                $this->validateRepeatSlot(
                    slot: $value,
                    slotName: $slotName,
                    handleKey: $handleKey,
                    seenNames: $seenNames,
                    contextTokens: $contextTokens,
                    activeIterationTokens: $activeIterationTokens,
                    parentChain: $parentChain,
                    parentPlace: $parentPlace,
                );
            } else {
                foreach ($value as $place) {
                    $this->validatePlace(
                        place: $place,
                        handleKey: $handleKey,
                        seenNames: $seenNames,
                        contextTokens: $contextTokens,
                        activeIterationTokens: $activeIterationTokens,
                        parentChain: $parentChain,
                        parentPlace: $parentPlace,
                    );
                }
            }
        }
    }

    /**
     * @param array<string, bool> $seenNames
     * @param list<string> $contextTokens
     * @param list<string> $activeIterationTokens
     * @param list<string> $parentChain
     *
     * @throws DuplicateNameException|UnknownContextException|UnknownIterationException|MissingDataKeyException|RepeatTypeMismatchException|TypeMismatchException|MissingPropException|LayoutException
     */
    private function validateRepeatSlot(
        ResolvedRepeatSlot $slot,
        string $slotName,
        string $handleKey,
        array &$seenNames,
        array $contextTokens,
        array $activeIterationTokens,
        array $parentChain,
        ?ResolvedPlace $parentPlace,
    ): void {
        // Check that parent placement's data DTO has the dataKey property
        if ($parentPlace !== null) {
            $dtoClass = $this->resolveDtoClass($parentPlace->component, $parentChain, $handleKey);
            $this->checkDtoPropertyExists(
                $dtoClass,
                $slot->dataKey,
                $parentPlace->component,
                $parentChain,
                $handleKey
            );

            // Check that the property is typed as a collection of slot->yields
            $this->checkRepeatItemType($dtoClass, $slot->dataKey, $slot->yields, $parentChain, $handleKey);
        }

        // Add iteration token to active tokens for children
        $newIterationTokens = array_merge($activeIterationTokens, [$slot->as]);

        foreach ($slot->children as $child) {
            $this->validatePlace(
                place: $child,
                handleKey: $handleKey,
                seenNames: $seenNames,
                contextTokens: $contextTokens,
                activeIterationTokens: $newIterationTokens,
                parentChain: $parentChain,
                parentPlace: $parentPlace,
            );
        }
    }

    /**
     * @param array<string, bool> $seenNames
     * @param list<string> $contextTokens
     * @param list<string> $activeIterationTokens
     * @param list<string> $parentChain
     *
     * @throws DuplicateNameException|UnknownContextException|UnknownIterationException|MissingDataKeyException|RepeatTypeMismatchException|TypeMismatchException|MissingPropException|LayoutException
     */
    private function validatePlace(
        ResolvedPlace $place,
        string $handleKey,
        array &$seenNames,
        array $contextTokens,
        array $activeIterationTokens,
        array $parentChain,
        ?ResolvedPlace $parentPlace,
    ): void {
        $currentChain = array_merge($parentChain, $place->name !== null ? [$place->name] : []);

        // 1. Validate name format
        if ($place->name !== null) {
            if (!preg_match(self::NAME_PATTERN, $place->name)) {
                throw new InvalidArgumentException(
                    message: "Invalid placement name '$place->name'.",
                );
            }

            // 2. Check name uniqueness
            if (isset($seenNames[$place->name])) {
                throw DuplicateNameException::forNameWithChain(
                    $place->name,
                    $this->chainToString($currentChain, $handleKey),
                );
            }
            $seenNames[$place->name] = true;
        }

        // 3. Validate props
        $this->validateProps(
            place: $place,
            handleKey: $handleKey,
            contextTokens: $contextTokens,
            activeIterationTokens: $activeIterationTokens,
            parentChain: $currentChain,
            parentPlace: $parentPlace,
        );

        // Recurse into child slots
        $this->validateSlots(
            slots: $place->slots,
            handleKey: $handleKey,
            seenNames: $seenNames,
            contextTokens: $contextTokens,
            activeIterationTokens: $activeIterationTokens,
            parentChain: $currentChain,
            parentPlace: $place,
        );
    }

    /**
     * @param list<string> $contextTokens
     * @param list<string> $activeIterationTokens
     * @param list<string> $parentChain
     *
     * @throws UnknownContextException|UnknownIterationException|MissingDataKeyException|TypeMismatchException|MissingPropException|LayoutException
     */
    private function validateProps(
        ResolvedPlace $place,
        string $handleKey,
        array $contextTokens,
        array $activeIterationTokens,
        array $parentChain,
        ?ResolvedPlace $parentPlace,
    ): void {
        $component = $place->component;
        $chain = $this->chainToString($parentChain, $handleKey);

        // Get data() method parameters
        $params = $this->getDataParameters($component, $parentChain, $handleKey);

        // Check required props
        foreach ($params as $param) {
            $paramName = $param->getName();
            if (!$param->isOptional() && !isset($place->props[$paramName])) {
                throw MissingPropException::forPropWithChain($paramName, $component, $chain);
            }
        }

        // Validate each prop source
        foreach ($place->props as $propName => $source) {
            if ($source instanceof ContextSource) {
                if (!in_array($source->token, $contextTokens, true)) {
                    throw UnknownContextException::forContextWithChain($source->token, $handleKey, $chain);
                }
            } elseif ($source instanceof IteratedSource) {
                if (!in_array($source->token, $activeIterationTokens, true)) {
                    throw UnknownIterationException::forIterationWithChain($source->token, $chain);
                }
            } elseif ($source instanceof ParentDataSource) {
                if ($parentPlace === null) {
                    throw UnknownContextException::forContextWithChain(
                        'parentData (no parent placement)',
                        $handleKey,
                        $chain,
                    );
                }
                $parentDtoClass = $this->resolveDtoClass($parentPlace->component, $parentChain, $handleKey);
                $this->checkDtoPropertyExists(
                    $parentDtoClass,
                    $source->key,
                    $parentPlace->component,
                    $parentChain,
                    $handleKey,
                );
            }

            // Type check: source resolved type vs component param type
            $param = array_find($params, fn ($p) => $p->getName() === $propName);
            if ($param !== null) {
                $this->checkSourceTypeCompatibility($source, $param, $component, $propName, $chain);
            }
        }
    }

    /**
     * Resolve the DTO class from the component's data() return type.
     *
     * @param list<string> $parentChain
     *
     * @return class-string
     *
     * @throws TypeMismatchException
     */
    private function resolveDtoClass(
        string $component,
        array $parentChain,
        string $handleKey,
    ): string
    {
        if (!class_exists($component)) {
            throw new TypeMismatchException(
                message: "Component class '$component' not found.",
                context: $this->chainToString($parentChain, $handleKey),
                suggestion: 'Ensure the component class exists and is autoloaded.',
            );
        }

        $reflection = new ReflectionClass($component);
        if (!$reflection->hasMethod('data')) {
            throw new TypeMismatchException(
                message: "Component '$component' does not have a data() method.",
                context: $this->chainToString($parentChain, $handleKey),
                suggestion: 'Add a data() method to the component that returns a typed DTO.',
            );
        }

        $method = $reflection->getMethod('data');
        $returnType = $method->getReturnType();

        if (!($returnType instanceof ReflectionNamedType) || $returnType->isBuiltin()) {
            throw new TypeMismatchException(
                message: "Component '$component' data() method must return a concrete DTO class, not a built-in or union type.",
                context: $this->chainToString($parentChain, $handleKey),
                suggestion: 'Change the data() return type to a concrete DTO class.',
            );
        }

        /** @var class-string $dtoClass */
        $dtoClass = $returnType->getName();

        return $dtoClass;
    }

    /**
     * @param list<string> $parentChain
     *
     * @throws MissingDataKeyException
     */
    private function checkDtoPropertyExists(
        string $dtoClass,
        string $key,
        string $component,
        array $parentChain,
        string $handleKey,
    ): void {
        $reflection = new ReflectionClass($dtoClass);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $hasProperty = array_any($properties, fn ($p) => $p->getName() === $key);

        if (!$hasProperty) {
            throw MissingDataKeyException::forKeyWithChain(
                $key,
                $component,
                $this->chainToString($parentChain, $handleKey),
            );
        }
    }

    /**
     * @param list<string> $parentChain
     *
     * @throws RepeatTypeMismatchException|LayoutException
     */
    private function checkRepeatItemType(
        string $dtoClass,
        string $dataKey,
        string $yieldsType,
        array $parentChain,
        string $handleKey,
    ): void {
        $reflection = new ReflectionClass($dtoClass);

        if (!$reflection->hasProperty($dataKey)) {
            return; // Already caught by checkDtoPropertyExists
        }

        $property = $reflection->getProperty($dataKey);
        $elementType = $this->resolveCollectionElementType($property);

        if ($elementType === null) {
            return; // Can't determine element type, skip check
        }

        if ($elementType !== $yieldsType) {
            throw RepeatTypeMismatchException::forItemWithChain(
                $yieldsType,
                $elementType,
                $this->chainToString($parentChain, $handleKey),
            );
        }
    }

    /**
     * Resolve the element type from a collection property's @var docblock.
     * Supports: list<X>, array<int, X>, X[]
     */
    private function resolveCollectionElementType(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if ($docComment === false) {
            return null;
        }

        $elementTypeName = null;

        if (preg_match('/@var\s+list<([^\s>]+)>/', $docComment, $matches)) {
            $elementTypeName = $matches[1];
        } elseif (preg_match('/@var\s+array<int,\s*([^\s>]+)>/', $docComment, $matches)) {
            $elementTypeName = $matches[1];
        } elseif (preg_match('/@var\s+([^\s\[\]]+)\[\]/', $docComment, $matches)) {
            $elementTypeName = $matches[1];
        }

        if ($elementTypeName === null) {
            return null;
        }

        return $this->resolveTypeName($elementTypeName, $property->getDeclaringClass());
    }

    /**
     * Resolve a potentially unqualified type name against the declaring class's imports.
     *
     * @param ReflectionClass<object> $declaringClass
     */
    private function resolveTypeName(
        string $typeName,
        ReflectionClass $declaringClass,
    ): string
    {
        // Already fully qualified
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        // Check if it's a built-in type
        $builtins = ['int', 'string', 'bool', 'float', 'array', 'object', 'null', 'mixed'];
        if (in_array($typeName, $builtins, true)) {
            return $typeName;
        }

        // Try to resolve via use statements in the file
        $fileName = $declaringClass->getFileName();
        if ($fileName === false) {
            return $declaringClass->getNamespaceName() . '\\' . $typeName;
        }

        $useStatements = $this->parseUseStatements($fileName);

        if (isset($useStatements[$typeName])) {
            return $useStatements[$typeName];
        }

        // Check if alias (last part of class name) matches
        foreach ($useStatements as $alias => $fqcn) {
            if ($alias === $typeName) {
                return $fqcn;
            }
        }

        // Fall back to the same namespace
        $ns = $declaringClass->getNamespaceName();

        return ($ns !== '' ? $ns . '\\' : '') . $typeName;
    }

    /**
     * Parse use statements from a PHP file.
     *
     * @return array<string, string>  alias => FQCN
     */
    private function parseUseStatements(string $fileName): array
    {
        $source = file_get_contents($fileName);
        if ($source === false) {
            return [];
        }

        $uses = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = trim($match[1]);
                if (str_contains($statement, ' as ')) {
                    [$fqcn, $alias] = array_map('trim', explode(' as ', $statement, 2));
                    $uses[$alias] = $fqcn;
                } else {
                    $parts = explode('\\', $statement);
                    $alias = end($parts);
                    $uses[$alias] = $statement;
                }
            }
        }

        return $uses;
    }

    /**
     * @param list<ReflectionParameter> $params
     *
     * @throws TypeMismatchException
     */
    private function checkSourceTypeCompatibility(
        mixed $source,
        ReflectionParameter $param,
        string $component,
        string $propName,
        string $chain,
    ): void {
        $paramType = $param->getType();
        if (!($paramType instanceof ReflectionNamedType)) {
            return; // Can't check union types or no type
        }

        $expectedType = $paramType->getName();
        $sourceType = $this->resolveSourceType($source);

        if ($sourceType === null) {
            return; // Can't determine source type, skip
        }

        if (!$this->isTypeAssignable($sourceType, $expectedType)) {
            throw TypeMismatchException::forPropWithChain($propName, $expectedType, $sourceType, $chain);
        }
    }

    /**
     * Get the resolved PHP type string for a source.
     */
    private function resolveSourceType(mixed $source): ?string
    {
        return match (true) {
            $source instanceof RouteSource => $source->as,
            $source instanceof QuerySource => $source->as,
            $source instanceof ParentDataSource => $source->as,
            $source instanceof ContextSource => null, // Resolved at runtime
            $source instanceof IteratedSource => null, // Resolved at runtime
            default => null,
        };
    }

    /**
     * Check if $sourceType is assignable to $expectedType.
     */
    private function isTypeAssignable(
        string $sourceType,
        string $expectedType,
    ): bool
    {
        if ($sourceType === $expectedType) {
            return true;
        }

        // Allow subtype assignment for class types
        if (class_exists($sourceType) && class_exists($expectedType)) {
            return is_a($sourceType, $expectedType, true);
        }

        return false;
    }

    /**
     * Get data() method parameters for a component.
     *
     * @param list<string> $parentChain
     *
     * @return list<ReflectionParameter>
     *
     * @throws TypeMismatchException
     */
    private function getDataParameters(
        string $component,
        array $parentChain,
        string $handleKey,
    ): array
    {
        if (!class_exists($component)) {
            return [];
        }

        $reflection = new ReflectionClass($component);
        if (!$reflection->hasMethod('data')) {
            return [];
        }

        $method = $reflection->getMethod('data');

        // Validate return type is a concrete DTO class
        $returnType = $method->getReturnType();
        if (!($returnType instanceof ReflectionNamedType) || $returnType->isBuiltin()) {
            throw new TypeMismatchException(
                message: "Component '$component' data() method must return a concrete DTO class.",
                context: $this->chainToString($parentChain, $handleKey),
                suggestion: 'Change the data() return type to a concrete DTO class.',
            );
        }

        return $method->getParameters();
    }

    /**
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @param array<string, bool> $allNames
     *
     * @throws DanglingAnchorException
     */
    private function checkDanglingAnchors(
        array $slots,
        array $allNames,
        string $handleKey,
    ): void {
        foreach ($slots as $value) {
            if ($value instanceof ResolvedRepeatSlot) {
                foreach ($value->children as $child) {
                    $this->checkPlaceDanglingAnchors($child, $allNames, $handleKey);
                }
            } else {
                foreach ($value as $place) {
                    $this->checkPlaceDanglingAnchors($place, $allNames, $handleKey);
                }
            }
        }
    }

    /**
     * @param array<string, bool> $allNames
     *
     * @throws DanglingAnchorException
     */
    private function checkPlaceDanglingAnchors(
        ResolvedPlace $place,
        array $allNames,
        string $handleKey,
    ): void {
        foreach ($place->decorators as $decorator) {
            // Decorators are decorator class names (from WrapWith operations).
            // Validate that the class exists and implements DecoratorInterface.
            if (!class_exists($decorator)) {
                throw DanglingAnchorException::forAnchorWithChain(
                    $decorator,
                    $handleKey,
                    $this->chainToString([$place->name ?? ''], $handleKey),
                );
            }
        }

        $this->checkDanglingAnchors($place->slots, $allNames, $handleKey);
    }

    /**
     * @param list<string> $chain
     */
    private function chainToString(
        array $chain,
        string $handleKey,
    ): string
    {
        if (empty($chain)) {
            return $handleKey;
        }

        return $handleKey . ' → ' . implode(' > ', $chain);
    }

    /**
     * Validate cross-handle conflicts for layouts with statically-known handleProviders.
     *
     * @param array<string, ResolvedLayout> $resolvedLayouts
     *
     * @throws DynamicHandleConflictException|DuplicateContextTokenException|ChainedHandleProviderException
     */
    private function validateCrossHandleConflicts(array $resolvedLayouts): void
    {
        foreach ($resolvedLayouts as $baseHandleKey => $baseLayout) {
            if ($baseLayout->handleProviders === []) {
                continue;
            }

            $basePlacementNames = $this->collectResolvedPlacementNames($baseLayout->slots);
            $baseContextTokens = array_map(fn ($p) => $p->token, $baseLayout->context);

            foreach ($baseLayout->handleProviders as $provideHandle) {
                $providerClass = $provideHandle->provider;

                if (!class_exists($providerClass)) {
                    continue;
                }

                $providerReflection = new ReflectionClass($providerClass);
                $attrs = $providerReflection->getAttributes(ProvidesHandles::class);

                if ($attrs === []) {
                    // Opaque provider — cannot validate statically
                    continue;
                }

                /** @var ProvidesHandles $providesHandles */
                $providesHandles = $attrs[0]->newInstance();

                foreach ($providesHandles->handles as $dynamicHandle) {
                    if (!isset($resolvedLayouts[$dynamicHandle])) {
                        continue;
                    }

                    $dynamicLayout = $resolvedLayouts[$dynamicHandle];

                    // Check for chained handleProviders on the dynamic handle
                    if ($dynamicLayout->handleProviders !== []) {
                        throw ChainedHandleProviderException::forChain($providerClass, $dynamicHandle);
                    }

                    // Check for placement name conflicts
                    $dynamicPlacementNames = $this->collectResolvedPlacementNames($dynamicLayout->slots);
                    foreach ($dynamicPlacementNames as $name) {
                        if (in_array($name, $basePlacementNames, true)) {
                            throw DynamicHandleConflictException::forCollidingPlacement(
                                $name,
                                $baseHandleKey,
                                $dynamicHandle,
                            );
                        }
                    }

                    // Check for context token conflicts
                    foreach ($dynamicLayout->context as $provide) {
                        if (in_array($provide->token, $baseContextTokens, true)) {
                            throw DuplicateContextTokenException::forToken(
                                $provide->token,
                                $dynamicHandle,
                                $baseHandleKey,
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Recursively collect all named placement names from resolved slots.
     *
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @return list<string>
     */
    private function collectResolvedPlacementNames(array $slots): array
    {
        $names = [];
        foreach ($slots as $value) {
            if ($value instanceof ResolvedRepeatSlot) {
                foreach ($value->children as $child) {
                    $names = array_merge($names, $this->collectResolvedPlaceNames($child));
                }
            } else {
                foreach ($value as $place) {
                    $names = array_merge($names, $this->collectResolvedPlaceNames($place));
                }
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function collectResolvedPlaceNames(ResolvedPlace $place): array
    {
        $names = [];
        if ($place->name !== null) {
            $names[] = $place->name;
        }
        $names = array_merge($names, $this->collectResolvedPlacementNames($place->slots));

        return $names;
    }
}
