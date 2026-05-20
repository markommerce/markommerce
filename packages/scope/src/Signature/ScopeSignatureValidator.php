<?php

declare(strict_types=1);

namespace Markommerce\Scope\Signature;

use Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

class ScopeSignatureValidator
{
    /** @var array<string, true> */
    private array $cache = [];

    public function __construct(
        private ScopeRegistryInterface $scopeRegistry,
    ) {}

    /**
     * @param list<string> $attributeAxes
     *
     * @throws InvalidSignatureForAttributeException
     */
    public function validate(
        ScopeSignature $scopeSignature,
        array $attributeAxes,
    ): void {
        $cacheKey = $scopeSignature->toString() . '||' . implode(',', $attributeAxes);

        if (isset($this->cache[$cacheKey])) {
            return;
        }

        foreach ($scopeSignature->axes() as $axis) {
            if (!in_array($axis, $attributeAxes, true)) {
                throw InvalidSignatureForAttributeException::forUnknownAxis($axis, $attributeAxes);
            }

            $value = $scopeSignature->get($axis);

            if ($value !== null && !$this->scopeRegistry->getHierarchy($axis)->exists($value)) {
                throw InvalidSignatureForAttributeException::forUnknownValue($value, $axis);
            }

            if ($value !== null && $value === $this->scopeRegistry->getAxis($axis)->default) {
                throw InvalidSignatureForAttributeException::forDefaultScope($axis, $value);
            }
        }

        $this->cache[$cacheKey] = true;
    }
}
