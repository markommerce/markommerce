<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttribute\Definition;

use Markommerce\Attribute\Entity\AttributeDefinition;

class StaticAttributeProvider
{
    /**
     * @return list<AttributeDefinition>
     */
    public function definitions(): array
    {
        $sku = new AttributeDefinition();
        $sku->code = 'sku';
        $sku->entityType = 'product';
        $sku->type = 'text';
        $sku->backing = 'Column';
        $sku->config = ['property' => 'sku'];

        $name = new AttributeDefinition();
        $name->code = 'name';
        $name->entityType = 'product';
        $name->type = 'text';
        $name->backing = 'Column';
        $name->config = ['property' => 'name'];

        $priceAmount = new AttributeDefinition();
        $priceAmount->code = 'priceAmount';
        $priceAmount->entityType = 'product';
        $priceAmount->type = 'decimal';
        $priceAmount->backing = 'Column';
        $priceAmount->config = ['property' => 'priceAmount'];

        return [$sku, $name, $priceAmount];
    }
}
