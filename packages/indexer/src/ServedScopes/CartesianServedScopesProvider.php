<?php

declare(strict_types=1);

namespace Markommerce\Indexer\ServedScopes;

use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\ScopeSignature;

class CartesianServedScopesProvider implements ServedScopesProviderInterface
{
    /**
     * Maximum number of signatures returned. Cartesian products exceeding this cap
     * are truncated and a warning is logged via trigger_error().
     */
    public const int MAX_SIGNATURES = 1024;

    public function __construct(
        private ScopeRegistryInterface $scopeRegistry,
    ) {}

    /**
     * @param list<string> $axes
     * @return list<ScopeSignature>
     */
    public function signatures(array $axes): array
    {
        if ($axes === []) {
            return [];
        }

        $axisNonDefaultPaths = [];
        foreach ($axes as $axis) {
            if (!$this->scopeRegistry->hasAxis($axis)) {
                continue;
            }

            $default = $this->scopeRegistry->getAxis($axis)->default;
            $paths = $this->scopeRegistry->getHierarchy($axis)->paths();
            $nonDefault = array_values(array_filter($paths, fn (string $p): bool => $p !== $default));

            if ($nonDefault === []) {
                continue;
            }

            $axisNonDefaultPaths[$axis] = $nonDefault;
        }

        if ($axisNonDefaultPaths === []) {
            return [];
        }

        $signatures = [];
        $warningFired = false;
        $this->buildCartesian(
            array_keys($axisNonDefaultPaths),
            $axisNonDefaultPaths,
            0,
            [],
            $signatures,
            $warningFired
        );

        return $signatures;
    }

    /**
     * Recursively build the cartesian product of non-default paths per axis.
     *
     * @param list<string> $axes
     * @param array<string, list<string>> $axisNonDefaultPaths
     * @param array<string, string> $current
     * @param list<ScopeSignature> $signatures
     */
    private function buildCartesian(
        array $axes,
        array $axisNonDefaultPaths,
        int $depth,
        array $current,
        array &$signatures,
        bool &$warningFired,
    ): void {
        if ($depth === count($axes)) {
            if (count($signatures) >= self::MAX_SIGNATURES) {
                if (!$warningFired) {
                    trigger_error(
                        'CartesianServedScopesProvider: cap of ' . self::MAX_SIGNATURES . ' signatures exceeded; truncating.',
                        E_USER_WARNING,
                    );
                    $warningFired = true;
                }

                return;
            }

            $signatures[] = new ScopeSignature($current);

            return;
        }

        $axis = $axes[$depth];
        foreach ($axisNonDefaultPaths[$axis] as $path) {
            $next = $current;
            $next[$axis] = $path;
            $this->buildCartesian($axes, $axisNonDefaultPaths, $depth + 1, $next, $signatures, $warningFired);
        }
    }
}
