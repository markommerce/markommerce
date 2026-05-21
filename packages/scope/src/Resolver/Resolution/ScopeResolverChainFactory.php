<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Scope\Exceptions\InvalidResolverConfigException;

class ScopeResolverChainFactory
{
    /** @var array<string, list<ScopeAxisResolverInterface>> */
    private array $cache = [];

    public function __construct(
        private ContainerInterface $container,
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * Build the chain of resolvers for the given axis name.
     *
     * @return list<ScopeAxisResolverInterface>
     *
     * @throws InvalidResolverConfigException
     */
    public function for(string $axisName): array
    {
        if (isset($this->cache[$axisName])) {
            return $this->cache[$axisName];
        }

        $configKey = "scope.axes.$axisName.resolvers";

        if (!$this->configRepository->has($configKey)) {
            $this->cache[$axisName] = [];

            return [];
        }

        $entries = $this->configRepository->getArray($configKey);

        if ($entries === []) {
            $this->cache[$axisName] = [];

            return [];
        }

        $chain = [];

        foreach ($entries as $index => $entry) {
            $chain[] = $this->buildResolver($entry, $index, $axisName);
        }

        $this->cache[$axisName] = $chain;

        return $chain;
    }

    /**
     * @param mixed $entry
     *
     * @throws InvalidResolverConfigException
     */
    private function buildResolver(mixed $entry, int $index, string $axisName): ScopeAxisResolverInterface
    {
        if (is_string($entry)) {
            return $this->buildFromClassString($entry, $axisName);
        }

        return $this->buildFromArray($entry, $index, $axisName);
    }

    /**
     * @throws InvalidResolverConfigException
     */
    private function buildFromClassString(string $class, string $axisName): ScopeAxisResolverInterface
    {
        if (!class_exists($class)) {
            throw InvalidResolverConfigException::unknownClass($class, $axisName);
        }

        $resolver = $this->container->get($class);

        if (!$resolver instanceof ScopeAxisResolverInterface) {
            throw InvalidResolverConfigException::notImplementingInterface($class, $axisName);
        }

        return $resolver;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @throws InvalidResolverConfigException
     */
    private function buildFromArray(array $entry, int $index, string $axisName): ScopeAxisResolverInterface
    {
        if (!isset($entry['class'])) {
            throw InvalidResolverConfigException::missingClassKey($index, $axisName);
        }

        $class = $entry['class'];

        if (!class_exists($class)) {
            throw InvalidResolverConfigException::unknownClass($class, $axisName);
        }

        $extraArgs = array_filter(
            $entry,
            fn (string $key) => $key !== 'class',
            ARRAY_FILTER_USE_KEY,
        );

        $resolver = new $class(...$extraArgs);

        if (!$resolver instanceof ScopeAxisResolverInterface) {
            throw InvalidResolverConfigException::notImplementingInterface($class, $axisName);
        }

        return $resolver;
    }
}
