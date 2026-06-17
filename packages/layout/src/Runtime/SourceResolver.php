<?php

declare(strict_types=1);

namespace Markommerce\Layout\Runtime;

use InvalidArgumentException;
use Markommerce\Layout\Contracts\SourceInterface;
use Markommerce\Layout\Exceptions\InvalidSourceTypeException;
use Markommerce\Layout\Source\ContextSource;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;
use Markommerce\Layout\Source\QuerySource;
use Markommerce\Layout\Source\RouteSource;
use Markommerce\Layout\Source\ServiceSource;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

class SourceResolver
{
    /**
     * @throws InvalidSourceTypeException|InvalidArgumentException
     */
    public function resolve(
        mixed $source,
        ResolutionContext $context,
    ): mixed {
        return match (true) {
            $source instanceof RouteSource => $this->resolveRoute($source, $context),
            $source instanceof QuerySource => $this->resolveQuery($source, $context),
            $source instanceof ContextSource => $this->resolveContext($source, $context),
            $source instanceof IteratedSource => $this->resolveIterated($source, $context),
            $source instanceof ParentDataSource => $this->resolveParentData($source, $context),
            $source instanceof ServiceSource => $this->resolveService($source, $context),
            !($source instanceof SourceInterface) => $source,
            default => throw new InvalidArgumentException('Unknown source type: ' . get_class($source)),
        };
    }

    /**
     * @throws InvalidSourceTypeException
     */
    private function resolveRoute(
        RouteSource $source,
        ResolutionContext $context,
    ): mixed {
        $value = $context->routeParams[$source->name] ?? null;

        if ($value === null) {
            throw InvalidSourceTypeException::forSource(
                $source->name,
                'null',
                $source->as,
            );
        }

        return $this->castToType($value, $source->as, $source->name);
    }

    private function resolveQuery(
        QuerySource $source,
        ResolutionContext $context,
    ): mixed {
        $value = $context->request->query($source->name, $source->default);

        if ($value === null) {
            return $source->default;
        }

        return $this->castToType($value, $source->as, $source->name);
    }

    private function resolveContext(
        ContextSource $source,
        ResolutionContext $context,
    ): mixed {
        if (!array_key_exists($source->token, $context->contextMap)) {
            throw new RuntimeException(
                "Unknown context token '$source->token' in placement chain '$context->placementChain'.",
            );
        }

        $value = $context->contextMap[$source->token];

        if ($source->path !== null) {
            return $this->walkPath($value, $source->path, $context->placementChain);
        }

        return $value;
    }

    private function resolveIterated(
        IteratedSource $source,
        ResolutionContext $context,
    ): mixed {
        $value = $context->iterationItem;

        if ($source->path !== null) {
            return $this->walkPath($value, $source->path, $context->placementChain);
        }

        return $value;
    }

    /**
     * @throws InvalidSourceTypeException
     */
    private function resolveParentData(
        ParentDataSource $source,
        ResolutionContext $context,
    ): mixed {
        $data = $context->parentData;
        $value = $data->{$source->key};

        return $this->castToType($value, $source->as, $source->key);
    }

    private function resolveService(
        ServiceSource $source,
        ResolutionContext $context,
    ): mixed {
        if ($context->container === null) {
            throw new RuntimeException(
                "Cannot resolve service '$source->class': no container available in placement '$context->placementChain'.",
            );
        }

        return $context->container->get($source->class);
    }

    /**
     * @throws InvalidSourceTypeException
     */
    private function castToType(
        mixed $value,
        string $as,
        string $sourceName,
    ): mixed {
        return match ($as) {
            'int' => is_numeric((string) $value) ? (int) $value : throw InvalidSourceTypeException::forSource(
                $sourceName,
                gettype($value),
                'int',
            ),
            'bool' => (bool) $value,
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [],
            default => $value,
        };
    }

    private function walkPath(
        mixed $value,
        string $path,
        string $placementChain,
    ): mixed {
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (is_array($value)) {
                if (!array_key_exists($segment, $value)) {
                    $available = implode(', ', array_keys($value));
                    throw new RuntimeException(
                        "Path segment '$segment' not found in value at placement '$placementChain'. Available keys: $available.",
                    );
                }
                $value = $value[$segment];
            } elseif (is_object($value)) {
                if (!property_exists($value, $segment)) {
                    $reflection = new ReflectionClass($value);
                    $props = array_map(
                        fn (ReflectionProperty $p) => $p->getName(),
                        $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
                    );
                    $available = implode(', ', $props);
                    throw new RuntimeException(
                        "Path segment '$segment' not found in object at placement '$placementChain'. Available keys: $available.",
                    );
                }
                $value = $value->{$segment};
            } else {
                throw new RuntimeException(
                    "Cannot walk path segment '$segment' on scalar value at placement '$placementChain'.",
                );
            }
        }

        return $value;
    }
}
