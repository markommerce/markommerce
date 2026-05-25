<?php

declare(strict_types=1);

namespace Markommerce\Config\Resolution;

use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

class OverrideMatcher
{
    public function __construct(
        private readonly SignatureCandidateEnumerator $signatureCandidateEnumerator,
    ) {}

    /**
     * @param list<string> $axes
     */
    public function match(
        ConfigRow $row,
        array $axes,
        ScopeContext $context,
    ): mixed
    {
        if ($row->overrides === []) {
            return null;
        }

        $candidates = $this->signatureCandidateEnumerator->enumerate($axes, $context);

        foreach ($candidates as $sig) {
            $key = $sig->toString();

            if (array_key_exists($key, $row->overrides)) {
                return $row->overrides[$key];
            }
        }

        return null;
    }
}
