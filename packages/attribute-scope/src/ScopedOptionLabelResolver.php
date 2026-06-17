<?php

declare(strict_types=1);

namespace Markommerce\AttributeScope;

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Resolution\ScopeWalker;

readonly class ScopedOptionLabelResolver
{
    public function __construct(
        private AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private ScopeWalker $scopeWalker,
    ) {}

    /**
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function resolve(
        AttributeOption $option,
        AttributeOptionScopedLabels $labels,
        ScopeContext $context,
    ): string {
        $definition = $option->attributeId !== null
            ? $this->attributeDefinitionRepository->find($option->attributeId)
            : null;

        /** @var list<string> $axes */
        $axes = $definition !== null ? ($definition->config()['axes'] ?? []) : [];

        $result = $this->scopeWalker->walk($labels, 'label', $axes, $context);

        if ($result->isFound()) {
            return (string) $result->value();
        }

        return $option->label;
    }
}
