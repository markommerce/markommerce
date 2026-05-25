<?php

declare(strict_types=1);

namespace Markommerce\Config\Proxy;

use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\ValueObjects\ConfigDefinition;
use ReflectionClass;

class ProxyGenerator
{
    private const string GENERATED_NAMESPACE_PREFIX = 'Markommerce\\Config\\Generated\\';

    /**
     * @param class-string $configClass
     * @param list<ConfigDefinition> $definitions
     *
     * @throws InvalidConfigClassException
     */
    public function generate(
        string $configClass,
        array $definitions,
    ): string
    {
        $this->validateConstructor($configClass);

        foreach ($definitions as $definition) {
            $this->validateDefinition($definition);
        }

        $originalNamespace = $this->extractNamespace($configClass);
        $originalClassName = $this->extractClassName($configClass);

        $generatedNamespace = self::GENERATED_NAMESPACE_PREFIX . $originalNamespace;
        $generatedClassName = $originalClassName . '_Resolved';

        $propertiesSource = $this->buildProperties($configClass, $definitions);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$generatedNamespace};

        use Markommerce\\Config\\ConfigResolver;

        class {$generatedClassName} extends \\{$configClass}
        {
            public function __construct(
                private ConfigResolver \$__resolver,
            ) {}
        {$propertiesSource}
        }
        PHP;
    }

    /**
     * @param class-string $configClass
     *
     * @throws InvalidConfigClassException
     */
    private function validateConstructor(string $configClass): void
    {
        $reflection = new ReflectionClass($configClass);
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
     * @throws InvalidConfigClassException
     */
    private function validateDefinition(ConfigDefinition $definition): void
    {
        /** @var class-string $configClass */
        $configClass = $definition->configClass;
        $field = $definition->field;

        $reflection = new ReflectionClass($configClass);
        $property = $reflection->getProperty($field);

        if ($property->isReadOnly()) {
            throw InvalidConfigClassException::propertyWithUnsupportedType(
                $configClass,
                $field,
                'readonly properties are not supported',
            );
        }

        if (str_contains($definition->type, '|') || str_contains($definition->type, '&')) {
            throw InvalidConfigClassException::propertyWithUnsupportedType(
                $configClass,
                $field,
                'union and intersection types are not supported',
            );
        }
    }

    /**
     * @param class-string $configClass
     * @param list<ConfigDefinition> $definitions
     */
    private function buildProperties(
        string $configClass,
        array $definitions,
    ): string
    {
        $parts = [];

        foreach ($definitions as $definition) {
            $type = $this->resolveTypeString($definition->type);
            $field = $definition->field;
            $parts[] = <<<PHP

                public {$type} \${$field} {
                    get => \$this->__resolver->resolved(
                        \\{$configClass}::class,
                        '{$field}',
                    );
                }
            PHP;
        }

        return implode('', $parts);
    }

    private function resolveTypeString(string $type): string
    {
        // If the type is a class name (not a scalar), prefix with backslash for FQN
        $scalars = ['int', 'string', 'float', 'bool', 'array', 'null', 'mixed', 'void', 'never', 'object', 'iterable', 'callable'];

        if (in_array($type, $scalars, true)) {
            return $type;
        }

        // Nullable scalar types like ?string
        if (str_starts_with($type, '?')) {
            $inner = substr($type, 1);
            if (in_array($inner, $scalars, true)) {
                return $type;
            }

            return '?\\' . $inner;
        }

        // Class or enum type — prefix with backslash if not already prefixed
        if (!str_starts_with($type, '\\')) {
            return '\\' . $type;
        }

        return $type;
    }

    private function extractNamespace(string $fqn): string
    {
        $lastBackslash = strrpos($fqn, '\\');
        if ($lastBackslash === false) {
            return '';
        }

        return substr($fqn, 0, $lastBackslash);
    }

    private function extractClassName(string $fqn): string
    {
        $lastBackslash = strrpos($fqn, '\\');
        if ($lastBackslash === false) {
            return $fqn;
        }

        return substr($fqn, $lastBackslash + 1);
    }
}
