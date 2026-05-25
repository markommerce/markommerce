<?php

declare(strict_types=1);

namespace Markommerce\Config\Registry;

use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigKeyConflictException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\ValueObjects\ConfigDefinition;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

class ConfigRegistryBuilder
{
    /**
     * @param list<class-string> $configClasses
     *
     * @throws ConfigKeyConflictException
     * @throws AxisNotDeclaredException
     * @throws InvalidConfigClassException
     */
    public function build(
        array $configClasses,
        ScopeRegistryInterface $scopeRegistry,
    ): ConfigRegistry
    {
        /** @var list<ConfigDefinition> $definitions */
        $definitions = [];

        /** @var array<string, class-string> $seenKeys */
        $seenKeys = [];

        foreach ($configClasses as $configClass) {
            $reflection = new ReflectionClass($configClass);

            $this->validateConstructor($reflection, $configClass);

            foreach ($reflection->getProperties() as $property) {
                $configAttributes = $property->getAttributes(Config::class);

                if (count($configAttributes) === 0) {
                    continue;
                }

                /** @var Config $configAttr */
                $configAttr = $configAttributes[0]->newInstance();
                $key = $configAttr->key;
                $secret = $configAttr->secret;
                $field = $property->getName();

                $type = $this->resolveType($reflection->getName(), $field, $property->getType());

                $this->validateDefaultOrNullability($reflection->getName(), $field, $property);

                $defaultValue = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

                $axes = $this->resolveAxes($reflection->getName(), $field, $property, $scopeRegistry);

                if (isset($seenKeys[$key])) {
                    throw ConfigKeyConflictException::forKey($key, $seenKeys[$key], $configClass);
                }

                $seenKeys[$key] = $configClass;

                $definitions[] = new ConfigDefinition(
                    key: $key,
                    configClass: $configClass,
                    field: $field,
                    axes: $axes,
                    type: $type,
                    defaultValue: $defaultValue,
                    secret: $secret,
                );
            }
        }

        return new ConfigRegistry($definitions);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $configClass
     *
     * @throws InvalidConfigClassException
     */
    private function validateConstructor(
        ReflectionClass $reflection,
        string $configClass,
    ): void
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                throw InvalidConfigClassException::configClassHasRequiredConstructor($configClass);
            }
        }
    }

    /**
     * @param class-string $configClass
     *
     * @throws InvalidConfigClassException
     */
    private function resolveType(
        string $configClass,
        string $field,
        ?\ReflectionType $type,
    ): string {
        if ($type instanceof ReflectionUnionType) {
            throw InvalidConfigClassException::propertyWithUnsupportedType(
                $configClass,
                $field,
                'union types are not supported',
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw InvalidConfigClassException::propertyWithUnsupportedType(
                $configClass,
                $field,
                'intersection types are not supported',
            );
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return 'mixed';
    }

    /**
     * @param class-string $configClass
     *
     * @throws InvalidConfigClassException
     */
    private function validateDefaultOrNullability(
        string $configClass,
        string $field,
        \ReflectionProperty $property,
    ): void {
        $type = $property->getType();

        $isNullable = $type instanceof ReflectionNamedType && $type->allowsNull();

        if (!$property->hasDefaultValue() && !$isNullable) {
            throw InvalidConfigClassException::propertyMissingDefaultOrNullability($configClass, $field);
        }
    }

    /**
     * @param class-string $configClass
     *
     * @return list<string>
     * @throws AxisNotDeclaredException
     */
    private function resolveAxes(
        string $configClass,
        string $field,
        \ReflectionProperty $property,
        ScopeRegistryInterface $scopeRegistry,
    ): array {
        $scopedAttributes = $property->getAttributes(Scoped::class);

        if (count($scopedAttributes) === 0) {
            return [];
        }

        /** @var Scoped $scopedAttr */
        $scopedAttr = $scopedAttributes[0]->newInstance();
        $axes = $scopedAttr->axes;

        foreach ($axes as $axis) {
            if (!$scopeRegistry->hasAxis($axis)) {
                throw AxisNotDeclaredException::forAxisOnProperty($configClass, $field, $axis);
            }
        }

        return $axes;
    }
}
