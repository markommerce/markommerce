<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttribute\Definition;

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;

class ProductAttributeDefinitions
{
    public function __construct(
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private readonly StaticAttributeProvider $staticAttributeProvider,
    ) {}

    public function findByCode(string $code): ?AttributeDefinition
    {
        $statics = $this->staticAttributeProvider->definitions();

        $static = array_find($statics, fn (AttributeDefinition $d) => $d->code === $code);

        if ($static !== null) {
            return $static;
        }

        return $this->attributeDefinitionRepository->findByCode('product', $code);
    }
}
