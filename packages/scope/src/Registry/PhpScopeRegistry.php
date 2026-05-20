<?php

declare(strict_types=1);

namespace Markommerce\Scope\Registry;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\ScopeConfigurationException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;

readonly class PhpScopeRegistry implements ScopeRegistryInterface
{
    /** @var array<string, ScopeAxis> */
    private array $axes;

    /**
     * @throws ConfigNotFoundException|ScopeConfigurationException
     */
    public function __construct(ConfigRepositoryInterface $config)
    {
        $rawAxes = $config->getArray('scope.axes');
        $this->axes = $this->buildAxes($rawAxes);
    }

    public function hasAxis(string $name): bool
    {
        return isset($this->axes[$name]);
    }

    /**
     * @throws UnknownAxisException
     */
    public function getAxis(string $name): ScopeAxis
    {
        if (!isset($this->axes[$name])) {
            throw UnknownAxisException::forAxis($name);
        }

        return $this->axes[$name];
    }

    /**
     * @return list<string>
     */
    public function listAxes(): array
    {
        return array_keys($this->axes);
    }

    /**
     * @throws UnknownAxisException
     */
    public function getHierarchy(string $axisName): ScopeHierarchy
    {
        return $this->getAxis($axisName)->hierarchy;
    }

    /**
     * @param array<mixed> $rawAxes
     * @return array<string, ScopeAxis>
     * @throws ScopeConfigurationException
     */
    private function buildAxes(array $rawAxes): array
    {
        $axes = [];

        foreach ($rawAxes as $name => $definition) {
            $axisName = (string) $name;

            if (!is_array($definition)) {
                throw ScopeConfigurationException::malformedConfig(
                    $axisName,
                    'axis definition must be an array',
                );
            }

            $scopes = $definition['scopes'] ?? null;

            if (!is_array($scopes)) {
                throw ScopeConfigurationException::malformedConfig(
                    $axisName,
                    "'scopes' must be an array",
                );
            }

            if ($scopes === []) {
                throw ScopeConfigurationException::emptyScopesMap($axisName);
            }

            if (!array_key_exists('default', $definition)) {
                throw ScopeConfigurationException::missingDefault($axisName);
            }

            $default = $definition['default'];

            if (!array_key_exists($default, $scopes)) {
                throw ScopeConfigurationException::defaultNotInScopes($axisName, (string) $default);
            }

            /** @var list<string> $paths */
            $paths = array_keys($scopes);
            $hierarchy = ScopeHierarchy::fromPaths($paths);
            $axes[$axisName] = new ScopeAxis(name: $axisName, hierarchy: $hierarchy, default: (string) $default);
        }

        return $axes;
    }
}
