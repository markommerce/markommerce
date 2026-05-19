<?php

declare(strict_types=1);

namespace Markommerce\Scope\Signature;

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

class SignatureCandidateEnumerator
{
    /** @var array<string, list<ScopeSignature>> */
    private array $cache = [];

    public function __construct(
        private readonly ScopeRegistryInterface $scopeRegistry,
        private readonly int $cap = 256,
    ) {}

    /**
     * Enumerate candidate ScopeSignature objects for the given attribute axes and context.
     *
     * @param list<string> $attributeAxes
     * @return list<ScopeSignature>
     */
    public function enumerate(
        array $attributeAxes,
        ScopeContext $context,
    ): array {
        if ($attributeAxes === []) {
            return [];
        }

        $state = $context->state();
        $ksortedState = $state;
        ksort($ksortedState);
        $cacheKey = serialize($attributeAxes) . '||' . serialize($ksortedState);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Build per-axis walk-up value lists (with OMIT sentinel at the end)
        $axisValues = [];
        foreach ($attributeAxes as $axis) {
            $path = $state[$axis] ?? null;
            if ($path !== null && $this->scopeRegistry->hasAxis($axis)) {
                $walked = $this->scopeRegistry->getHierarchy($axis)->walkUp($path);
                $axisValues[$axis] = array_merge($walked, [null]);
            } else {
                // Axis not active in context — OMIT-only loop (one null iteration)
                $axisValues[$axis] = [null];
            }
        }

        $candidates = [];
        $warningFired = false;
        $capExceeded = false;

        $this->cartesian($attributeAxes, $axisValues, 0, [], $candidates, $warningFired, $capExceeded);

        $this->cache[$cacheKey] = $candidates;

        return $candidates;
    }

    /**
     * Recursively build the cartesian product of axis value lists.
     *
     * @param list<string> $attributeAxes
     * @param array<string, list<string|null>> $axisValues
     * @param array<string, string> $current
     * @param list<ScopeSignature> $candidates
     */
    private function cartesian(
        array $attributeAxes,
        array $axisValues,
        int $depth,
        array $current,
        array &$candidates,
        bool &$warningFired,
        bool &$capExceeded,
    ): void {
        if ($capExceeded) {
            return;
        }

        if ($depth === count($attributeAxes)) {
            // Skip if all axes are OMIT (no values in current)
            if ($current === []) {
                return;
            }

            if (count($candidates) >= $this->cap) {
                if (!$warningFired) {
                    trigger_error(
                        'SignatureCandidateEnumerator: cap of ' . $this->cap . ' candidates exceeded; truncating.',
                        E_USER_WARNING,
                    );
                    $warningFired = true;
                }
                $capExceeded = true;

                return;
            }

            $candidates[] = new ScopeSignature($current);

            return;
        }

        $axis = $attributeAxes[$depth];
        foreach ($axisValues[$axis] as $value) {
            if ($capExceeded) {
                return;
            }

            if ($value === null) {
                // OMIT — don't include this axis in the signature
                $this->cartesian(
                    $attributeAxes,
                    $axisValues,
                    $depth + 1,
                    $current,
                    $candidates,
                    $warningFired,
                    $capExceeded,
                );
            } else {
                $next = $current;
                $next[$axis] = $value;
                $this->cartesian(
                    $attributeAxes,
                    $axisValues,
                    $depth + 1,
                    $next,
                    $candidates,
                    $warningFired,
                    $capExceeded,
                );
            }
        }
    }
}
