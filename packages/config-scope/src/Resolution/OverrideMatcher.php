<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Resolution;

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

class OverrideMatcher
{
    public function __construct(
        private readonly SignatureCandidateEnumerator $enumerator,
    ) {}

    /**
     * Find the best-matching override for the given axes and context.
     *
     * @param array<string, mixed> $overrides signature => raw value
     * @param list<string> $axes
     */
    public function match(array $overrides, array $axes, ScopeContext $context): mixed
    {
        if ($overrides === []) {
            return null;
        }

        $candidates = $this->enumerator->enumerate($axes, $context);

        foreach ($candidates as $candidate) {
            $key = $candidate->toString();
            if (array_key_exists($key, $overrides)) {
                return $overrides[$key];
            }
        }

        return null;
    }
}
